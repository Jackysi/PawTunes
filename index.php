<?php

    /**
     * PawTunes Project - Open Source Radio Player
     *
     * @author       Jacky (Jaka Prasnikar)
     * @email        jacky@prahec.com
     * @website      https://prahec.com
     * @repository   https://github.com/Jackysi/pawtunes
     *
     * This file is part of the PawTunes open-source project.
     * Contributions and feedback are welcome! Visit the repository or website for more details.
     */

    chdir( __DIR__ );

    ## Include files & settings
    require 'inc/autoload.php';

    use lib\PawTunes;

    // Set-up PawTunes Object
    $pawtunes = new PawTunes();

    try {

        ## PHP debugging ini re-writes (where possible)
        error_reporting( ( $pawtunes->config( 'debugging' ) !== 'enabled' ) ? E_ALL & ~E_NOTICE : E_ALL );
        ini_set( 'error_reporting', ( $pawtunes->config( 'debugging' ) !== 'enabled' ) ? E_ALL & ~E_NOTICE : E_ALL );
        ini_set( "log_errors", $pawtunes->config( 'debugging' ) !== 'disabled' );
        ini_set( "error_log", getcwd() . "/data/logs/player_errors.log" );
        ini_set( 'display_errors', $pawtunes->config( 'debugging' ) === 'enabled' );

        ## Handle themes here
        $templates = $pawtunes->getTemplates();

        ## Allow using ?t=parameter for template switching
        if ( !empty( $_GET[ 'template' ] ) && array_key_exists( $_GET[ 'template' ], $templates ) ) {

            $pawtunes->setConfig( 'template', $_GET[ 'template' ] );

        } else if ( empty( $pawtunes->config( 'template' ) ) || !array_key_exists( $pawtunes->config( 'template' ), $templates ) ) {

            ## No switch as above, use settings template
            $pawtunes->setConfig( 'template', key( $templates ) );

        }

        ## Handle playlists etc...
        if ( isset( $_GET[ 'channel' ], $_GET[ 'playlist' ] ) ) {
            require 'inc/playlist-handler.php';
            exit;
        }

        ### Handle Artwork Requests
        if ( isset( $_GET[ 'artwork' ] ) ) {
            require 'inc/handle-artwork.php';
            exit;
        }

        ## Handle requests & other backend stuff
        if ( isset( $_GET[ 'channel' ] ) ) {
            require 'inc/handler.php';
            exit;
        }


        // Template loader
        if ( !is_file( "{$templates[ $pawtunes->config( 'template' ) ][ 'path' ]}/{$templates[ $pawtunes->config( 'template' ) ][ 'template' ]}" ) ) {
            die( 'Unable to find the template file!' );
        }

        ob_start( [ $pawtunes, 'outputBufferHandler' ] );
        echo file_get_contents( "{$templates[ $pawtunes->config( 'template' )][ 'path' ]}/{$templates[ $pawtunes->config( 'template' ) ][ 'template' ]}" );
        $output = ob_get_clean();

        // Vars
        $output = $pawtunes->template( $output, $pawtunes->getTemplateEngineOpts() );

    } catch ( Throwable|Exception $e ) {

        // Catch errors, incl. PHP ones
        $pawtunes->writeLog( 'player_errors', "FATAL ERROR: {$e->getMessage()}" );

        // Extra trace info if debug is enabled
        if ( $pawtunes->config( 'debugging' ) !== 'disabled' ) {
            $pawtunes->writeLog( 'player_errors', "FATAL ERROR: {$e->getTraceAsString()}" );
        }

        if ( $pawtunes->config( 'debugging' ) === 'enabled' ) {
            echo 'PAWTUNES ERROR: ' . $e->getMessage();
            exit;
        }

        echo "An error has occurred, please try again later.";
        exit;

    }

    echo $output;