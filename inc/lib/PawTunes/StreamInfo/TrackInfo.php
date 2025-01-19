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
use lib\PawTunes;

abstract class TrackInfo implements TrackInfoInterface {

    protected $pawtunes;

    protected $channel;


    public function __construct( PawTunes $pawtunes, $channel ) {

        $this->pawtunes = $pawtunes;
        $this->channel = $channel;

    }


    /**
     * Check if stats URL is set, used in most methods
     *
     * @return $this
     * @throws \lib\PawException
     */
    protected function requireURLSet() {

        if ( empty( $this->channel[ 'stats' ][ 'url' ] ) ) {
            throw new PawException( 'Direct track information method require a valid stream URL!' );
        }

        return $this;

    }


    /**
     * Check if CURL Extension is loaded, used in conjunction with get function
     *
     * @throws \lib\PawException
     */
    protected function requireXMLExt() {

        if ( !function_exists( 'simplexml_load_string' ) ) {
            throw new PawException( "SimpleXML extension is not loaded!" );
        }

        return $this;

    }


    /**
     * Check if CURL Extension is loaded, used in conjunction with get function
     *
     * @throws \lib\PawException
     */
    protected function requireCURLExt() {

        if ( !function_exists( 'curl_version' ) ) {
            throw new PawException( "CURL extension is not loaded!" );
        }

        return $this;

    }


    /**
     * Check if HTTP Auth is required for the method used
     *
     * @throws \lib\PawException
     */
    protected function requireAuth() {

        if ( empty( $this->channel[ 'stats' ][ 'auth-user' ] ) || empty( $this->channel[ 'stats' ][ 'auth-pass' ] ) ) {
            throw new PawException( "No authentication provided for the track info method!" );
        }

        return $this;

    }


    /**
     * Apply regex on response text to determine track info
     *
     * @param $text
     * @param $track
     *
     * @return array
     */
    protected function handleTrack( $text, $track = null ) {

        // When data not provided
        if ( empty( $track ) ) {
            preg_match( '/' . $this->pawtunes->config( 'track_regex' ) . '/', $this->pawtunes->strToUTF8( $text ), $track );
        }

        $info = [];
        $info[ 'artist' ] = ( !$track || ( empty( $track[ 'artist' ] ) ) ? $this->pawtunes->config( 'artist_default' ) : trim( $track[ 'artist' ] ) );
        $info[ 'title' ] = ( !$track || ( empty( $track[ 'title' ] ) ) ? $this->pawtunes->config( 'title_default' ) : trim( $track[ 'title' ] ) );
        $info[ 'artwork_override' ] = $track[ 'artwork' ] ?? $track[ 'image' ] ?? null;
        return $info;

    }

}