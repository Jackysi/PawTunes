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

    class Shoutcast extends TrackInfo {

        /**
         * Make sure requirements are meet and then get info, parse it and return
         *
         * @throws \lib\PawException
         */
        public function getInfo() {

            $this->requireCURLExt()
                 ->requireXMLExt()
                 ->requireURLSet()
                 ->requireAuthParam();

            // Attempt getting the XML data from the Shoutcast server
            $xml = $this->pawtunes->get( "{$this->channel['stats']['url']}/admin.cgi?pass={$this->channel['stats']['auth']}&mode=viewxml&sid={$this->channel['stats']['sid']}" );
            if ( !$xml ) {
                throw new PawException( "Connection to the Shoutcast server failed!" );
            }

            return $this->parseResponse( $xml );

        }


        /**
         * @throws \lib\PawException
         */
        private function requireAuthParam() {

            if ( empty( $this->channel[ 'stats' ][ 'auth' ] ) ) {
                throw new PawException( "Unable to connect to the stream because authentication info is missing!" );
            }

            return $this;

        }


        /**
         * @throws \lib\PawException
         */
        private function parseResponse( $data ) {

            $parsed = $this->pawtunes->xml2array( $data, true );

            // Throw exception if XML parsing failed
            if ( empty( $parsed ) || !is_array( $parsed ) || !isset( $parsed[ 'songtitle' ] ) ) {
                throw new PawException( "Unable to parse Shoutcast XML response!" );
            }

            // Parse track info
            $track = $this->handleTrack( $parsed[ 'songtitle' ] );

            // Handle history
            if ( $this->channel[ 'stats' ][ 'sc-history' ] && isset( $parsed[ 'songhistory' ] ) && count( $parsed[ 'songhistory' ][ 'SONG' ] ) >= 1 ) {

                $track[ 'history' ] = [];
                foreach ( $parsed[ 'songhistory' ][ 'SONG' ] as $song ) {

                    // Parse as you would any track, but add the time it was played
                    $track[ 'history' ][] = array_merge(
                        $this->handleTrack( $song[ 'TITLE' ] ),
                        [ 'time' => ( (int) $song[ 'PLAYEDAT' ] * 1000 ) ]
                    );

                }

            }

            return $track;

        }

    }