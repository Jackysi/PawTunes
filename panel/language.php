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

    // Load list of languages
    $languages = include 'panel/inc/language-list.php';

    // Read language directory for all files
    $list = $pawtunes->browse( 'inc/locale/' );

    // File delete handler
    if ( isset( $_GET[ 'delete' ] ) ) {

        $deleteFile = preg_replace( '![^a-z0-9]!i', '', $_GET[ 'delete' ] ); ## Replace all but characters and numbers
        if ( is_file( "inc/locale/{$deleteFile}.php" ) && unlink( "inc/locale/{$deleteFile}.php" ) === true ) {

            $panel->flash( $panel->alert( 'Successfully deleted the <b>' . ( $languages[ $_GET[ 'delete' ] ][ 'name' ] ?? 'Unknown' ) . '</b> translation!', 'success' ), 'index.php?page=language' );

        } else {

            $panel->flash( $panel->alert( 'Failed to delete specified language file, you may not have sufficient permissions!', 'error' ) );

        }

    }

    // Display languages table
    if ( !isset( $_GET[ 'add' ] ) && empty( $_GET[ 'edit' ] ) ) {

        $panel->view(
            "language-list",
            [
                'languages'    => $languages,
                'translations' => $list,
                'message'      => $message ?? null,
            ]
        );


    } else {

        // Remove empty spaces before the value
        $_POST = array_map( 'trim', $_POST );

        // Handle submission
        if ( !empty( $_POST ) ) {

            $file = preg_replace( '![^a-z0-9]!i', '', ( ( empty( $_POST[ 'isocode' ] ) ) ? $_GET[ 'edit' ] : $_POST[ 'isocode' ] ) );
            unset( $_POST[ 'isocode' ] );

            if ( empty( $file ) || !isset( $language[ $file ] ) ) {

                echo $panel->alert( 'Invalid ISO code or file name, please cancel and try again.', 'error' );

            } else {

                // Try to save
                if ( $panel->storeConfig( "locale/{$file}", $_POST ) ) {

                    $panel->flash(
                        $panel->alert( '<b>' . ( $language[ $file ][ 'name' ] ?? 'Unknown' ) . '</b> translation has been ' . ( ( isset( $_GET[ 'edit' ] ) ) ? 'updated' : 'added' ) . ' successfully!', 'success' ),
                        'index.php?page=language'
                    );

                }

                echo $panel->alert( 'Failed saving translation because you may not have sufficient permissions!', 'error', true );

            }

        }

        // Load existing file
        if ( isset( $_GET[ 'edit' ] ) && empty( $_POST ) ) {

            $_GET[ 'edit' ] = preg_replace( '![^a-z0-9]!i', '', $_GET[ 'edit' ] ); ## Replace all but characters and numbers
            if ( is_file( 'inc/locale/' . $_GET[ 'edit' ] . '.php' ) ) {

                $lang = include( 'inc/locale/' . $_GET[ 'edit' ] . '.php' );
                $_POST = $lang;

            } else {

                $panel->flash(
                    $panel->alert( 'Invalid ISO code or file name, please select another language and try again.', 'error' ),
                    'index.php?page=language'
                );

            }

        }


        $f = new Forms();
        $panel->view( "language-edit", [ 'form' => $f, 'languages' => $languages ] );

    }