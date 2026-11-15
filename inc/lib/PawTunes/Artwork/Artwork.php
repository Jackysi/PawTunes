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

namespace lib\PawTunes\Artwork;

use lib\Helpers;
use lib\Image\Image;
use lib\PawTunes;

abstract class Artwork
{

    /**
     * Search results are stable (CDN URLs rarely change), a week covers a station's rotation cycle
     */
    protected const SEARCH_FOUND_TTL = 7 * 86400;

    /**
     * Short enough that artwork added to the provider's catalog shows up the next day
     */
    protected const SEARCH_NOT_FOUND_TTL = 86400;

    /**
     * Cached marker for "the API answered, but had no artwork" (cache misses return false, so it cannot be null)
     */
    protected const SEARCH_NOT_FOUND = 'not-found';

    /**
     * @var string
     */
    public $path = './data/images';

    /**
     * Path to store cropped and optimized images
     *
     * @var string
     */
    public $cachePath = './data/cache';

    /**
     * List of allowed extensions
     *
     * @var string[]
     */
    public $extensions = ['jpg', 'jpeg', 'png', 'svg', 'webp', 'avif'];

    /**
     * @var integer
     */
    protected $imageWidth;

    /**
     * @var integer
     */
    protected $imageHeight;

    /**
     * @var boolean
     */
    protected $cacheImage = true;

    /**
     * Cache API search results (hits and confirmed misses)
     *
     * @var boolean
     */
    protected $cacheRequests = false;

    /**
     * Strip release noise ("(Radio Edit)", "feat. Guest") from searches, see Helpers::cleanSearchTerm()
     *
     * @var boolean
     */
    protected $cleanSearch = true;

    /**
     * Set by a provider whose API says "no artwork" with a non-2xx status (fanart.tv answers 404 for an artist
     * without art): the miss is then cached like a 2xx one. Reset before every search.
     *
     * @var boolean
     */
    protected $confirmedMiss = false;

    /**
     * @var string
     */
    protected $defaultArtist;

    /**
     * @var string
     */
    protected $defaultTrack;

    /**
     * @var string
     */
    protected $override;

    /**
     * @var
     */
    protected $pawtunes;


    /**
     * @param  PawTunes  $pawtunes
     */
    public function __construct(PawTunes $pawtunes, $override = null)
    {
        $this->imageWidth  = $pawtunes->config('images_size') ?? 360;
        $this->imageHeight = $pawtunes->config('images_size') ?? 360;

        $this->cacheImage    = $pawtunes->config('cache_images') ?? false;
        $this->cacheRequests = $pawtunes->config('cache_api_requests') ?? false;

        // On unless explicitly disabled: installs that predate the option get it without saving settings
        $this->cleanSearch = $pawtunes->config('clean_artwork_search') ?? true;
        $this->cachePath     = ( ! empty($pawtunes->config('cache')['path'])) ? $pawtunes->config('cache')['path'] : $this->cachePath;

        $this->defaultArtist = $pawtunes->config('artist_default') ?? 'Various Artists';
        $this->defaultTrack  = $pawtunes->config('title_default') ?? 'Unknown Track';

        $this->pawtunes = $pawtunes;

        // Check if we have an override provided
        $this->override = ( ! empty($override)) ? $override : null;
    }


    /**
     * @param      $artist
     * @param      $title
     * @param  bool  $skipCache
     *
     * @return string|null
     */
    public function __invoke($artist, $title = '', string $override = '', bool $skipCache = false): ?string
    {
        // Default artist/title, skip lookup
        if ($this->isDefaultTrack($artist, $title)) {
            return null;
        }

        // If we already have an image, stop here.
        $existing = $this->getExistingImage($artist, $title, $skipCache);
        if ($existing || empty($artist)) {
            return $existing;
        }

        // Now let's do magic.
        $artworkURL = ( ! empty($override)) ? $override : $this->searchArtwork($artist, $title, $skipCache);
        if ( ! $artworkURL || ! filter_var($artworkURL, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Not downloading & caching
        if ($skipCache || ! $this->cacheImage) {
            return $artworkURL;
        }

        // Download and cache, return new URL
        $fileName = $this->pawtunes->parseTrack("{$artist} - {$title}");
        if ( ! $newImage = $this->downloadArtwork($artworkURL, $fileName)) {
            return null;
        }

        return $newImage;
    }


    /**
     * Get image
     *
     * @param  string  $artist
     * @param  string  $title
     * @param  bool  $skipCache
     *
     * @return string
     */
    public function getExistingImage($artist, $title = '', bool $skipCache = false): ?string
    {
        // Figure out what we are actually searching for
        $trackArtist = $this->pawtunes->parseTrack($artist);
        $trackName   = $this->pawtunes->parseTrack("{$artist} - {$title}");

        // Empty Artist, Too short or default artist-title returned
        if (empty($trackArtist) || strlen($trackArtist) < 3) {
            return null;
        }

        // Check if we have a cached image (artist - title)
        if ($title !== '' && $trackImage = $this->getExistingArtwork($trackName)) {
            return $trackImage;
        }

        // Check if we have an artist image
        if ($artistImage = $this->getExistingArtwork($trackArtist)) {
            return $artistImage;
        }

        // Check if we have a cached artist image
        if ($skipCache === false && ($cachedArtist = $this->getCachedArtwork(($title === '') ? $trackArtist : $trackName))) {
            return $cachedArtist;
        }

        // Last check if there is an override
        if ($this->override && filter_var($this->override, FILTER_VALIDATE_URL)) {
            return $this->override;
        }

        return null;
    }


    /**
     * Run the provider search through the cache (when "Cache API Requests" is enabled).
     * Keys are "<source>_<md5>", e.g. "itunes_9e107d9d372bb6826bd81d3542a419d6".
     *
     * @param  string  $artist
     * @param  string  $title
     * @param  bool  $skipCache  Force a fresh search (panel lookup tool), the result is still cached
     *
     * @return string|null
     */
    protected function searchArtwork(string $artist, string $title, bool $skipCache = false): ?string
    {
        // Cleaned before the cache key is built, so "Song" and "Song (Radio Edit)" share one search.
        // Only the search changes: local files keep using the names from the stream.
        if ($this->cleanSearch) {
            $artist = Helpers::cleanSearchTerm($artist);
            $title  = ($title !== '') ? Helpers::cleanSearchTerm($title) : '';
        }

        // Providers are untyped and may return anything, filter_var() yields the URL or false
        if ( ! $this->cacheRequests) {
            return filter_var($this->getArtworkURL($artist, $title), FILTER_VALIDATE_URL) ?: null;
        }

        $cache = $this->pawtunes->cache;
        $http  = $this->pawtunes->http;

        // The class name doubles as the source key (iTunes -> itunes, AppleMusic -> applemusic).
        // serialize() length-prefixes each field, so ("a b", "c") and ("a", "b c") never share a key.
        // The size is part of the key because iTunes and Apple Music bake it into the returned URL.
        $source   = strtolower(substr(strrchr(static::class, '\\'), 1));
        $cacheKey = $source.'_'.md5(serialize([$artist, $title, $this->imageWidth, $this->imageHeight]));

        if ( ! $skipCache) {
            $cached = $cache->get($cacheKey);
            if ($cached === self::SEARCH_NOT_FOUND) {
                return null;
            }

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        // Start clean, so a status left by the previous source is never mistaken for this provider's answer
        $http->resetLastStatus();
        $this->confirmedMiss = false;
        $artworkURL = filter_var($this->getArtworkURL($artist, $title), FILTER_VALIDATE_URL);

        if ($artworkURL !== false) {
            $cache->set($cacheKey, $artworkURL, self::SEARCH_FOUND_TTL);

            return $artworkURL;
        }

        // Cache a miss only when the API itself answered 2xx, or the provider confirmed it. Rate limits (403/429),
        // auth errors and timeouts are our failure to ask, not "no artwork", so the next request retries.
        $status = $http->getLastStatus();
        if ($this->confirmedMiss || ($status !== null && $status >= 200 && $status < 300)) {
            $cache->set($cacheKey, self::SEARCH_NOT_FOUND, self::SEARCH_NOT_FOUND_TTL);
        }

        return null;
    }


    /**
     * Does a search result belong to the artist we searched for? Part of "Clean Searches": with the option off,
     * every result is accepted like before, which is the escape hatch should the loose match ever reject a real one.
     *
     * @param  string  $searched
     * @param  string  $found
     *
     * @return bool
     */
    protected function isSameArtist(string $searched, string $found): bool
    {
        return ! $this->cleanSearch || Helpers::sameArtist($searched, $found);
    }


    /**
     * Default class Artwork means Artwork functionality is "Disabled"
     *
     * @param $artist
     * @param $title
     *
     * @return null
     */
    protected function getArtworkURL(string $artist, ?string $title = '')
    {
        return null;
    }


    /**
     * @param $url
     * @param $imageName
     *
     * @return string|null
     */
    protected function downloadArtwork($url, $imageName)
    {
        $this->ignoreAbort();

        // Headers list
        $imageType = null;

        // URL Is validated, now download image and check it's headers
        $img = $this->pawtunes->get(
            $url,
            null,
            null,
            false,
            0,
            $err,
            [
                CURLOPT_HEADERFUNCTION => function ($ch, $hLine) use (&$imageType) {

                    // Handle content type header
                    if ((stripos($hLine, 'content-type') !== false) && stripos($hLine, 'image/') !== false) {
                        $imageType = trim(str_ireplace('content-type: image/', '', $hLine));
                    }

                    return strlen($hLine);

                },
            ]
        );

        // Download failed, invalid extension or too small file
        if (strlen($img) < 1024 || $imageType === null || ! in_array($imageType, $this->extensions)) {
            return null;
        }

        // Stored as downloaded first
        $source = "{$this->cachePath}/{$imageName}.".$imageType;
        file_put_contents($source, $img);

        // "Artwork Format": every new image is stored in that one format, otherwise in the one it came in
        $format = $this->pawtunes->artworkFormat();
        $path   = ($format !== null) ? "{$this->cachePath}/{$imageName}.{$format}" : $source;

        // Crop to the configured square. A file GD cannot read stays as it was downloaded.
        $image = Image::open($source);
        if ($image === null || ! $image->crop((int) $this->imageWidth, (int) $this->imageHeight)->save($path)) {
            return $source;
        }

        // Converted: the download itself is not needed any more
        if ($path !== $source) {
            Helpers::deleteFile($source);
        }

        return $path;
    }


    /**
     * @param $artist
     * @param $title
     *
     * @return bool
     */
    protected function isDefaultTrack($artist, $title): bool
    {
        return ($artist === $this->defaultArtist && $title === $this->defaultTrack);
    }


    /**
     * @param $name
     *
     * @return string|null
     */
    protected function getExistingArtwork($name): ?string
    {
        foreach ($this->extensions as $ext) {

            if (is_file("{$this->path}/{$name}.{$ext}")) {
                return "{$this->path}/{$name}.{$ext}";
            }

        }

        return null;
    }


    /**
     * @param $name
     *
     * @return string|null
     */
    protected function getCachedArtwork($name): ?string
    {
        foreach ($this->extensions as $ext) { ## Low priority cached images from various sources

            if (is_file("{$this->cachePath}/{$name}.{$ext}")) {
                return "{$this->cachePath}/{$name}.{$ext}";
            }

        }

        return null;
    }


    /**
     * Set PHP timeout to 0 and ignore user abort
     *
     * @return void
     */
    protected function ignoreAbort()
    {
        ignore_user_abort(true);
        set_time_limit(0);
    }

}