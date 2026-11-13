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

/**
 * Deezer public catalog: no API key, strong coverage of independent and electronic labels that iTunes and
 * Spotify searches often miss. Album covers for "artist - title" searches, artist pictures in artist mode.
 */
class Deezer extends Artwork
{

    private const SEARCH_TRACK = 'https://api.deezer.com/search?q={{$query}}&limit=10';

    private const SEARCH_ARTIST = 'https://api.deezer.com/search/artist?q={{$query}}&limit=10';


    /**
     * @param  string  $artist
     * @param  string|null  $title
     *
     * @return string|null
     * @throws PawException When Deezer answers with an error (quota), so the miss is not cached
     */
    protected function getArtworkURL($artist, $title = '')
    {
        $artistOnly = ($title === null || $title === '');

        // A plain query: the documented artist:"..." track:"..." filters return nothing. isSameArtist() below keeps
        // "Hello" by Adele out of a search for the band Hello instead.
        $query = rawurlencode($artistOnly ? $artist : "{$artist} {$title}");

        $search = $this->pawtunes->get(
            $this->pawtunes->template($artistOnly ? self::SEARCH_ARTIST : self::SEARCH_TRACK, ['query' => $query], false),
            null,
            null,
            false,
            5
        );

        $data = json_decode((string) $search, true);

        // Deezer reports errors (quota: 50 requests per 5 seconds) with HTTP 200. Throwing keeps the base class from
        // caching a "not found" for a day, getArtwork() logs it and moves on to the next source.
        if (isset($data['error'])) {
            throw new PawException('Deezer: '.($data['error']['message'] ?? 'unknown error'));
        }

        // 500px covers the usual artwork sizes, the 1000px variant only when asked for more
        $size = ($this->imageWidth > 500) ? 'xl' : 'big';

        foreach ($data['data'] ?? [] as $result) {

            $name = (string) ($artistOnly ? ($result['name'] ?? '') : ($result['artist']['name'] ?? ''));
            $url  = (string) ($artistOnly ? ($result["picture_{$size}"] ?? '') : ($result['album']["cover_{$size}"] ?? ''));

            // Artists without a picture get a generic silhouette, its URL has an empty hash: ".../artist//500x500-..."
            if ($url === '' || strpos($url, '//', 8) !== false || ! $this->isSameArtist((string) $artist, $name)) {
                continue;
            }

            return $url;

        }

        return null;
    }

}
