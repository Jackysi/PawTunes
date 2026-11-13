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

class iTunes extends Artwork
{

    /**
     * Ten results so a song by the right artist can be picked when the top hit is someone else's song
     * with the same name (the parameter is "limit", the old "results" was ignored and returned 50)
     *
     * @var string
     */
    private $url = "https://itunes.apple.com/search?term={{\$rawTrack}}&format=json&media=music&entity={{\$entity}}&limit=10";


    /**
     * @param  string  $artist
     * @param  string|null  $title
     *
     * @return false|string
     */
    protected function getArtworkURL($artist, $title = '')
    {
        $entity = 'song';
        $track  = rawurlencode($artist);

        // Also support searching full song names, if title is provided
        if ( ! empty($title)) {
            $track .= "+".rawurlencode($title);
        }

        $search = $this->pawtunes->get(
            $this->pawtunes->template(
                $this->url,
                [
                    'rawTrack' => $track,
                    'entity'   => $entity,
                ],
                false
            )
        );

        $data = json_decode((string) $search, true);

        foreach ($data['results'] ?? [] as $result) {

            // Skip covers of other artists ("Hello" by Adele when the band Hello is playing)
            if (empty($result['artworkUrl100']) || ! $this->isSameArtist((string) $artist, (string) ($result['artistName'] ?? ''))) {
                continue;
            }

            // .../100x100bb.jpg -> .../{W}x{H}.jpg, the CDN renders any size
            $lastSlash = strrpos($result['artworkUrl100'], '/');

            return substr($result['artworkUrl100'], 0, $lastSlash).
                   "/{$this->imageWidth}x{$this->imageHeight}.".$this->pawtunes->extGet($result['artworkUrl100']);

        }

        return null;

    }

}