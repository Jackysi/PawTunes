<?php

/**
 * Hint IDE already defined variables from parent (this file is included)
 *
 * @var $this
 */

$deleteFiles = [
    'panel/lib/splitbrain/PHPArchive/Archive.php',
    'panel/lib/splitbrain/PHPArchive/ArchiveCorruptedException.php',
    'panel/lib/splitbrain/PHPArchive/ArchiveIOException.php',
    'panel/lib/splitbrain/PHPArchive/ArchiveIllegalCompressionException.php',
    'panel/lib/splitbrain/PHPArchive/FileInfo.php',
    'panel/lib/splitbrain/PHPArchive/FileInfoException.php',
    'panel/lib/splitbrain/PHPArchive/Tar.php',
    'panel/lib/splitbrain/PHPArchive/Zip.php',

    # Delete folders
    'panel/lib/splitbrain/PHPArchive',
    'panel/lib/splitbrain',
];

// This file runs inside the updater of the release it replaces, and 1.x updaters install without checking the
// PHP version: keep it PHP 7.4 syntax and use only what 1.1.1 already has ($this->panel->storeConfig()).
if ( PHP_VERSION_ID < 80100 ) {

    $this->sendSSE( '<div class="text-danger"><b>PawTunes 2 requires PHP 8.1 or newer, this server runs PHP ' . PHP_VERSION . '.</b> The player and this panel will not load until PHP is upgraded.</div>' );
    $this->pawtunes->writeLog( 'panel_errors', 'POST UPDATE: PawTunes 2 installed on PHP ' . PHP_VERSION . ', it requires PHP 8.1 or newer.' );

}

// *** PAWTUNES 2 CONFIG MIGRATION
// Read from disk, not from $this->pawtunes: the running instance may have been built before the update
$configFile = $this->path . '/inc/config/general.php';
$config     = is_file( $configFile ) ? include $configFile : null;

if ( is_array( $config ) ) {

    $this->sendSSE( '<div>Migrating settings...</div>' );
    $configChanged = false;

    // 1.x stored the share image as "override_share_image"
    if ( array_key_exists( 'override_share_image', $config ) ) {

        if ( empty( $config[ 'share_image_override' ] ) ) {
            $config[ 'share_image_override' ] = $config[ 'override_share_image' ];
        }

        unset( $config[ 'override_share_image' ] );
        $configChanged = true;

    }

    // Never read by anything
    if ( array_key_exists( 'license', $config ) ) {

        unset( $config[ 'license' ] );
        $configChanged = true;

    }

    if ( $configChanged && !$this->panel->storeConfig( 'config/general', $config ) ) {

        $this->sendSSE( '<div class="text-danger">Unable to save <strong>"inc/config/general.php"</strong>, please make sure it is writable!</div>' );
        $this->pawtunes->writeLog( 'panel_errors', 'POST UPDATE: Unable to write the migrated inc/config/general.php!' );

    }

}

// *** CLEAN CACHE & OLD USELESS FILES
$this->sendSSE( '<div>Cleaning old unused files...</div>' );

// Delete old files
foreach ( $deleteFiles as $file ) {

    if ( is_file( $file ) ) {
        if ( !@unlink( $this->path . '/' . $file ) ) {

            $this->sendSSE( '<div class="text-danger">Unable to delete <strong>"' . $file . '"</strong> file!</div>' );
            $this->pawtunes->writeLog( 'panel_errors', 'POST UPDATE: Unable to delete "' . $file . '" file!' );

        }
    }

    if ( is_dir( $file ) ) {
        if ( !@rmdir( $this->path . '/' . $file ) ) {

            $this->sendSSE( '<div class="text-danger">Unable to delete <strong>"' . $file . '"</strong> directory!</div>' );
            $this->pawtunes->writeLog( 'panel_errors', 'POST UPDATE: Unable to delete "' . $file . '" directory!' );

        }
    }

}

// *** CLEAN CACHE & OLD USELESS FILES
$this->sendSSE( '<div>Cleaning cache and temporary files...</div>' );

// Use cache delete first. This file ships with the new release but runs inside the updater of the old one,
// whose Cache class is already loaded: releases before 1.1.2 only have deleteAll().
if ( method_exists( $this->pawtunes->cache, 'clear' ) ) {
    $this->pawtunes->cache->clear();
} else {
    $this->pawtunes->cache->deleteAll();
}

// Cache directory
$cachePath = realpath( $this->path . '/' . $this->pawtunes->config( 'cache' )[ 'path' ] );

// Make sure cache exists/resolves
if ( $cachePath ) {

    // Get list of caches (remove cache)
    $files = $this->pawtunes->browse( $cachePath );
    foreach ( $files as $file ) {
        @unlink( $cachePath . '/' . $file );
    }

}

// Delete the post-update script
@unlink( $this->path . '/post-update.php' );