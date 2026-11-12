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
 * Web app manifest handler: index.php?manifest
 * Required scope: $pawtunes (PawTunes), $templates (PawTunes::getTemplates())
 *
 * Generated per request so an installed player ("Add to Home Screen") carries the station's name,
 * description and icon instead of the generic PawTunes ones. Relative URLs (start_url, scope, icons)
 * resolve against this manifest's URL, which is the install directory, so installs in a subdirectory
 * keep working. `id` is the install path: a relative id would resolve against the origin and two
 * players on one host would be treated as the same app. Colors come from the active template's
 * manifest.json (theme_color, background_color), white when a template does not define them.
 */

while (ob_get_level()) {
    ob_end_clean();
}

/**
 * Name for the home screen label: the title when it fits in 12 characters, otherwise cut at the last
 * word boundary within 12 characters (a single word longer than that is cut hard). Characters are
 * counted with PCRE in UTF-8 mode, so this needs no mbstring; a title that is not valid UTF-8 is cut by bytes.
 *
 * @param  string  $title
 *
 * @return string
 */
function manifestShortName(string $title): string
{
    if (preg_match('/^.{0,12}$/us', $title)) {
        return $title;
    }

    // The first 13 characters: a space in 13th position means the first 12 end on a word boundary
    $head  = preg_match('/^.{13}/us', $title, $match) ? $match[0] : substr($title, 0, 13);
    $space = strrpos($head, ' ');
    if ($space > 0) {
        return rtrim(substr($head, 0, $space), " -:,.");
    }

    // One long word: hard cut
    $short = preg_match('/^.{12}/us', $title, $match) ? $match[0] : substr($title, 0, 12);

    return rtrim($short, " -:,.");
}

/**
 * Does the If-None-Match request header name the given ETag? (weak comparison, "*" accepted)
 *
 * @param  string  $etag
 *
 * @return bool
 */
function manifestEtagMatches(string $etag): bool
{
    if (empty($_SERVER['HTTP_IF_NONE_MATCH'])) {
        return false;
    }

    foreach (explode(',', $_SERVER['HTTP_IF_NONE_MATCH']) as $candidate) {

        $candidate = preg_replace('/^W\//', '', trim($candidate));
        if ($candidate === '*' || $candidate === $etag) {
            return true;
        }

    }

    return false;
}

$title = trim((string) $pawtunes->config('title'));
if ($title === '') {
    $title = 'PawTunes';
}

$template = $templates[$pawtunes->config('template')] ?? [];
$icons    = $pawtunes->getAppIcons();

// Install directory as an absolute path ("/" at the web root, "/radio/" in a subdirectory)
$installPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/').'/';

$manifest = [
    'id'               => $installPath,
    'name'             => $title,
    'short_name'       => manifestShortName($title),
    'description'      => trim((string) $pawtunes->config('description')),
    'start_url'        => './',
    'scope'            => './',
    'display'          => 'standalone',
    'theme_color'      => $template['theme_color'] ?? '#ffffff',
    'background_color' => $template['background_color'] ?? '#ffffff',
    'icons'            => [
        ['src' => $icons[192], 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icons[512], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
    ],
];

$json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$etag = '"'.md5($json).'"';

// Browsers fetch the manifest on page load to judge installability and serve it from their cache within
// max-age (no revalidation). Ten minutes keeps a renamed station or a new icon from being stale for long
// while a listener who keeps the page open costs one request per ten minutes.
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=600');
header('ETag: '.$etag);

if (manifestEtagMatches($etag)) {
    http_response_code(304);
    exit;
}

echo $json;
