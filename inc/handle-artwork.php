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

// Generated Artwork: drawn from the name on every request, nothing is stored. The image depends on the name only,
// so browsers may keep it for good.
if (isset($_GET['generated'])) {

    $name = (string) $_GET['generated'];
    if ( ! $pawtunes->config('generated_artwork') || ! lib\GeneratedArtwork::isValidName($name)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    exit(lib\GeneratedArtwork::svg($name));

}

if ( ! isset($_GET['artist'])) {
    http_response_code(404);
    exit;
}

// Important so full images are downloaded!
ignore_user_abort(true);

$override = null;
if (isset($_GET['override'])) {
    $decoded = base64_decode($_GET['override'], true);
    if ($decoded !== false && filter_var($decoded, FILTER_VALIDATE_URL)) {
        $scheme = parse_url($decoded, PHP_URL_SCHEME);
        $host   = parse_url($decoded, PHP_URL_HOST);

        // Only allow http/https and block private/reserved IP ranges
        if (
            in_array($scheme, ['http', 'https'], true)
            && $host !== false
            && filter_var(gethostbyname($host), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        ) {
            $override = $decoded;
        }
    }
}

$title = ($pawtunes->config('artist_images_only')) ? '' : $_GET['title'] ?? '';
$art   = $pawtunes->getArtwork($_GET['artist'], $title, $override ?? '');

// If default, return 404
if ( ! $art) {
    http_response_code(404);
    exit;
}

// Real artwork is cached by browsers for 14 days. Placeholders (the default image, Generated Artwork) only for an
// hour: they stand in for "nothing found yet", and a cover found later (provider back up, AI Artwork drawn after
// this response, an upload) should reach listeners soon.
$placeholder  = ($art === $pawtunes->defaultArtwork()) || (strpos($art, 'artwork&generated=') !== false);
$browserCache = $placeholder ? 3600 : 1209600;

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
    flush();
    exit;

}

// Art is now downloaded image or URL to the image, redirect to it
http_response_code(302);
header("Cache-Control: public, max-age={$browserCache}");
header("Location: {$art}");
exit;