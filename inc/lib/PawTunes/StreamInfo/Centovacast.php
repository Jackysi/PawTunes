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

namespace lib\PawTunes\StreamInfo;

use lib\PawException;

class Centovacast extends TrackInfo {

    /**
     * @throws \lib\PawException
     */
    private function requireUsername() {

        if ( empty( $this->channel[ 'stats' ][ 'user' ] ) ) {
            throw new PawException( "Unable to connect to the stream because CentovaCast username is not set!" );
        }

        return $this;

    }


    /**
     * @return array
     * @throws \lib\PawException
     */
    public function getInfo() {

        $this->requireCURLExt()
             ->requireURLSet()
             ->requireUsername();

        // Attempt to get JSON response from API
        $json = $this->pawtunes->get( "{$this->channel['stats']['url']}/external/rpc.php?m=streaminfo.get&username={$this->channel['stats']['user']}&rid={$this->channel['stats']['user']}&charset=utf8" );
        if ( !$json ) {
            throw new PawException( "Connection to the Centovacast RPC API failed!" );
        }

        // Can we decode it?
        $parsed = json_decode( $json, true );
        if ( !$parsed ) {
            throw new PawException( "Unable to parse Centovacast RPC API response!" );
        }

        // Was error returned?
        if ( !empty( $parsed[ 'error' ] ) ) {
            throw new PawException( "Centovacast RPC API returned ERROR: {$parsed['error']}" );
        }

        return $this->centovaHandleTrack( $parsed[ 'data' ][ 0 ][ 'track' ] );

    }


    /**
     * Replacement for Centova cast because method is slightly different here
     *
     * @param $track
     *
     * @return array
     */
    private function centovaHandleTrack( $track ) {

        $info = $this->handleTrack( null, $track );
        $info[ 'artwork_override' ] = ( !empty( $track[ 'imageurl' ] ) && $this->channel[ 'stats' ][ 'use-cover' ] ? $track[ 'imageurl' ] : null );

        return $info;

    }

}