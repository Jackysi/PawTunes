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

// Delete templates cache
if ( isset( $_GET[ 'reset' ] ) && $_GET[ 'reset' ] === 'templates' ) {

    $pawtunes->cache->delete( 'templates' );
    $panel->flash( $panel->alert( 'Templates cache flushed successfully!', 'success' ), 'index.php?page=settings' );

}

// Template settings
if ( isset( $_GET[ 'advanced-options' ] ) ) {

    $template = $_POST[ 'template' ];
    unset( $_POST[ 'template' ] );

    $tpl = $pawtunes->getAdvancedTemplateOptions( $template );
    if ( count( $tpl ) < 1 ) {
        die( 'Unable to set template settings, Template not found!' );
    }

    // Make sure we fill only values in manifest, also don't store empty values
    foreach ( $tpl as $option => $value ) {

        if ( !isset( $_POST[ $option ] ) ) {
            $tpl[ $option ] = false;
            continue;
        }

        if ( $_POST[ $option ] === 'true' ) {
            $tpl[ $option ] = $_POST[ $option ] === 'true';
        }

        $tpl[ $option ] = $_POST[ $option ];

    }


    $pawtunes->setConfig(
        'tplOptions',
        array_merge( $pawtunes->config( 'tplOptions' ), [ $template => $tpl ] )
    );

    if ( $panel->storeConfig( 'config/general', $pawtunes->getConfigAll() ) ) {
        die( 'Saved successfully' );
    }

    die( 'Unable to save settings!' );

}

if ( isset( $_GET[ 'delete' ] ) && $_GET[ 'delete' ] === 'override-share-image' ) {

    @unlink( $pawtunes->config( 'override_share_image' ) );

    $pawtunes->setConfig( 'override_share_image', '' );
    $panel->storeConfig( 'config/general', $pawtunes->getConfigAll() );

}

// Check if method is post
if ( !empty( $_POST ) ) {

    // Map a few fields which we will store
    $store = [
        'tplOptions'      => $pawtunes->config( 'tplOptions' ),
        'artwork_sources' => $panel->mapArtworkSourcesPost( $_POST ),
    ];

    // Upload, but also works with input field
    if ( !empty( $_FILES[ 'override_image' ][ 'name' ] ) ) {

        $uploadOverride = $panel->upload( 'override_image', 'data/images/', 'override-image' );
        if ( $uploadOverride[ 'error' ] !== null ) {

            $message = $panel->alert( $uploadOverride[ 'error' ], 'error' );

        } else {

            $_POST[ 'override_share_image' ] = "./{$uploadOverride[ 'path' ]}";

        }
    }


    if ( isset( $message ) ) {

        echo "";

    } else if ( !empty( $_POST[ 'admin_password' ] ) && strlen( $_POST[ 'admin_password' ] ) < 5 ) {

        $message = $panel->alert( 'Panel password must have at least 5 characters!', 'error' );

    } else if ( $_POST[ 'admin_password' ] !== $_POST[ 'admin_pass2' ] ) {

        $message = $panel->alert( 'new passwords do not match, please try again!', 'error' );

    } else if ( empty( $_POST[ 'admin_username' ] ) ) {

        $message = $panel->alert( 'You must enter admin username or else you won\'t be able to login to the control panel!', 'error' );

    } else if ( !is_numeric( $_POST[ 'artist_maxlength' ] ) || !is_numeric( $_POST[ 'title_maxlength' ] ) || empty( $_POST[ 'title' ] ) || empty( $_POST[ 'track_regex' ] ) ) {

        $message = $panel->alert( 'You did not define artist and/or title max length!', 'error' );

    } else if ( @preg_match( "/{$_POST['track_regex']}/i", null ) === false ) {

        $message = $panel->alert( 'Track RegEx is invalid! Please fix it or use default value.', 'error' );

    } else if ( !is_numeric( $_POST[ 'stats_refresh' ] ) || $_POST[ 'stats_refresh' ] < 3 || $_POST[ 'stats_refresh' ] > 120 ) {

        $message = $panel->alert( 'Invalid range for Stats Refresh Speed. The value must not be lower than <b>3</b> and higher than <b>120</b>!', 'error' );

    } else {

        // Delete submit key
        unset( $_POST[ 'admin_pass2' ] );

        // Password handle
        if ( !empty( $_POST[ 'admin_password' ] ) ) { // Hash password, safety

            $_POST[ 'admin_password' ] = password_hash( $_POST[ 'admin_password' ], PASSWORD_DEFAULT );

        } else { // No password provided

            $_POST[ 'admin_password' ] = $pawtunes->config( 'admin_password' );

        }

        // Keep development key
        if ( !empty( $pawtunes->config( 'development' ) ) ) {
            $_POST[ 'development' ] = $pawtunes->config( 'development' );
        }

        // Cache path is bit different, due to allowing different caching methods
        if ( isset( $_POST[ 'cache_path' ] ) ) {
            $_POST[ 'cache' ][ 'path' ] = $_POST[ 'cache_path' ];
            unset( $_POST[ 'cache_path' ] );
        }

        $pawtunes->setConfigAll( $store + $_POST );
        if ( $panel->storeConfig( 'config/general', $pawtunes->getConfigAll() ) ) {

            $message = $panel->alert( 'Settings successfully updated!', 'success' );

        } else {

            $message = $panel->alert( 'Unable to save configuration changes, you may not have sufficient permissions!', 'error', true );

        }

    }


} else {

    $_POST = $pawtunes->getConfigAll();
    unset( $_POST[ 'artwork_sources' ] );

}

// Never show password
if ( isset( $_POST[ 'admin_password' ] ) ) {
    unset( $_POST[ 'admin_password' ] );
}

if ( isset( $_POST[ 'admin_pass2' ] ) ) {
    unset( $_POST[ 'admin_pass2' ] );
}

// Create list of available languages
$language = include( 'panel/inc/language-list.php' );
$languages = [];
if ( is_dir( 'inc/locale' ) ) {

    $ff = $pawtunes->browse( 'inc/locale/' );
    $files = [];

    // Only available
    foreach ( $ff as $file ) {
        $filename = $pawtunes->extDel( $file );
        $languages[ $file ] = $language[ $filename ];
    }

}

// Get all available channels
$channels = $pawtunes->getChannels();

// Now create options array
$channelsList = [ 0 => 'Default' ];
if ( is_array( $channels ) ) {
    foreach ( $channels as $c ): $channelsList[ $c[ 'name' ] ] = $c[ 'name' ]; endforeach;
}

// Now create options array
$templates = $pawtunes->getTemplates();
$templatesList = array_map(
    static function( $var ) {
        return $var[ 'name' ];
    }, $templates
);

// Cache path is bit different, due to allowing different caching methods
$_POST[ 'cache_path' ] = $_POST[ 'cache' ][ 'path' ] ?? null;

$panel->view(
    'settings',
    [
        'artworkAPIs'   => $panel->mapArtworkSourcesView( $pawtunes->getConfigAll()[ 'artwork_sources' ] ?? [] ),
        'pawtunes'      => $pawtunes,
        'message'       => $message ?? null,
        'languages'     => $languages,
        'templates'     => $templates,
        'templatesList' => $templatesList,
        'channels'      => $channelsList,
    ]
);