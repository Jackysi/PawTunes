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

class iTunes extends Artwork {

    /**
     * @var string
     */
    private $url = "https://itunes.apple.com/search?term={{\$rawTrack}}&format=json&media=music&entity={{\$entity}}&results=1&start=0";


    /**
     * @param string      $artist
     * @param string|null $title
     *
     * @return false|string
     */
    protected function getArtworkURL( $artist, $title = '' ) {

        $entity = 'song';
        $track = rawurlencode( $artist );

        // Also support searching full song names, if title is provided
        if ( !empty( $title ) ) {
            $track .= "+" . rawurlencode( $title );
        }

        $search = $this->pawtunes->get( $this->pawtunes->template( $this->url, [ 'rawTrack' => $track, 'entity' => $entity ], false ) );

        // If there is an response
        if ( $search !== false ) {

            // Read JSON String
            $data = json_decode( $search, true );

            // Reading JSON
            if ( !empty( $data[ 'resultCount' ] ) && $data[ 'resultCount' ] >= 1 ) { // Check if result is not empty

                // Find position of LAST slash (/)
                $last_slash = strrpos( $data[ 'results' ][ 0 ][ 'artworkUrl100' ], '/' );

                // Return the modified string
                return substr( $data[ 'results' ][ 0 ][ 'artworkUrl100' ], 0, $last_slash ) .
                       "/{$this->imageWidth}x{$this->imageHeight}." . $this->pawtunes->extGet( $data[ 'results' ][ 0 ][ 'artworkUrl100' ] );

            }

        }

        return false;

    }

}