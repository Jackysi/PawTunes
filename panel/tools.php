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
 * @var lib\PawTunes $pawtunes
 */

if ( ! isset($panel)) {
    header("Location: index.php?page=home");
    exit;
}

// No can do without index definition!
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;

// Load channels file (used for all actions on this page)
$channels = [];
if (is_file("inc/config/channels.php")) {
    $channels = include("inc/config/channels.php");
}

// Artwork manager allowed extensions option
$allow_ext = ['jpeg', 'jpg', 'png', 'svg', 'webp'];

// Get list of templates available
$templates = $pawtunes->getTemplates();

// Handle compiling new CSS stylesheet
if ( ! empty($_POST) && $_POST['form'] === 'compile') {

    include 'panel/lib/scss/scss.inc.php';

    // Path
    $base_path = $templates[$_POST['template']]['path'];
    $scss_file = '';

    // Find scheme
    if (is_array($templates[$_POST['template']]['schemes']) && count($templates[$_POST['template']]['schemes']) >= 1) {
        foreach ($templates[$_POST['template']]['schemes'] as $key => $path) {

            if ($path['name'] === $_POST['base-theme']) {

                $scss_file = $path['compile'];
                break;

            }

        }
    }


    // Validate data
    if (empty($_POST['filename']) || empty($_POST['base-theme']) || empty($_POST['base-color'])) {

        $theme_message = $panel->alert('Invalid data submission! There are some missing fields, please try again!', 'error');

    } elseif ($scss_file === '' || ! is_file("{$base_path}/{$scss_file}")) {

        $theme_message = $panel->alert('Unable to compile a new theme since the <b>base theme</b> file or <b>template</b> path is missing!', 'error');

    } elseif ( ! is_dir("{$base_path}/custom/") && ! mkdir("{$base_path}/custom/", 0755) && ! is_dir("{$base_path}/custom/")) {

        $theme_message = $panel->alert('Directory "custom" under the template directory does not exist because something went wrong while creating it!', 'error');

    } else {

        // Compile SASS and save it as file.
        $scss = new ScssPhp\ScssPhp\Compiler();
        $scss->setImportPaths($base_path.'/'.dirname($scss_file));
        $scss->setOutputStyle(OutputStyle::COMPRESSED);

        // Compile!
        try {

            $contents = $scss->compileString("\$accent-color: {$_POST[ 'base-color' ]}; \$bg-color: {$_POST['bg-color']}; @import '".basename($scss_file)."';");

            // Append color & scheme to the output file so we can use the information on update
            if ($contents->getCss() !== null) {

                // Replace pre-defined text strings
                $contents = preg_replace(
                    ['/accent=([^;]*);/i', '/scheme=([^;]*);/i'],
                    ["accent={$_POST['base-color']};", "scheme=".basename($scss_file).";"],
                    $contents->getCss()
                );

            }

            // Create directory if not existing
            if ( ! is_dir("{$base_path}/custom/")) {
                if ( ! mkdir("{$base_path}/custom/", 0755) && ! is_dir("{$base_path}/custom/")) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', "{$base_path}/custom/"));
                }
            }

            // Attempt to save
            if (file_put_contents("{$base_path}/custom/{$_POST['filename']}.css", $contents)) {

                // Clear templates cache
                $pawtunes->cache->delete('templates');
                $theme_message = $panel->alert('Successfully compiled new player theme!', 'success');

            } else {

                $theme_message = $panel->alert('Unable to save new theme! Please make sure the template directory is writable (chmod 755)!', 'error');

            }

        } catch (SassException $e) {

            $theme_message = $panel->alert('SASS Compilation ERROR occurred: '.$e->getMEssage().'!', 'error');

        }

    }

}

// Handle artist image upload
if ( ! empty($_POST) && $_POST['form'] === 'artwork') {

    header('Content-Type: text/plain');
    if ( ! isset($_FILES['image']) || ! $_FILES['image']['tmp_name']) {
        $panel->sendError('No image was uploaded!');
    }

    if ( ! in_array($pawtunes->extGet($_FILES['image']['name']), $allow_ext)) {
        $panel->sendError('You have uploaded invalid image file!');
    }

    if (empty($_POST['track'])) {
        $panel->sendError('You need to enter track name!');
    }


    $track = $pawtunes->parseTrack($_POST['track']);
    $panel->deleteArtwork($_POST['track']);

    // Attempt to save
    $up = $panel->upload('image', 'data/images/', $track);
    if ( ! empty($up['error'])) {

        $panel->sendError("Uploading failed! ERROR: {$up['error']}");

    } elseif ( ! extension_loaded('gd')) {

        @unlink($up['path']);
        $panel->sendError('Cropping image failed because GD extension is not available!');

    } else {

        // From post to variable
        $p['cropY'] = (int) trim($_POST['cropY']);
        $p['cropX'] = (int) trim($_POST['cropX']);

        // Check image size
        if (
            empty($pawtunes->config('images_size'))
            ||
            ! is_numeric($pawtunes->config('images_size'))
            || $pawtunes->config('images_size') < 100
        ) {

            $pawtunes->setConfig('images_size', 280);

        }

        // Calculate crop position depending on input/output image size
        if ($p['cropY'] !== 0) {

            $p['cropY'] *= ($pawtunes->config('images_size') / 140);

        } elseif ($p['cropX'] !== 0) {

            $p['cropX'] *= ($pawtunes->config('images_size') / 140);

        }

        // Crop
        lib\ImageResize::handle(
            $up['path'],
            "{$pawtunes->config('images_size')}x{$pawtunes->config('images_size')}",
            'crop', null,
            [
                'cropY' => $p['cropY'],
                'cropX' => $p['cropX'],
            ]
        );

        // Show success
        echo 'Artwork image was uploaded and handled successfully!';

    }

    exit;

}

$panel->view(
    'tools',
    [
        'theme_message' => $theme_message ?? null,
        'templates'     => $templates,
        'channels'      => $channels,
        'extensions'    => $pawtunes->artworkExtensions,
    ]
);