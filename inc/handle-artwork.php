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
 * Artwork proxy/resize handler.
 * Required scope: $pawtunes (PawTunes)
 */

while (ob_get_level()) {
    ob_end_clean();
}

if ( ! isset($_GET['artist'])) {
    http_response_code(404);
    exit;
}

// Important so full images are downloaded!
ignore_user_abort(true);

$override = null;
if (isset($_GET['override'])) {
    $decoded = base64_decode((string) $_GET['override'], true);
    if ($decoded !== false && lib\Helpers::isPublicURL($decoded)) {
        $override = $decoded;
    }
}

$title = ($pawtunes->config('artist_images_only')) ? '' : $_GET['title'] ?? '';
$art   = $pawtunes->getArtwork($_GET['artist'], $title, $override ?? '');

// If default, return 404
if ( ! $art) {
    http_response_code(404);
    exit;
}

// Real artwork is cached by browsers for 14 days. The default image only for an hour: it stands in for "nothing
// found yet", and a cover found later (provider back up, AI Artwork drawn after this response, an upload) should
// reach listeners soon.
$browserCache = ($art === $pawtunes->defaultArtwork()) ? 3600 : 1209600;

// File hosted on the server? Attempt serving it via web server
if (
    $pawtunes->config('serve_via_web')
    && $pawtunes->config('cache_images')
    &&
    ! filter_var($art, FILTER_VALIDATE_URL)
    && file_exists($art)
) {

    // Web servers require full path
    header("Cache-Control: public, max-age={$browserCache}");
    header('Content-Type: '.mime_content_type(realpath($art)));
    //header('Content-Length: ' . filesize($art));

    // NGINX has it this way, not sure about sendfile
    $art = rtrim($_SERVER['DOCUMENT_URI'] ?? $_SERVER['REQUEST_URI'], 'index.php').ltrim($art, './');
    header('X-Sendfile: '.$art);
    header('X-Accel-Redirect: '.$art);
    header('X-LiteSpeed-Location: '.$art);

    // No flush() here: sent headers would stop PawTunes::afterResponse() from ending the response, and the
    // work queued for after it (recompressing the artwork just downloaded) would be skipped
    exit;

}

// Art is now downloaded image or URL to the image, redirect to it
http_response_code(302);
header("Cache-Control: public, max-age={$browserCache}");
header("Location: {$art}");
exit;