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

    namespace API;

    use lib\PawException;

    class Debug extends Base {

        protected string $sseName = 'debug';


        public function readLog() {

            $logFile = 'data/logs/player_errors.log';
            header( "Content-type: text/plain" );

            if ( is_file( $logFile ) ) {

                header( 'Content-Length: ' . filesize( $logFile ) );
                echo file_get_contents( $logFile );

            }

            exit;

        }


        public function deleteLog() {

            $this->sendJSON( [ 'success' => @unlink( 'data/logs/player_errors.log' ) ] );

        }


        public function __invoke() {

            header( 'Content-Type: text/event-stream' );
            flush();

            $_GET[ 'test' ] = $_GET[ 'test' ] ?? "";
            switch ( $_GET[ 'test' ] ) {

                // Test port 8000 (Icecast/Shoutcast)
                case 'ports':
                    $test = [ 'Shoutcast & Icecast' => 'http://defikon.com:8000/status.xsl' ];
                    break;

                // Centovacast test
                case 'centovacast':
                    $test = [ 'Centovacast' => 'http://sc2.streamingpulse.com:2199/' ];
                    break;

                // Test the update center SSL (TLS + SNI)
                case 'ssl_check':
                    $test = [ 'Update Center' => 'https://prahec.com/api/test', 'iTunes SSL Check' => 'https://is2-ssl.mzstatic.com/' ];
                    break;

                // Test connection for all configured channels
                case 'user':
                    foreach ( $this->pawtunes->getChannels() as $channel ) {
                        switch ( $channel[ 'stats' ][ 'method' ] ) {

                            case 'disabled':
                            case 'sam':
                                continue 2;

                            case 'shoutcast-public':
                                $test[ $channel[ 'name' ] ] = $channel[ 'stats' ][ 'url' ] . '/7.html';
                                break;

                            default:
                                $test[ $channel[ 'name' ] ] = $channel[ 'stats' ][ 'url' ];
                                break;
                        }
                    }
                    break;

                default:
                    $test = [
                        'Shoutcast & Icecast' => 'http://defikon.com:8000/status.xsl',
                        'Centovacast'         => 'http://uk1.streamingpulse.com:2199/',
                        'Prahec API'          => 'https://prahec.com',
                    ];
                    break;

            }

            // Now do the testing
            if ( isset( $test ) && is_array( $test ) && count( $test ) >= 1 ) {

                // LOOP
                foreach ( $test as $name => $url ) {

                    // Issue.
                    if ( !$url ) {
                        continue;
                    }

                    $this->sendSSE( "<br>Connecting to {$url} ({$name})..." );

                    // Determine scheme
                    $url_parts = parse_url( $url );
                    if ( $url_parts[ 'scheme' ] === 'wss' ) {

                        $this->sendSSE( '<b><span class="text-warning">Sorry, web sockets are not supported in the debugger at this time.</span></b>' );
                        continue;

                    }

                    $verbose = ( $this->pawtunes->config( 'debugging' ) === 'enabled' ) ? fopen( 'php://temp', 'wb+' ) : null;
                    $test = $this->pawtunes->get(
                        $url,
                        null,
                        null,
                        function( $total, $downloaded ) {
                            return ( $downloaded > 500 ) ? 1 : 0;
                        },
                        15,
                        $curl_error,
                        [
                            CURLOPT_RANGE   => '0-500',
                            CURLOPT_VERBOSE => $this->pawtunes->config( 'debugging' ) === 'enabled',
                            CURLOPT_STDERR  => $verbose,
                        ],
                        false
                    );

                    if ( $test !== false || $curl_error === 'Callback aborted' ) {

                        $this->sendSSE( '<b><span class="text-success">Connection successfully established!</span></b>' );

                    } else {

                        $curl_error = str_replace( "'", '&apos;', $curl_error );
                        $this->sendSSE( '<b><span class="text-danger">' . ( ( !empty( $curl_error ) ) ? $curl_error : 'Connection failed!' ) . '</span></b>' );

                    }

                    if ( $this->pawtunes->config( 'debugging' ) === 'enabled' ) {

                        $this->sendSSE( '<br><b>CURL VERBOSE LOG</b> ( ' . $url . ' )<br>' . str_repeat( '*', 125 ) );

                        rewind( $verbose ); ## Rewind
                        while ( !feof( $verbose ) ) { ## Read

                            $msg = str_replace( [ "\n", "\r", "'" ], [ '', '', "\\'" ], fgets( $verbose, 2048 ) );
                            $this->sendSSE( $msg );

                        }

                        $this->sendSSE( str_repeat( '*', 125 ) );
                        fclose( $verbose );

                    }

                }

            }

            $this->closeSSE();

        }


        public function handleError( $e ) {

            $this->sendSSE( '<b><span class="text-danger">FATAL ERROR: ' . $e->getMessage() . '</span></b>' );
            $this->closeSSE();

        }

    }