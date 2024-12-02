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

    /**
     * Hint IDE already defined variables from parent (this file is included)
     *
     * @var \lib\PawTunes $pawtunes
     * @var array         $templates
     * @var lib\Cache     $cache
     */

    use lib\PawException;

    $channels = [];
    if ( is_file( 'inc/config/channels.php' ) ) {
        $channels = include( 'inc/config/channels.php' );
    }

    // Start few functions and init objects
    header( "Content-Type: application/json; charset=utf-8" );

    // Important so full images are downloaded!
    ignore_user_abort( true );

    /* Initial player run, get all channels in nice json object
    ============================================================================================== */
    if ( isset( $_GET[ 'channel' ] ) && $_GET[ 'channel' ] === 'all' ) {

        // If count of channels is more than 1
        if ( count( $channels ) >= 1 ) {

            $out = [];

            // Recreate channel options array for best practice and security
            foreach ( $channels as $chn_key => $chn ) {

                // Check if skin exists
                if ( empty( $chn[ 'skin' ] ) || !is_file( "{$templates[$pawtunes->config('template')]['path']}/{$chn[ 'skin' ]}" ) ) {

                    // Better set default too
                    $chn[ 'skin' ] = $templates[ $pawtunes->config( 'template' ) ][ 'schemes' ][ 0 ][ 'style' ];

                }

                // Check if we need to use websocket
                if ( $chn[ 'stats' ][ 'method' ] === 'azuracast' && !empty( $chn[ 'stats' ][ 'url' ] ) ) {

                    $statsURL = parse_url( $chn[ 'stats' ][ 'url' ] );
                    $chn[ 'websocket' ][ 'method' ] = 'azuracast';
                    $chn[ 'websocket' ][ 'station' ] = $chn[ 'stats' ][ 'station' ];
                    $chn[ 'websocket' ][ 'url' ] = ( $statsURL[ 'scheme' ] === 'wss' ) ? $chn[ 'stats' ][ 'url' ] : false;
                    $chn[ 'websocket' ][ 'history' ] = $chn[ 'stats' ][ 'azura-history' ];
                    $chn[ 'websocket' ][ 'useRemoteCovers' ] = $chn[ 'stats' ][ 'use-cover' ];

                }

                $out[ $chn_key ] = [
                    'name'      => $chn[ 'name' ],
                    'logo'      => $chn[ 'logo' ],
                    'websocket' => $chn[ 'websocket' ] ?? null,
                    'skin'      => "{$templates[$pawtunes->config('template')]['path']}/{$chn[ 'skin' ]}",
                    'streams'   => $chn[ 'streams' ],
                ];

            }

            // Output data into JSON array (or JSONP)
            $json_data = json_encode( $out );
            exit( ( !empty( $_GET[ 'callback' ] ) && $pawtunes->config( 'api' ) === true ) ? "{$_GET['callback']}({$json_data});" : $json_data );

        }

        // No channels defined, return empty json
        $pawtunes->exitJSON();

    }

    // Search for our channel in config
    $chn_key = 0;
    foreach ( $channels as $key => $search ) {
        if ( $search[ 'name' ] === $_GET[ 'channel' ] ) {
            $chn_key = $key;
            break;
        }
    }

    ## Make sure this channel really exists
    if ( !isset( $channels[ $chn_key ] ) || !is_array( $channels[ $chn_key ] ) ) {
        die( json_encode( [] ) );
    }

    // Set few vars before attempting fate :)
    $channel = $channels[ $chn_key ];

    // Cache related info
    $info[ 'cache' ][ 'status' ] = false;
    $info[ 'cache' ][ 'date' ] = date( 'Y-m-d H:i:s' );
    $info[ 'cache' ][ 'refresh' ] = ( ( $pawtunes->config( 'stats_refresh' ) - 1 ) <= 1 ) ? 10 : ( $pawtunes->config( 'stats_refresh' ) - 1 );

    /* Now do the heavy work, use configured method to get stats information
    ============================================================================================== */
    switch ( $channel[ 'stats' ][ 'method' ] ) {

        case 'azuracast':
        case 'centovacast':
        case 'custom':
        case 'direct':
        case 'icecast':
        case 'sam':
        case 'shoutcast':
        case 'shoutcast-public':

            // Check Cache first
            if ( $cached = $pawtunes->cache->get( 'stream.info.' . $chn_key ) ) {

                $info = $cached;
                $info[ 'cache' ][ 'status' ] = true;
                break;

            }

            // Guess className required for specific method
            $className = "\\lib\\PawTunes\\StreamInfo\\" . str_replace( '-', '', ucwords( $channel[ 'stats' ][ 'method' ], '-' ) );

            try {

                // Initiate class and get data
                $class = new $className( $pawtunes, $channel );
                $info += $class->getInfo();

                // Artwork in lazy mode?
                if ( $pawtunes->config( 'artwork_lazy_loading' ) !== true ) {

                    $info[ 'artwork' ] = $pawtunes->getArtwork(
                        $info[ 'artist' ],
                        ( !$pawtunes->config( 'artist_images_only' ) ) ? $info[ 'title' ] : null,
                        $info[ 'artwork_override' ] ?? ''
                    );

                } else {

                    $override = ( !empty( $info[ 'artwork_override' ] ) ) ? '&override=' . base64_encode( $info[ 'artwork_override' ] ) : '';
                    $info[ 'artwork' ] = "./index.php?artwork&artist={$info[ 'artist' ]}&title={$info[ 'title' ]}{$override}";

                }

                // Handle history lazy & normal loading
                if ( isset( $info[ 'history' ] ) && count( $info[ 'history' ] ) > 0 ) {
                    foreach ( $info[ 'history' ] as $key => $track ) {

                        if ( $pawtunes->config( 'artwork_lazy_loading' ) !== true ) {// Slow

                            $info[ 'history' ][ $key ][ 'artwork' ] = $pawtunes->getArtwork(
                                $info[ 'history' ][ $key ][ 'artist' ],
                                ( !$pawtunes->config( 'artist_images_only' ) ) ? $info[ 'history' ][ $key ][ 'title' ] : null,
                                $info[ 'history' ][ $key ][ 'artwork_override' ] ?? ''
                            );

                        } else { // Fast

                            $override = ( !empty( $track[ 'artwork_override' ] ) ) ? '&override=' . base64_encode( $track[ 'artwork_override' ] ) : '';
                            $info[ 'history' ][ $key ][ 'artwork' ] = "./index.php?artwork&artist={$track[ 'artist' ]}&title={$track[ 'title' ]}{$override}";

                        }

                        // Delete useless var
                        unset( $info[ 'history' ][ $key ][ 'artwork_override' ] );

                    }
                }


                // Delete useless var
                unset( $info[ 'artwork_override' ] );

                // Set cache
                $pawtunes->cache->set( 'stream.info.' . $chn_key, $info, $info[ 'cache' ][ 'refresh' ] );

                // Cache for channels list and w/e else
                $pawtunes->cache->set( 'stream.info.historic.' . $chn_key, $info, 0 );

            } catch ( PawException $e ) {

                // Catch errors, incl. PHP ones
                $pawtunes->writeLog( 'player_errors', "{$channel['name']}: {$e->getMessage()}" );

                // Extra trace info if debug is enabled
                if ( $pawtunes->config( 'debugging' ) === 'enabled' ) {
                    $pawtunes->writeLog( 'player_errors', "{$channel['name']}: {$e->getTraceAsString()}" );
                }

            }
            break;


        /* Disabled, simply return defaults
        ============================================================================================== */
        default:

            // Disabled or ERROR occurred
            $info = [
                'artist' => $pawtunes->config( 'artist_default' ),
                'title'  => $pawtunes->config( 'title_default' ),
                'image'  => $pawtunes->getArtwork( null ),
                'status' => 'disabled',
            ];

            // Log if not disabled
            if ( $channel[ 'stats' ][ 'method' ] !== 'disabled' ) {
                $pawtunes->writeLog( 'errors', "{$channel['name']}: Invalid method! This is truly fancy error which should have never happened!" );
            }

    }

    $jsonData = json_encode( empty( $info ) ? [] : $info, JSON_THROW_ON_ERROR );

    // Show output (if this is JSONP request, adapt response to its requirements
    echo( ( !empty( $_GET[ 'callback' ] ) && $pawtunes->config( 'api' ) === true ) ? "{$_GET['callback']}({$jsonData});" : $jsonData );