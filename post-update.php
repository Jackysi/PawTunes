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

    // Use cache delete first
    $this->pawtunes->cache->deleteAll();

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