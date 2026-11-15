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
 * Track info request handler.
 * Required scope: $pawtunes (PawTunes), $templates (array)
 */

require_once __DIR__.'/track-info.php';

/**
 * HTTP max-age for the channel list: it changes only when the admin edits channels or templates.
 * External (JSONP) consumers see such a change within a minute; the player itself reads the list
 * from the page, not from this endpoint.
 */
const CHANNEL_LIST_MAX_AGE = 60;

/**
 * Does the If-None-Match request header name the given ETag?
 *
 * @param  string  $etag
 *
 * @return bool
 */
function etagMatches(string $etag): bool
{
    if (empty($_SERVER['HTTP_IF_NONE_MATCH'])) {
        return false;
    }

    // Weak comparison: the "W/" prefix is ignored on both sides, only the opaque tag counts
    $etag = preg_replace('/^W\//', '', $etag);

    foreach (explode(',', $_SERVER['HTTP_IF_NONE_MATCH']) as $candidate) {

        $candidate = preg_replace('/^W\//', '', trim($candidate));
        if ($candidate === '*' || $candidate === $etag) {
            return true;
        }

    }

    return false;
}

/**
 * Output the JSON (or JSONP) response with HTTP caching headers.
 *
 * Browsers, CDNs and reverse proxies may reuse the response for max-age seconds, which is why
 * listeners polling within one refresh interval never reach PHP once such a cache sits in front of
 * it. The ETag is a weak validator over the track data only: the "cache" block (fetch date, hit
 * status) changes on every refresh while the track usually does not, so an unchanged track answers
 * a conditional request with 304 and no body.
 *
 * @param  array  $data  Response data
 * @param  lib\PawTunes  $pawtunes
 * @param  int  $maxAge  Seconds the response may be reused (at least 1)
 *
 * @throws JsonException
 */
function outputJson(array $data, $pawtunes, int $maxAge): void
{
    // Stream servers and databases (SAM's latin1 history, for one) may hand over invalid UTF-8: substitute
    // those bytes instead of failing the whole response
    $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

    // JSONP: the callback name is part of the URL, so the wrapped body is cacheable like plain JSON
    if ( ! empty($_GET['callback']) && $pawtunes->config('api') === true) {

        $callback = $_GET['callback'];
        if ( ! preg_match('/^[a-zA-Z_$][a-zA-Z0-9_$.]*$/', $callback)) {
            http_response_code(400);
            exit(json_encode(['error' => 'Invalid callback parameter']));
        }

        header('Content-Type: application/javascript; charset=utf-8');
        $json = "{$callback}({$json});";

    }

    // No stale-while-revalidate: a browser that honours it answers the next poll from its own stale copy
    // (showing a track change one interval late), and the server-side stale copy plus refresh lock already
    // keep the upstream load to one request per interval. A shared cache still reuses the copy for max-age.
    $cacheControl = 'public, max-age='.max(1, $maxAge);

    $etag = 'W/"'.md5(json_encode(array_diff_key($data, ['cache' => true]), JSON_INVALID_UTF8_SUBSTITUTE).(isset($callback) ? $callback : '')).'"';

    header('Cache-Control: '.$cacheControl);
    header('ETag: '.$etag);

    if (etagMatches($etag)) {
        http_response_code(304);
        exit;
    }

    exit($json);
}

$channels = $pawtunes->getChannels();

// Start few functions and init objects
header("Content-Type: application/json; charset=utf-8");

// Public API: let any origin read track info and the channel list (sent before any exit, 304s included)
if ($pawtunes->config('api') === true) {
    header('Access-Control-Allow-Origin: *');
}

// Important so full images are downloaded!
ignore_user_abort(true);

/* Initial player run, get all channels in nice json object
============================================================================================== */
if (isset($_GET['channel']) && $_GET['channel'] === 'all') {

    // If count of channels is more than 1
    if (count($channels) >= 1) {

        $out = [];

        // Recreate channel options array for best practice and security
        foreach ($channels as $chn_key => $chn) {

            // Check if skin exists
            if (empty($chn['skin']) || ! is_file("{$templates[$pawtunes->config('template')]['path']}/{$chn[ 'skin' ]}")) {

                // Better set default too
                $chn['skin'] = $templates[$pawtunes->config('template')]['schemes'][0]['style'];

            }

            // Web sockets
            if (
                ! empty($chn['stats']['url'])
                && in_array($chn['stats']['method'], ['azuracast', 'custom'])
            ) {

                $statsURL = parse_url($chn['stats']['url']);

                // Set websocket info
                $chn['websocket']['method'] = $chn['stats']['method'];
                $chn['websocket']['url']    = ($statsURL['scheme'] === 'wss') ? $chn['stats']['url'] : false;

                // Azura Cast has additional parameters
                if ($chn['stats']['method'] === 'azuracast') {

                    $chn['websocket']['station']         = $chn['stats']['station'];
                    $chn['websocket']['history']         = $chn['stats']['azura-history'];
                    $chn['websocket']['useRemoteCovers'] = $chn['stats']['use-cover'];
                    $chn['websocket']['upNext']          = ! empty($chn['stats']['up-next']);

                }

            }


            $out[$chn_key] = [
                'name'      => $chn['name'],
                'logo'      => $chn['logo'],
                'websocket' => $chn['websocket'] ?? null,
                'skin'      => "{$templates[$pawtunes->config('template')]['path']}/{$chn[ 'skin' ]}",
                'streams'   => $chn['streams'],
            ];

        }

        // Output data into JSON array (or JSONP)
        outputJson($out, $pawtunes, CHANNEL_LIST_MAX_AGE);

    }

    // No channels defined, return empty json
    $pawtunes->exitJSON();

}

// Search for our channel in config, fallback to first channel if not found
// (graceful: if admin renamed a channel, returning visitors still get audio)
$chn_key = 0;
foreach ($channels as $key => $search) {
    if ($search['name'] === $_GET['channel']) {
        $chn_key = $key;
        break;
    }
}

## Make sure this channel really exists
if ( ! isset($channels[$chn_key]) || ! is_array($channels[$chn_key])) {
    die(json_encode([]));
}

// Set few vars before attempting fate :)
$channel = $channels[$chn_key];

// Cached copy or a fresh one from the channel's track info method (inc/track-info.php)
[$info, $maxAge] = trackInfo($pawtunes, $chn_key, $channel);

// Show output (if this is JSONP request, adapt response to its requirements)
outputJson(empty($info) ? [] : $info, $pawtunes, $maxAge);
