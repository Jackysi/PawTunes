<?php

/**
 * PawTunes Project - Open Source Radio Player
 *
 * @author       Jacky (Jaka Prasnikar)
 * @email        jacky@prahec.com
 * @website      https://prahec.com
 * @repository   https://github.com/Jackysi/pawtunes
 * This file is part of the PawTunes open-source project.
 * Contributions and feedback are welcome! Visit the repository or website for more details.
 */

namespace lib\AI;

use lib\Helpers;
use lib\PawException;
use lib\PawTunes;
use Throwable;

/**
 * AI Artwork for artists no artwork source knows: a real picture found by a web search, or abstract artwork drawn
 * by an image model.
 *
 * One attempt per artist (see generate()):
 *  a. Web search ("search" option, AI Assistant on Anthropic Claude): Claude looks for the artist's page on a music
 *     site, PawTunes reads that page's og:image. The answer also carries an artwork concept for step b.
 *  b. Drawing: AI Assistant turns the names into a concept (mood, style, palette and a short motif, e.g.
 *     "Fractured Truth" -> "shattered glass shards"), the image model draws it. Without AI Assistant the prompt
 *     is seeded by the name alone.
 *
 * Safeguards, in the order they apply:
 *  1. Played only: an artist qualifies only after handler.php saw it as "now playing" on the station's own stream
 *     (markPlayed). The public "?artwork&artist=..." URL alone can never start a paid attempt, and the title used
 *     is the stream's, never one from a URL.
 *  2. One attempt per artist per day, and at most one attempt per request.
 *  3. Daily budget: a file ledger under an exclusive lock, outside the cache, so a cache flush or a dead cache
 *     backend cannot reset or bypass it. If the ledger cannot be read or written, nothing is attempted.
 *  4. One attempt per artist at a time (atomic lock file), however many listeners ask at once.
 *  5. Prompt injection: names reach AI Assistant only cleaned and cut to Assistant::ARTIST_MAX_LENGTH and
 *     TITLE_MAX_LENGTH, and never reach the image model. Search results come only from music sites, the page must
 *     be one the search really returned, and a concept is reduced to list words plus a few plain words
 *     (ArtworkPrompt::normalizeConcept). The image prompt forbids text, people and cover layouts.
 *  6. Vision check (optional, off by default): see ImageCheck. Only drawn images are checked, found pictures are
 *     real covers and photos. Rejected images are thrown away, the budget stays spent.
 *  7. Re-encoded through GD: whatever the provider or the site sent, only plain pixels are stored.
 *  8. Kept out of the cache path and out of data/images: Flush Cache and updates cannot wipe them (no paying
 *     twice), and they are checked only after every real source, so a real cover always wins.
 *
 * Generation runs after the response was sent (PawTunes::afterResponse): the first listener sees the default
 * artwork, later plays show the AI artwork.
 */
final class ArtworkGenerator
{

    public const DIRECTORY = './data/ai-artwork';

    private const LEDGER = self::DIRECTORY.'/.ledger.json';

    /**
     * How long a "now playing" artist stays eligible: covers its history entries and lazy artwork requests
     */
    private const PLAYED_TTL = 6 * 3600;

    /**
     * After an attempt (success, failure or rejection) the artist is not tried again for a day
     */
    private const RETRY_AFTER = 86400;

    /**
     * A lock older than this belongs to a crashed request and may be taken over
     */
    private const LOCK_TIMEOUT = 600;

    public const DEFAULT_DAILY_LIMIT = 20;

    private const PLAYED_PREFIX = 'played:';

    /**
     * Seconds for fetching the music page and its picture (runs after the response, nobody waits for it)
     */
    private const PAGE_TIMEOUT = 15;

    /**
     * An honest client name: Bandcamp and Last.fm answer the browser-like PawTunes default with a bot challenge
     */
    private const PAGE_USER_AGENT = 'PawTunes (+https://github.com/Jackysi/PawTunes)';

    /**
     * og:image values that are site placeholders, not the artist: Last.fm's grey star, generic "og-image" banners
     */
    private const PLACEHOLDER_IMAGES = ['2a96cbd8b46e442fc41c2b86b821562f', 'og-image', 'og_image', 'default'];

    private PawTunes $pawtunes;

    private array $config;

    /**
     * At most one generation per request: non-lazy track info asks for the current track and every history item
     */
    private static bool $queued = false;


    public function __construct(PawTunes $pawtunes)
    {
        $this->pawtunes = $pawtunes;
        $this->config   = $pawtunes->config('ai')['images'] ?? [];
    }


    public static function enabled(PawTunes $pawtunes): bool
    {
        return ! empty($pawtunes->config('ai')['images']['enabled']);
    }


    /**
     * handler.php: the station's stream is playing this artist right now, so it may get AI artwork
     *
     * @param  PawTunes  $pawtunes
     * @param  string  $artist
     * @param  string  $title  The song, kept with the entry: the attempt uses this title, never one from a URL
     *
     * @return void
     */
    public static function markPlayed(PawTunes $pawtunes, string $artist, string $title = ''): void
    {
        $name = $pawtunes->parseTrack($artist);
        if ( ! self::enabled($pawtunes) || $name === '' || $artist === $pawtunes->config('artist_default')) {
            return;
        }

        // add(), not set(): this runs on every track info refresh while the artist plays, and an existing entry
        // needs no rewrite. The TTL counts from the first refresh that saw the artist, which is when its artwork
        // is first requested (and queued); once it expires, the next play adds a fresh entry.
        // The value carries the song (the prefix keeps it truthy for an empty title, a string because not every
        // cache backend stores arrays), so the attempt uses the stream's title, never one from a URL.
        $title = ($title === $pawtunes->config('title_default')) ? '' : mb_substr($title, 0, 200);
        $pawtunes->cache->add('ai.played.'.md5($name), self::PLAYED_PREFIX.$title, self::PLAYED_TTL);
    }


    /**
     * Stored AI artwork of an artist
     *
     * @param  string  $name  Normalized artist name (PawTunes::parseTrack)
     *
     * @return string|null
     */
    public static function existing(string $name): ?string
    {
        $file = self::DIRECTORY."/{$name}.jpg";

        return is_file($file) ? $file : null;
    }


    /**
     * Ask for AI artwork when no source had any. Cheap checks happen now, the generation after the response.
     *
     * @param  PawTunes  $pawtunes
     * @param  string  $artist
     *
     * @return void
     */
    public static function queue(PawTunes $pawtunes, string $artist): void
    {
        $name = $pawtunes->parseTrack($artist);
        if (self::$queued || $name === '' || ! self::enabled($pawtunes)) {
            return;
        }

        // Safeguard 1: only artists the stream really played, never a name typed into a URL
        $played = $pawtunes->cache->get('ai.played.'.md5($name));
        if ( ! $played) {
            return;
        }

        $title = (is_string($played) && strpos($played, self::PLAYED_PREFIX) === 0) ? substr($played, strlen(self::PLAYED_PREFIX)) : '';

        // Safeguard 2: one attempt per artist per day. add() is atomic, so of several listeners asking for a new
        // artist at the same moment only one gets to queue it. False also when the cache cannot record the attempt:
        // then no attempt is made either.
        if ( ! $pawtunes->cache->add('ai.attempt.'.md5($name), time(), self::RETRY_AFTER)) {
            return;
        }

        self::$queued = true;
        $pawtunes->afterResponse(static function () use ($pawtunes, $artist, $name, $title) {
            (new self($pawtunes))->generate($artist, $name, $title);
        });
    }


    /**
     * The whole pipeline for one artist, logs instead of throwing (runs after the response)
     *
     * @param  string  $artist  As the stream sent it (only AI Assistant reads it, never the image model)
     * @param  string  $name  Normalized artist name, also the file name
     * @param  string  $title  The song the stream played with it, may be empty
     *
     * @return string|null Path of the stored artwork
     */
    public function generate(string $artist, string $name, string $title = ''): ?string
    {
        $lock = $this->lock($name);
        if ($lock === null) {
            return null;
        }

        try {

            // Another request may have finished this artist while we waited for the lock: never pay twice
            if (($stored = self::existing($name)) !== null) {
                return $stored;
            }

            // Built first: a misconfiguration (missing key, unknown provider) must not spend budget
            $model = $this->model();

            if ( ! $this->reserveBudget()) {
                $this->log("daily limit reached (or ".self::LEDGER." is not writable), \"{$name}\" keeps its current artwork");

                return null;
            }

            // A real picture first: one search answers both "where is a picture" and "what would we draw"
            [$picture, $concept] = $this->search($artist, $title);
            if ($picture !== null) {
                return $this->store($name, $picture);
            }

            [$bytes, $prompt] = $this->draw($model, $artist, $name, $title, $concept);

            $reason = $this->rejectReason($bytes);
            if ($reason !== null) {
                $this->log("discarded the image for \"{$name}\": {$reason}");

                return null;
            }

            $this->log("drew artwork for \"{$name}\": {$prompt}");

            return $this->store($name, $bytes);

        } catch (Throwable $e) {

            $this->log("could not create artwork for \"{$name}\": {$e->getMessage()}", $e);

            return null;

        } finally {

            @unlink($lock);

        }
    }


    /**
     * The configured image model (validates the settings, so call it before reserveBudget)
     *
     * @return ImageModel
     * @throws PawException
     */
    public function model(): ImageModel
    {
        return Providers::buildImage($this->pawtunes, $this->config);
    }


    /**
     * Step a: web search for a real picture, when enabled and AI Assistant's model can search
     *
     * @param  string  $artist
     * @param  string  $title
     *
     * @return array [picture bytes or null, concept or null (null: draw() asks for one itself)]
     */
    public function search(string $artist, string $title): array
    {
        // On unless switched off: installs that enabled AI Artwork before the option existed search too
        if ( ! ($this->config['search'] ?? true)) {
            return [null, null];
        }

        try {

            $text  = Providers::textModel($this->pawtunes);
            $found = ($text !== null) ? (new Assistant($text))->findArtwork($artist, $title) : null;
            if ($found === null) {
                $this->debug('web search skipped, AI Assistant is off or its model cannot search the web (only Anthropic Claude can)');

                return [null, null];
            }

            if ($found['issue'] !== null) {
                $this->debug("web search for \"{$artist}\": {$found['issue']}");
            }

            if ($found['page'] !== null) {

                $picture = $this->pictureFromPage($found['page']);
                if ($picture !== null) {
                    $this->log("found a picture for \"{$artist}\" on {$found['page']}");

                    return [$picture, null];
                }

                $this->log("{$found['page']} has no usable picture for \"{$artist}\", drawing one instead");

            }

            if ($found['page'] === null) {
                $this->log("no picture of \"{$artist}\" on music sites, drawing one instead");
            }

            return [null, $found['concept']];

        } catch (Throwable $e) {

            $this->log("web search failed for \"{$artist}\", drawing instead: {$e->getMessage()}", $e);

            return [null, null];

        }
    }


    /**
     * The picture a music page shares (og:image), as image bytes
     *
     * @param  string  $page  A search result on one of Assistant::ARTWORK_SITES
     *
     * @return string|null Null when the page or its picture is unusable
     */
    private function pictureFromPage(string $page): ?string
    {
        // Each "return null" says why in the debug log, the caller only logs that the page had no usable picture
        if ( ! $this->onArtworkSite($page) || ! Helpers::isPublicURL($page)) {
            $this->debug("{$page} is not a public page on a music site, ignored");

            return null;
        }

        $options = [CURLOPT_USERAGENT => self::PAGE_USER_AGENT];

        $html = $this->pawtunes->get($page, null, null, false, self::PAGE_TIMEOUT, $error, $options);
        if ( ! is_string($html) || $html === '') {
            $this->debug("{$page} could not be loaded: ".($error ?: 'empty answer'));

            return null;
        }

        // <meta property="og:image" content="...">, attributes in either order
        if (
            ! preg_match('~<meta[^>]+(?:property|name)=["\'](?:og:image|twitter:image)["\'][^>]*\scontent=["\']([^"\']+)~i', $html, $match)
            && ! preg_match('~<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\'](?:og:image|twitter:image)["\']~i', $html, $match)
        ) {
            $this->debug("{$page} has no og:image or twitter:image (a bot challenge page looks the same)");

            return null;
        }

        $image = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ( ! Helpers::isPublicURL($image) || str_ireplace(self::PLACEHOLDER_IMAGES, '', $image) !== $image) {
            $this->debug("{$page} shares a placeholder or non-public picture: ".mb_substr($image, 0, 200));

            return null;
        }

        $image = $this->squareAppleImage($image);
        $bytes = $this->pawtunes->get($image, null, null, false, self::PAGE_TIMEOUT, $error, $options);
        $info  = is_string($bytes) ? @getimagesizefromstring($bytes) : false;

        // Site logos and placeholders are small, a real cover or photo is not
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || min($info[0], $info[1]) < 200) {
            $what = ($info === false) ? 'not an image'.($error ? " ({$error})" : '') : "{$info[0]}x{$info[1]} {$info['mime']}";
            $this->debug("the picture on {$page} is unusable ({$what}, a JPEG, PNG or WebP of at least 200 px is needed)");

            return null;
        }

        return $bytes;
    }


    /**
     * Apple Music shares artists as ".../1200x630cw.png": the photo in a circle on a light banner, which the square
     * crop turns into a round picture on a white tile. Its image CDN renders any "{W}x{H}{mode}.{ext}", so ask for
     * the plain square photo ("bb") instead, like the iTunes and Apple Music artwork sources do.
     *
     * @param  string  $image
     *
     * @return string
     */
    private function squareAppleImage(string $image): string
    {
        $host = strtolower((string) parse_url($image, PHP_URL_HOST));
        if (substr($host, -strlen('.mzstatic.com')) !== '.mzstatic.com') {
            return $image;
        }

        // At least 600 px, so the crop to images_size (280 by default) only ever scales down
        $size = max(600, (int) $this->pawtunes->config('images_size'));

        return preg_replace('~/\d+x\d+[a-z]*\.(?:png|jpe?g|webp)$~i', "/{$size}x{$size}bb.jpg", $image);
    }


    private function onArtworkSite(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach (Assistant::ARTWORK_SITES as $site) {
            if ($host === $site || substr($host, -strlen(".{$site}")) === ".{$site}") {
                return true;
            }
        }

        return false;
    }


    /**
     * Step b: prompt and image for an artist (also used by the panel's test)
     *
     * @param  ImageModel  $model
     * @param  string  $artist
     * @param  string  $name
     * @param  string  $title
     * @param  array|null  $concept  From the web search, null to ask AI Assistant for one now
     *
     * @return array [image bytes, prompt]
     * @throws PawException
     */
    public function draw(ImageModel $model, string $artist, string $name, string $title = '', ?array $concept = null): array
    {
        // With AI Assistant on, the artwork fits the music and the names, seeded by the name otherwise
        if ($concept === null) {
            try {
                $text    = Providers::textModel($this->pawtunes);
                $concept = ($text !== null) ? (new Assistant($text))->artworkConcept($artist, $title) : [];
            } catch (Throwable $e) {
                $this->log("concept lookup failed, using a seeded prompt: {$e->getMessage()}", $e);
            }
        }

        $prompt = ArtworkPrompt::build($name, $concept ?? []);
        $bytes  = $model->generate($prompt);

        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] < 256 || $info[1] < 256) {
            throw new PawException('the provider did not return a usable JPEG, PNG or WebP image');
        }

        return [$bytes, $prompt];
    }


    /**
     * Safeguard 6: the vision check, when enabled and AI Assistant can run it
     *
     * @param  string  $bytes
     *
     * @return string|null Why the image is rejected, null to keep it
     */
    public function rejectReason(string $bytes): ?string
    {
        if (empty($this->config['verify'])) {
            return null;
        }

        try {

            // The default (long) timeout fits: this runs after the response or in the panel's test, never while a
            // listener waits, and a vision answer from a local model can take a while
            $text = Providers::textModel($this->pawtunes);

            // Without AI Assistant there is no checker: the prompt rules alone apply (shown as a panel warning)
            return ($text !== null) ? ImageCheck::rejectReason($text, $bytes) : null;

        } catch (Throwable $e) {

            // Fail closed: an image that could not be checked is not used
            return "the check failed ({$e->getMessage()})";

        }
    }


    /**
     * Safeguards 7 and 8: square crop at the configured artwork size, re-encoded as JPEG into DIRECTORY
     *
     * @param  string  $name
     * @param  string  $bytes
     *
     * @return string
     * @throws PawException
     */
    public function store(string $name, string $bytes): string
    {
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new PawException('the image could not be decoded');
        }

        $size   = (int) ($this->pawtunes->config('images_size') ?: 280);
        $side   = min(imagesx($source), imagesy($source));
        $target = imagecreatetruecolor($size, $size);

        imagecopyresampled(
            $target, $source, 0, 0,
            (int) ((imagesx($source) - $side) / 2), (int) ((imagesy($source) - $side) / 2),
            $size, $size, $side, $side
        );

        $file = self::DIRECTORY."/{$name}.jpg";
        $temp = "{$file}.".uniqid('', true).'.tmp';

        $written = imagejpeg($target, $temp, 88);
        imagedestroy($source);
        imagedestroy($target);

        if ( ! $written || ! @rename($temp, $file)) {
            @unlink($temp);
            throw new PawException('the image could not be saved, is '.self::DIRECTORY.' writable?');
        }

        return $file;
    }


    /**
     * Today's usage of the daily budget
     *
     * @return array ['date' => 'Y-m-d', 'count' => int (today), 'total' => int (all time)]
     */
    public static function usage(): array
    {
        return (new DailyBudget(self::LEDGER))->usage();
    }


    /**
     * Safeguard 3: take one image from today's budget
     *
     * @return bool False when the budget is used up, or when the ledger cannot be read and written
     */
    public function reserveBudget(): bool
    {
        return (new DailyBudget(self::LEDGER))->take((int) ($this->config['daily_limit'] ?? self::DEFAULT_DAILY_LIMIT));
    }


    /**
     * Safeguard 4: one generation per artist at a time
     *
     * @param  string  $name
     *
     * @return string|null Lock file path, null when another request holds it
     */
    private function lock(string $name): ?string
    {
        if ( ! $this->ensureDirectory()) {
            $this->log('cannot create '.self::DIRECTORY.', no artwork is made until data/ is writable');

            return null;
        }

        $lock = self::DIRECTORY."/.{$name}.lock";

        // A crashed request left its lock behind, take it over
        if (is_file($lock) && filemtime($lock) < time() - self::LOCK_TIMEOUT) {
            @unlink($lock);
        }

        // Mode "x" creates the file or fails if it exists, atomically
        $handle = @fopen($lock, 'x');
        if ($handle === false) {
            $this->debug("another request is already creating artwork for \"{$name}\", skipped");

            return null;
        }

        fclose($handle);

        return $lock;
    }


    private function ensureDirectory(): bool
    {
        return is_dir(self::DIRECTORY) || @mkdir(self::DIRECTORY, 0755, true) || is_dir(self::DIRECTORY);
    }


    private function log(string $message, ?Throwable $e = null): void
    {
        Log::error($this->pawtunes, 'AI Artwork', $message, $e);
    }


    private function debug(string $message): void
    {
        Log::debug($this->pawtunes, 'AI Artwork', $message);
    }

}
