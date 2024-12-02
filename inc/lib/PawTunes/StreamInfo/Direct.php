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

    class Direct extends TrackInfo {

        /**
         * Make sure extensions are loaded and stream URL is set
         * Then get info, parse it and return
         *
         * @throws \lib\PawException
         */
        public function getInfo() {

            $this->requireURLSet()
                 ->requireURLFopen();

            $get_info = $this->readStream( $this->channel[ 'stats' ][ 'url' ] );

            if ( ( empty( $get_info ) ) && !empty( $this->channel[ 'fallback' ] ) ) {
                $get_info = $this->readStream( $this->channel[ 'fallback' ] );
            }

            if ( empty( $get_info ) ) {
                throw new PawException( " Connection to remote stream {$this->channel['stats']['url']} failed!" );
            }

            return $this->handleTrack( $get_info );

        }


        /**
         * @return $this
         * @throws \lib\PawException
         */
        private function requireURLFopen() {

            if ( !ini_get( 'allow_url_fopen' ) ) {
                throw new PawException( "Unable to connect to the stream because required PHP option \"allow_url_fopen\" is disabled!" );
            }

            return $this;

        }


        /**
         * Open stream and read its content to parse current playing track
         *
         * @param $url
         *
         * @return bool|string
         */
        private function readStream( $url ) {

            $result = false;
            $icy_metaint = false;
            $stream_context = stream_context_create(
                [
                    'http' => [
                        'method'        => 'GET',
                        'header'        => 'Icy-MetaData: 1',
                        'user_agent'    => 'Mozilla/5.0 (PawTunes) AppleWebKit/537.36 (KHTML, like Gecko)',
                        'timeout'       => 15,
                        'ignore_errors' => true,
                        'cafile'        => __DIR__ . '/bundle.crt',
                    ],
                ]
            );

            // Attempt to open stream, read it and close connection (all here)
            if ( $stream = @fopen( $url, 'r', false, $stream_context ) ) {

                // Find refresh time and/or check if this is OGG codec
                if ( ( $meta_data = stream_get_meta_data( $stream ) ) && isset( $meta_data[ 'wrapper_data' ] ) ) {

                    // Loop headers searching something to indicate codec
                    foreach ( $meta_data[ 'wrapper_data' ] as $header ) {

                        // Expected something like: string(17) "icy-metaint:16000" for MP3
                        if ( stripos( $header, 'icy-metaint' ) !== false ) {

                            $tmp = explode( ":", $header );
                            $icy_metaint = trim( $tmp[ 1 ] ); // Should be interval value
                            break;

                        }

                        // OGG Codec (start is 0)
                        if ( $header === 'Content-Type: application/ogg' ) {

                            $icy_metaint = 0;

                        }

                    }
                }

                // Stream returned metadata refresh time, use it to get streamTitle info.
                if ( $icy_metaint !== false && is_numeric( $icy_metaint ) ) {

                    $buffer = stream_get_contents( $stream, 600, $icy_metaint );

                    // Attempt to find string "StreamTitle" in stream with length of 600 bytes and $icy_metaint is offset where to start
                    if ( strpos( $buffer, 'StreamTitle=' ) !== false ) {

                        $title = explode( 'StreamTitle=', $buffer );
                        $title = trim( $title[ 1 ] );

                        // Use regex to match 'Song name - Title'; from StreamTitle='format'; (use "U" for ungreedy matching of .*)
                        if ( preg_match( "/^'(.*)';/U", $title, $m ) ) {
                            $result = $m[ 1 ];
                        }

                        // Icecast method ( only works if stream title / artist are on beginning )
                    } else if ( strpos( $buffer, 'TITLE=' ) !== false && strpos( $buffer, 'ARTIST=' ) !== false ) {

                        // This is not the best solution, it doesn't parse binary it just removes control characters after regex
                        preg_match( '/TITLE=(?P<title>.*)ARTIST=(?P<artist>.*)ENCODEDBY/s', $buffer, $m );

                        // Remove control characters like '\u10'...
                        $result = preg_replace( '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $m[ 'artist' ] . ' - ' . $m[ 'title' ] );

                    }

                }

                fclose( $stream );

            }

            // Handle information gathered so far
            return ( !$stream ? false : $result );

        }

    }