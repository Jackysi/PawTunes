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
     * Returns default image if not found
     *
     * @var \lib\PawTunes $pawtunes
     */

    if ( !isset( $panel ) ) {
        header( "Location: index.php?page=home" );
        exit;
    }

    // Get active template
    $templates = $pawtunes->getTemplates();

    // Now create options array
    $templatesList = [ 0 => 'None' ];
    foreach ( $templates as $key => $var ) : $templatesList[ $key ] = $var[ 'name' ]; endforeach;

    // Get all available channels
    $channels = [];
    if ( is_file( 'inc/config/channels.php' ) ) {
        $channels = include( 'inc/config/channels.php' );
    }

    // Now create options array
    $def_channel = [ 0 => 'None' ];
    if ( is_array( $channels ) ) {
        foreach ( $channels as $c ): $def_channel[ $c[ 'name' ] ] = $c[ 'name' ]; endforeach;
    }

    // Create list of available languages
    $language = include( 'panel/inc/language-list.php' );
    if ( is_dir( 'inc/locale' ) ) {

        $ff = $pawtunes->browse( 'inc/locale/' );
        $files = [];

        // Only available
        foreach ( $ff as $file ) {
            $filename = $pawtunes->extDel( $file );
            $languages[ $file ] = $language[ $filename ];
        }

    }

    // Now handle format width x height
    $template = $templates[ $pawtunes->config( 'template' ) ];
    if ( !empty( $template[ 'size' ] ) && strpos( $template[ 'size' ], 'x' ) !== false ) {
        [ $w, $h ] = explode( 'x', $template[ 'size' ] );
    }


    $panel->view(
        'home',
        [
            'templatesList' => $templatesList,
            'def_channel'   => $def_channel,
            'languages'     => $languages,
            'w'             => $w ?? null,
            'h'             => $h ?? null,
        ]
    );