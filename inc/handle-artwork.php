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

// File hosted on the server? Attempt serving it via web server
if (
    $pawtunes->config('serve_via_web')
    && $pawtunes->config('cache_images')
    &&
    ! filter_var($art, FILTER_VALIDATE_URL)
    && file_exists($art)
) {

    // Web servers require full path
    header("Cache-Control: public, max-age=1209600");
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
header("Cache-Control: public, max-age=1209600");
header("Location: {$art}");
exit;