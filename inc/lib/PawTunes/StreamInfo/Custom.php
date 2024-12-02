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

    class Custom extends TrackInfo {

        /**
         * @throws \lib\PawException
         */
        public function getInfo() {

            $this->requireCURLExt()
                 ->requireURLSet();

            // Attempt connection
            $data = $this->pawtunes->get(
                $this->channel[ 'stats' ][ 'url' ],
                null,
                (
                ( !empty( $this->channel[ 'stats' ][ 'auth-user' ] ) && !empty( $this->channel[ 'stats' ][ 'auth-pass' ] ) ) ?
                    "{$this->channel['stats']['auth-user']}:{$this->channel['stats']['auth-pass']}" :
                    ''
                )
            );

            if ( !$data ) {
                throw new PawException( "Connection to the custom API URL failed!" );
            }

            if ( empty( $data ) ) {
                throw new PawException( "Connection to the \"Custom\" method was successful but the server response is empty!" );
            }

            // Attempt decoding JSON
            $track = json_decode( $this->pawtunes->strToUTF8( $data ), true );
            if ( !empty( $track ) ) {
                return $this->parseJSONResponse( $track );
            }

            // Text response
            return $this->handleTrack( $data );

        }


        /**
         * Helper function to parse JSON response
         *
         * @param $data
         *
         * @return array
         */
        private function parseJSONResponse( $data ): array {

            // Track
            $track = $this->handleTrack( null, $data );

            // History
            if ( !empty( $data[ 'history' ] ) && count( $data[ 'history' ] ) > 0 ) {

                foreach ( $data[ 'history' ] as $key => $value ) {

                    // Artist or Title not provided
                    if ( empty( $data[ 'artist' ] ) || empty( $data[ 'title' ] ) ) {

                        $data[ 'history' ][ $key ][ 'artist' ] = $this->pawtunes->config( 'artist_default' );
                        $data[ 'history' ][ $key ][ 'title' ] = $this->pawtunes->config( 'title_default' );

                    }

                    // Played At provided
                    if ( !empty( $value[ 'played_at' ] ) ) {
                        $data[ 'history' ][ $key ][ 'time' ] = $value[ 'played_at' ];
                        unset( $data[ 'history' ][ $key ][ 'played_at' ] );
                    }

                    // Artwork provided
                    if ( !empty( $value[ 'artwork' ] ) || !empty( $value[ 'image' ] ) ) {
                        $data[ 'history' ][ $key ][ 'artwork_override' ] = $value[ 'artwork' ] ?? $value[ 'image' ];
                    }

                }

                // Reverse array so it's in the right order
                $track[ 'history' ] = array_reverse( $data[ 'history' ] );

            }

            return $track;

        }

    }