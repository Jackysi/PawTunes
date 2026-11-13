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

use lib\PawException;
use lib\PawTunes;

/**
 * Last.fm artwork source.
 *
 * Last.fm stopped serving artist photos in 2019, artist.getInfo only returns a grey star placeholder.
 * Album covers are still served, so this uses the track's album cover, or in Artist Images Mode
 * the cover of the artist's most popular album.
 */
class LastFM extends Artwork
{

    private const API_URL = 'https://ws.audioscrobbler.com/2.0/?format=json&autocorrect=1&api_key=%s&%s';

    /**
     * File name of the grey star image Last.fm returns instead of real artwork
     */
    private const PLACEHOLDER = '2a96cbd8b46e442fc41c2b86b821562f';

    /**
     * Last.fm error code for "artist/track not found", a normal miss and not worth logging
     */
    private const ERROR_NOT_FOUND = 6;

    /**
     * @var string
     */
    private $apiKey;


    /**
     * @param  PawTunes  $pawtunes
     * @param  null  $override
     */
    public function __construct(PawTunes $pawtunes, $override = null)
    {
        // Parent constructor (parses settings)
        parent::__construct($pawtunes, $override);

        // Required!
        if (empty($this->pawtunes->config('artwork_sources')['lastfm']['api_key'])) {
            throw new PawException('Missing API key for LastFM artwork API! Please get one from https://www.last.fm/api/account/create.');
        }

        $this->apiKey = $this->pawtunes->config('artwork_sources')['lastfm']['api_key'];
    }


    /**
     * @param  string  $artist
     * @param  string|null  $title
     *
     * @return string|null
     */
    protected function getArtworkURL($artist, $title = '')
    {
        if ( ! empty($title)) {

            $data   = $this->request(['method' => 'track.getInfo', 'artist' => $artist, 'track' => $title]);
            $found  = $data['track']['artist']['name'] ?? '';
            $images = $data['track']['album']['image'] ?? [];

        } else {

            $data = $this->request(['method' => 'artist.getTopAlbums', 'artist' => $artist, 'limit' => 1]);

            // Converted from XML: a single album may arrive as an object instead of a one-item list
            $albums = $data['topalbums']['album'] ?? [];
            $album  = isset($albums['name']) ? $albums : ($albums[0] ?? []);

            $found  = $album['artist']['name'] ?? '';
            $images = $album['image'] ?? [];

        }

        // Autocorrect may land on a different artist, skip those like the other sources do
        if ( ! is_array($images) || $images === [] || ! $this->isSameArtist((string) $artist, (string) $found)) {
            return null;
        }

        // Sizes ascend (small, medium, large, extralarge), so take the largest real image
        foreach (array_reverse($images) as $image) {
            $url = $image['#text'] ?? '';
            if ($url !== '' && strpos($url, self::PLACEHOLDER) === false) {
                return $url;
            }
        }

        return null;
    }


    /**
     * @param  array  $params  API method and its arguments
     *
     * @return array Decoded response, empty on transport errors and misses
     */
    private function request(array $params): array
    {
        $response = $this->pawtunes->get(
            sprintf(self::API_URL, rawurlencode($this->apiKey), http_build_query($params, '', '&', PHP_QUERY_RFC3986)),
            null,
            null,
            false,
            10
        );

        $data = json_decode((string) $response, true);
        if ( ! is_array($data)) {
            return [];
        }

        // Bad key, rate limit, suspended app... (no artist in the message: untrusted text, logs are viewable in the panel)
        if (isset($data['error'])) {

            if ((int) $data['error'] === self::ERROR_NOT_FOUND) {
                return [];
            }

            throw new PawException('LastFM artwork search failed: '.($data['message'] ?? "error {$data['error']}"));

        }

        return $data;
    }

}
