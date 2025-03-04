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
 * @var PawTunes $pawtunes
 */

use lib\PawTunes;

if ( ! isset($panel)) {
    header("Location: index.php?page=home");
    exit;
}

// Load channels file (used for all actions on this page)
$trackInfoMethods = include "panel/channels.edit.inc.php";

// Options (might change in future)
$codecs       = ['mp3' => 'MP3', 'oga' => 'OGG', 'm4a' => 'AAC'];
$logo_ext     = ['jpeg', 'png', 'webp', 'jpg', 'svg'];
$streamUrlExt = ['pls', 'm3u', 'xspf'];
$templates    = $pawtunes->getTemplates();
$form         = new Forms;


// Attempt to delete logo from existing channel
if ( ! empty($_GET['logo']) && $_GET['logo'] === 'delete' && isset($channels[$_GET['channel']]['logo'])) {

    @unlink($channels[$_GET['channel']]['logo']);
    die("Deleted logo");

}


// Handle POST
if ( ! empty($_POST)) {

    $_POST['name'] = trim($_POST['name']);

    // Verify fields
    if (empty($_POST['name'])) {

        $error = $panel->alert('You need to specify name of the channel you are creating or editing.', 'error');

    } elseif (preg_match("/[^a-zA-Z0-9\s-]/", $_POST['name'])) {

        $error = $panel->alert('The channel name is invalid, it should not contain any special characters.', 'error');

    } elseif ( ! isset($_POST['quality']) || empty($_POST['url_0'][0])) {

        $error = $panel->alert('You have to configure streams! Player does not work without them.', 'error');

        // Success
    } else {

        // Handle upload
        if ( ! empty($_FILES['logo']['tmp_name'])) {

            $filename = "logo.".time();

            // Before continue, delete old image
            if ($_GET['action'] !== 'add' && ! empty($channels[$_GET['channel']]['logo'])) {
                @unlink($channels[$_GET['channel']]['logo']); // Delete old image
            }

            // Attempt to save
            $up = $panel->upload('logo', 'data/images/', $filename);
            if ( ! empty($up['error'])) {

                $error = $panel->alert("Uploading logo failed! ERROR: {$up['error']}", 'error');

            } elseif ( ! in_array($pawtunes->extGet($up['path']), $logo_ext)) {

                $error = $panel->alert("Invalid image format! You can only upload JPEG, JPG, PNG, WEBP and SVG images!", 'error');
                @unlink($up['path']);

            } else { // Save success, now do tell!

                $logoPath = $up['path'];
                if ($pawtunes->extGet($up['path']) !== 'svg') { // Only resize if not SVG

                    // Calculate crop width by having set height
                    $imageSize      = getimagesize($up['path']);
                    $calculateWidth = $imageSize[0] / ($imageSize[1] / 80);

                    // Crop
                    $img = new lib\ImageResize ($up['path']);
                    $img->resize("{$calculateWidth}x80");
                    $img->save($up['path']);

                }

            }
        }


        // Convert quality group's POST to a nicer PHP valid array
        $c              = count($_POST['quality']) - 1;
        $quality_groups = [];

        // Loop through stream groups
        for ($i = 0; $i <= $c; $i++) {

            $streamName = $_POST['quality'][$i];

            // Count fields
            $name        = 'url_'.$i;
            $totalFields = count($_POST[$name]) - 1;
            $streams     = [];

            // LOOP
            for ($f = 0; $f <= $totalFields; $f++) {

                $codec           = $_POST['codec_'.$i][$f];
                $streams[$codec] = $_POST[$name][$f];

                if ( ! filter_var($_POST[$name][$f], FILTER_VALIDATE_URL)) { // Validate if the stream URL is actually an URL or not

                    $error = $panel->alert('Stream URL <b>"'.$_POST[$name][$f].'"</b> is not valid url! Please read section <b>"How to configure streams?"</b> below.', 'error');

                } elseif (in_array($pawtunes->extGet($_POST[$name][$f]), $streamUrlExt)) { // Check if stream URL is a playlist

                    $error = $panel->alert('Stream URL <b>"'.$_POST[$name][$f].'"</b> is a playlist file, not an actual stream! Please read section <b>"How to configure streams?"</b> below.', 'error');

                }

            }

            // Update groups
            $quality_groups[$streamName] = $streams;

        }


        // Attempt to check stats config and create output conf
        if (empty($error)) {

            switch ($_POST['stats']) {

                // Use direct method
                case 'direct':

                    if ( ! filter_var($_POST['direct-url'], FILTER_VALIDATE_URL) || ( ! empty($_POST['direct-url-fallback']) && ! filter_var($_POST['direct-url-fallback'], FILTER_VALIDATE_URL))) {
                        $error = $panel->alert('Configured stream URL for stats is not valid. Please enter real URL to the stream.', 'error');
                    }

                    // normalize array
                    $stats = [
                        'method'   => 'direct',
                        'url'      => $_POST['direct-url'] ?? null,
                        'fallback' => $_POST['direct-url-fallback'] ?? null,
                    ];
                    break;

                // Shoutcast Method
                case 'azuracast':

                    // Check if Shoutcast admin URL can be parsed
                    if ($pawtunes->parseURL($_POST['azura-url'] ?? null) === null) {
                        $error = $panel->alert('Azuracast API/Websocket URL could not be detected. Please use <b>http(s)://url-to-server:port/</b> or <b>wss://url-to-server:port/</b>  format.', 'error');
                    }

                    // Make sure station ID is set
                    if (empty($_POST['azura-station'])) {
                        $error = $panel->alert('Azuracast station ID is not set. Without it, PawTunes cannot determine which stream info to read.', 'error');
                    }

                    // normalize array
                    $stats = [
                        'method'        => 'azuracast',
                        'url'           => $_POST['azura-url'] ?? null,
                        'station'       => $_POST['azura-station'] ?? null,
                        'use-cover'     => ! empty($_POST['azura-use-cover']) && (bool) $_POST['azura-use-cover'] === true,
                        'azura-history' => ! empty($_POST['azura-history']) && (bool) $_POST['azura-history'] === true,
                    ];
                    break;

                // Shoutcast Method
                case 'shoutcast':

                    // Check if Shoutcast admin URL can be parsed
                    if ($pawtunes->parseURL($_POST['shoutcast-url'] ?? null) === null) {
                        $error = $panel->alert('Shoutcast Stats URL could not be detected. Please use <b>http://url-to-server:port</b> format.', 'error');
                    }

                    // normalize array
                    $stats = [
                        'method'     => 'shoutcast',
                        'url'        => $_POST['shoutcast-url'] ?? null,
                        'auth'       => $_POST['shoutcast-pass'] ?? null,
                        'sid'        => $_POST['shoutcast-sid'] ?? null,
                        'sc-history' => ! empty($_POST['sc-history']) && (bool) $_POST['sc-history'] === true,
                    ];
                    break;

                // Shoutcast Public Method
                case 'shoutcast-public':

                    // Check if Shoutcast public URL can be parsed
                    if ($pawtunes->parseURL($_POST['shoutcast-public-url'] ?? null) === null) {
                        $error = $panel->alert('Shoutcast Stats URL could not be detected. Please use <b>http://url-to-server:port</b> format.', 'error');
                    }

                    // normalize array
                    $stats = [
                        'method' => 'shoutcast-public',
                        'url'    => $_POST['shoutcast-public-url'] ?? null,
                        'sid'    => $_POST['shoutcast-public-sid'] ?? null,
                    ];
                    break;

                // Icecast Method
                case 'icecast':

                    // Check if Icecast admin URL can be parsed
                    if ($pawtunes->parseURL($_POST['icecast-url']) === null) {
                        $error = $panel->alert('Icecast stats URL could not be detected. Please use <b>http://url-to-server:port</b> format.', 'error');
                    }

                    // normalize array
                    $stats = [
                        'method'    => 'icecast',
                        'url'       => $_POST['icecast-url'] ?? null,
                        'auth-user' => $_POST['icecast-user'] ?? null,
                        'auth-pass' => $_POST['icecast-pass'] ?? null,
                        'mount'     => $_POST['icecast-mount'] ?? null,
                        'fallback'  => $_POST['icecast-fallback-mount'] ?? null,
                    ];
                    break;

                // Icecast Method
                case 'icecast-public':

                    // Check if Icecast public URL can be parsed
                    if ($pawtunes->parseURL($_POST['icecast-public-url'] ?? null) === null) {
                        $error = $panel->alert('Icecast Stats URL could not be detected. Please use <b>http://url-to-server:port</b> format.', 'error');
                    }

                    // normalize array
                    $stats = [
                        'method' => 'icecast-public',
                        'url'    => $_POST['icecast-public-url'] ?? null,
                        'mount'  => $_POST['icecast-public-mount'] ?? null,
                    ];
                    break;

                // SAM Broadcaster Method
                case 'sam': // normalize array
                    $stats = [
                                 'method'    => 'sam',
                                 'host'      => $_POST['sam-host'] ?? null,
                                 'auth-user' => $_POST['sam-user'] ?? null,
                                 'auth-pass' => $_POST['sam-pass'] ?? null,
                                 'db'        => $_POST['sam-db'],
                             ] ?? null;
                    break;

                // Centovacast Method
                case 'centovacast':

                    // Check if Centovacast panel URL can be parsed
                    if ($pawtunes->parseURL($_POST['centova-url']) === null) {
                        $error = $panel->alert('Centova cast control panel URL could not be detected. Please use <b>http://url-to-server:port</b> format.', 'error');
                    }

                    // normalize array
                    $stats = [
                        'method'    => 'centovacast',
                        'url'       => $_POST['centova-url'],
                        'user'      => $_POST['centova-user'],
                        'use-cover' => ! empty($_POST['centova-use-cover']) && (bool) $_POST['centova-use-cover'] === true,
                    ];
                    break;

                // Custom URL method
                case 'custom': // normalize array
                    $stats = [
                        'method'    => 'custom',
                        'url'       => $_POST['custom-url'],
                        'auth-user' => $_POST['custom-user'],
                        'auth-pass' => $_POST['custom-pass'],
                    ];
                    break;

                case 'disabled':
                    $stats = ['method' => 'disabled'];
                    break;

                default:
                    $error = $panel->alert('Invalid stats configuration! Can not continue!', "error");
                    break;

            }


            // We just used switch done here ;)

        }


        // Prepare output config array
        $conf[] = [
            'name'    => $pawtunes->strToUTF8($_POST['name']),
            'logo'    => ((empty($logoPath)) ? ($channels[$_GET['channel'] ?? null]['logo'] ?? null) : $logoPath),
            'skin'    => ( ! empty($_POST['skin'])) ? $_POST['skin'] : null,
            'streams' => $quality_groups,
            'stats'   => $stats ?? [],
        ];

        // If we already have channels, merge existing data
        if ($_GET['action'] !== 'add' && empty($error)) { ## EDIT

            $confOut                   = $channels;
            $confOut[$_GET['channel']] = $conf[0];

        } elseif (isset($channels) && is_array($channels) && empty($error)) { ## Merge new channels with existing ones

            $confOut = array_merge($channels, $conf);

        } else {

            $confOut = $conf;

        }


        // If any of above action's issued error, show it to user, otherwise save to file
        if ( ! empty($error)) {

            // Show?

        } elseif ($panel->storeConfig('config/channels', $confOut)) {

            $panel->flash($panel->alert('Successfully '.(($_GET['action'] === 'add') ? 'added' : 'updated').' channel.', 'success'), 'index.php?page=channels');

        } else {

            $error = $panel->alert('Unable to store channel settings, you may not have sufficient permissions!', 'error', true);

        }

    }

}


// Not submit & not new file
if ($_GET['action'] !== 'add' && empty($_POST)) {

    if (empty($channels[$_GET['channel']]) || ! is_numeric($_GET['channel'])) {
        $panel->flash($panel->alert('Unable to edit specified channel because it was not found!', 'index.php?page=channels'));
    }


    // Only Convert PHP array of streams to html comparable one if its available
    if (is_array($channels[$_GET['channel']]['streams'])) {

        // Few preset variables
        $cid          = $_GET['channel'];
        $_POST        = $channels[$cid];
        $countStreams = 0;

        // Convert PHP array of streams to html compatible one
        foreach ($channels[$cid]['streams'] as $name => $arr) {

            $_POST['quality'][$countStreams] = $name;
            foreach ($arr as $codec => $url) {
                $_POST['url_'.$countStreams][]   = $url;
                $_POST['codec_'.$countStreams][] = $codec;
            }

            $countStreams++; ## Increase counter
        }

        unset($_POST['streams']);

    }

    // Parse config stats
    $stats          = $channels[$cid]['stats'];
    $_POST['stats'] = $stats['method'];

    // Map config fields to POST so we can use "value" in inputs.
    if (isset($trackInfoMethods[$stats['method']])) {
        foreach ($trackInfoMethods[$stats['method']]['fields'] as $opt) {

            $_POST[$opt['name']] = $stats[$opt['map']] ?? null;

        }
    }

}

// New Channel, defaults
if ($_GET['action'] === 'add' && empty($_POST)) {
    $_POST['stats'] = 'disabled';
}

// Get list of custom color schemes
$schemesList = [];
$custom      = $pawtunes->browse("{$templates[ $pawtunes->config( 'template' ) ]['path']}/custom/", true, false);

// Get from manifest
if (is_array($templates[$pawtunes->config('template')]['schemes']) && count($templates[$pawtunes->config('template')]['schemes']) >= 1) {
    foreach ($templates[$pawtunes->config('template')]['schemes'] as $key => $val): $schemesList[$val['name']] = $val['style']; endforeach;
}

// Get from custom directory
if (is_array($custom) && count($custom) >= 1) {
    foreach ($custom as $file) : $schemesList[ucfirst($pawtunes->extDel($file))] = "custom/{$file}"; endforeach;
}

$panel->view(
    'channel-edit',
    [
        'form'             => $form,
        'codecs'           => $codecs,
        'channels'         => $channels,
        'cid'              => $_GET['channel'] ?? null,
        'error'            => $error ?? null,
        'schemesList'      => $schemesList,
        'stats'            => $stats ?? null,
        'trackInfoMethods' => $trackInfoMethods,
        'data'             => $_POST,
    ]
);