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

use lib\PawException;
use lib\PawTunes;
use Throwable;

/**
 * AI Artwork: abstract artwork drawn by an image model for artists no artwork source knows.
 *
 * Safeguards, in the order they apply:
 *  1. Played only: an artist qualifies only after handler.php saw it as "now playing" on the station's own stream
 *     (markPlayed). The public "?artwork&artist=..." URL alone can never start a paid generation.
 *  2. One attempt per artist per day, and at most one generation per request.
 *  3. Daily budget: a file ledger under an exclusive lock, outside the cache, so a cache flush or a dead cache
 *     backend cannot reset or bypass it. If the ledger cannot be read or written, nothing is generated.
 *  4. One generation per artist at a time (atomic lock file), however many listeners ask at once.
 *  5. No fake covers: the prompt comes from fixed word lists (ArtworkPrompt), never artist or title, and it
 *     forbids text, people and cover layouts.
 *  6. Vision check (on by default): see ImageCheck. Rejected images are thrown away, the budget stays spent.
 *  7. Re-encoded through GD: whatever the provider sent, only plain pixels are stored (no metadata or payloads).
 *  8. Kept out of the cache path and out of data/images: Flush Cache and updates cannot wipe them (no paying
 *     twice), and they are checked only after every real source, so a real cover always wins.
 *
 * Generation runs after the response was sent (PawTunes::afterResponse): the first listener sees the default or
 * generated artwork, later plays show the AI artwork.
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
     *
     * @return void
     */
    public static function markPlayed(PawTunes $pawtunes, string $artist): void
    {
        $name = $pawtunes->parseTrack($artist);
        if ( ! self::enabled($pawtunes) || $name === '' || $artist === $pawtunes->config('artist_default')) {
            return;
        }

        // add(), not set(): this runs on every track info refresh while the artist plays, and an existing entry
        // needs no rewrite. The TTL counts from the first refresh that saw the artist, which is when its artwork
        // is first requested (and queued); once it expires, the next play adds a fresh entry.
        $pawtunes->cache->add('ai.played.'.md5($name), 1, self::PLAYED_TTL);
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
        if ( ! $pawtunes->cache->get('ai.played.'.md5($name))) {
            return;
        }

        // Safeguard 2: one attempt per artist per day. add() is atomic, so of several listeners asking for a new
        // artist at the same moment only one gets to queue it. False also when the cache cannot record the attempt:
        // then no attempt is made either.
        if ( ! $pawtunes->cache->add('ai.attempt.'.md5($name), time(), self::RETRY_AFTER)) {
            return;
        }

        self::$queued = true;
        $pawtunes->afterResponse(static function () use ($pawtunes, $artist, $name) {
            (new self($pawtunes))->generate($artist, $name);
        });
    }


    /**
     * The whole pipeline for one artist, logs instead of throwing (runs after the response)
     *
     * @param  string  $artist  As the stream sent it (only used to pick a mood, never in the image prompt)
     * @param  string  $name  Normalized artist name, also the file name
     *
     * @return string|null Path of the stored artwork
     */
    public function generate(string $artist, string $name): ?string
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
                $this->log("daily limit reached, \"{$name}\" keeps its current artwork");

                return null;
            }

            [$bytes, $prompt] = $this->draw($model, $artist, $name);

            $reason = $this->rejectReason($bytes);
            if ($reason !== null) {
                $this->log("discarded the image for \"{$name}\": {$reason}");

                return null;
            }

            return $this->store($name, $bytes);

        } catch (Throwable $e) {

            $this->log("could not create artwork for \"{$name}\": {$e->getMessage()}");

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
     * Prompt and image for an artist (also used by the panel's test)
     *
     * @param  ImageModel  $model
     * @param  string  $artist
     * @param  string  $name
     *
     * @return array [image bytes, prompt]
     * @throws PawException
     */
    public function draw(ImageModel $model, string $artist, string $name): array
    {
        // With AI Assistant on, the mood fits the artist's music (one word out of a fixed list), seeded otherwise
        $mood = null;
        try {
            $text = Providers::textModel($this->pawtunes);
            $mood = ($text !== null) ? (new Assistant($text))->artworkMood($artist) : null;
        } catch (Throwable $e) {
            $this->log("mood lookup failed, using a seeded mood: {$e->getMessage()}");
        }

        $prompt = ArtworkPrompt::build($name, $mood);
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
            return null;
        }

        fclose($handle);

        return $lock;
    }


    private function ensureDirectory(): bool
    {
        return is_dir(self::DIRECTORY) || @mkdir(self::DIRECTORY, 0755, true) || is_dir(self::DIRECTORY);
    }


    private function log(string $message): void
    {
        $this->pawtunes->writeLog('player_errors', "AI Artwork: {$message}");
    }

}
