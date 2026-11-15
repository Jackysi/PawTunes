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

if ( ! function_exists('isCssColor')) {
    /**
     * Accept only real CSS color literals: #rgb, #rgba, #rrggbb, #rrggbbaa and rgb()/rgba()/hsl()/hsla()
     * with numeric (optionally percent) arguments. The value is interpolated into SCSS source, so nothing
     * else may pass.
     *
     * @param  string  $color
     *
     * @return bool
     */
    function isCssColor(string $color): bool
    {
        $hex    = '#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})';
        $number = '-?\d+(?:\.\d+)?%?';
        $func   = '(?:rgb|hsl)a?\(\s*'.$number.'\s*,\s*'.$number.'\s*,\s*'.$number.'(?:\s*,\s*'.$number.')?\s*\)';

        return (bool) preg_match('/^(?:'.$hex.'|'.$func.')$/i', $color);
    }
}

// Handle compiling new CSS stylesheet
if ( ! empty($_POST) && ($_POST['form'] ?? '') === 'compile') {

    // Every field ends up in a file name or in SCSS source, so each one is validated against a
    // strict shape before it is used anywhere. The is_string() guards keep a posted array
    // (filename[]=x, template[]=x) from throwing a TypeError in trim() or an illegal-offset fatal
    // in the $templates[$template] lookup on PHP 8.
    $filename    = is_string($_POST['filename'] ?? '') ? strtolower(trim($_POST['filename'])) : '';
    $template    = is_string($_POST['template'] ?? '') ? $_POST['template'] : '';
    $baseTheme   = is_string($_POST['base-theme'] ?? '') ? $_POST['base-theme'] : '';
    $accentColor = is_string($_POST['base-color'] ?? '') ? trim($_POST['base-color']) : '';
    $bgColor     = is_string($_POST['bg-color'] ?? '') ? trim($_POST['bg-color']) : '';

    // The base theme must be one of the chosen template's compilable schemes (manifest "compile" key)
    $base_path = $templates[$template]['path'] ?? '';
    $scss_file = '';
    foreach ($templates[$template]['schemes'] ?? [] as $scheme) {
        if (($scheme['name'] ?? null) === $baseTheme && ! empty($scheme['compile'])) {
            $scss_file = $scheme['compile'];
            break;
        }
    }

    // Validate data
    if ( ! isset($templates[$template])) {

        $theme_message = $panel->alert('Please select one of the installed templates!', 'error');

    } elseif ( ! preg_match('/^[a-z0-9_-]{1,64}$/', $filename)) {

        $theme_message = $panel->alert('The theme name may only contain lowercase letters, numbers, "-" and "_" (up to 64 characters)!', 'error');

    } elseif ($scss_file === '' || ! is_file("{$base_path}/{$scss_file}")) {

        $theme_message = $panel->alert('Unable to compile a new theme since the <b>base theme</b> is not a compilable scheme of the selected template!', 'error');

    } elseif ( ! isCssColor($accentColor) || ! isCssColor($bgColor)) {

        $theme_message = $panel->alert('Colors must be CSS colors such as <b>#3498db</b>, <b>rgb(52, 152, 219)</b> or <b>hsl(204, 70%, 53%)</b>!', 'error');

    } elseif ( ! is_dir("{$base_path}/custom/") && ! mkdir("{$base_path}/custom/", 0755) && ! is_dir("{$base_path}/custom/")) {

        $theme_message = $panel->alert('Directory "custom" under the template directory does not exist because something went wrong while creating it!', 'error');

    } else {

        // Compile SASS and save it as file.
        try {

            $scss = new ScssPhp\ScssPhp\Compiler();
            $scss->setImportPaths($base_path.'/'.dirname($scss_file));
            $scss->setOutputStyle(OutputStyle::COMPRESSED);

            $contents = $scss->compileString("\$accent-color: {$accentColor}; \$bg-color: {$bgColor}; @import '".basename($scss_file)."';")->getCss();

            // Append color & scheme to the output file so we can use the information on update
            $contents = preg_replace(
                ['/accent=([^;]*);/i', '/scheme=([^;]*);/i'],
                ["accent={$accentColor};", "scheme=".basename($scss_file).";"],
                $contents
            );

            // Attempt to save
            if (file_put_contents("{$base_path}/custom/{$filename}.css", $contents)) {

                // Clear templates cache
                $pawtunes->cache->delete('templates');
                $theme_message = $panel->alert('Successfully compiled new player theme!', 'success');

            } else {

                $theme_message = $panel->alert('Unable to save new theme! Please make sure the template directory is writable (chmod 755)!', 'error');

            }

        } catch (SassException $e) {

            $theme_message = $panel->alert('SASS Compilation ERROR occurred: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'!', 'error');

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

    // Attempt to save (upload() verifies the file is really one of the allowed image formats)
    $up = $panel->upload('image', 'data/images/', $track, $allow_ext);
    if ( ! empty($up['error'])) {

        $panel->sendError("Uploading failed! ERROR: {$up['error']}");

    }

    // The new file replaced its own format; drop copies of this track in the other formats
    foreach ($allow_ext as $ext) {
        if ("data/images/{$track}.{$ext}" !== $up['path'] && is_file("data/images/{$track}.{$ext}")) {
            $pawtunes::deleteFile("data/images/{$track}.{$ext}");
        }
    }

    if ( ! extension_loaded('gd')) {

        $pawtunes::deleteFile($up['path']);
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