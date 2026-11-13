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

use lib\AI\ArtworkGenerator;
use lib\PawException;

/**
 * Seconds a track info copy stays in the cache past its refresh time. During that window the stale
 * copy is served to everyone except the one request that refreshes it (see the lock below), and it
 * is also what listeners get when the upstream server is briefly unreachable.
 */
const STREAM_INFO_STALE_GRACE = 60;

/**
 * Lifetime of the refresh lock: it must outlive the slowest provider request (10 s for "direct",
 * 5 s for the others) with a margin, in case the request holding it dies without releasing it.
 */
const STREAM_INFO_LOCK_TTL = 30;

/**
 * HTTP max-age for a provider error response: short negative caching so a dead upstream is not
 * hammered by every poll once a shared cache sits in front of PHP.
 */
const STREAM_INFO_ERROR_MAX_AGE = 5;

/**
 * HTTP max-age for the channel list: it changes only when the admin edits channels or templates.
 * External (JSONP) consumers see such a change within a minute; the player itself reads the list
 * from the page, not from this endpoint.
 */
const CHANNEL_LIST_MAX_AGE = 60;

/**
 * Unix time until which a cached track info copy counts as fresh (fetch time + refresh interval)
 *
 * @param  array  $info  Cached track info
 *
 * @return int
 */
function trackInfoFreshUntil(array $info): int
{
    $fetched = trackInfoFetchedAt($info);
    if ($fetched === 0) {
        return 0;
    }

    // "retry" is set while the upstream is failing, it keeps a stale copy servable for a few seconds
    return max($fetched + (int) ($info['cache']['refresh'] ?? 0), (int) ($info['cache']['retry'] ?? 0));
}

/**
 * Unix time a track info copy was fetched. "fetched" avoids parsing the local "date" string, which is
 * ambiguous for an hour when daylight saving time ends; copies cached before it existed fall back to "date".
 *
 * @param  array  $info  Cached track info
 *
 * @return int  0 when unknown
 */
function trackInfoFetchedAt(array $info): int
{
    if ( ! empty($info['cache']['fetched'])) {
        return (int) $info['cache']['fetched'];
    }

    return empty($info['cache']['date']) ? 0 : (int) strtotime($info['cache']['date']);
}

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

// Cache related info
$info['cache']['status']  = false;
$info['cache']['date']    = date('Y-m-d H:i:s');
$info['cache']['fetched'] = time();
$info['cache']['refresh'] = (($pawtunes->config('stats_refresh') - 1) <= 1) ? 10 : ($pawtunes->config('stats_refresh') - 1);

// HTTP caching: how long this response may be reused, decided below
$maxAge = $info['cache']['refresh'];

/* Now do the heavy work, use configured method to get stats information
============================================================================================== */
switch ($channel['stats']['method']) {

    case 'azuracast':
    case 'centovacast':
    case 'custom':
    case 'direct':
    case 'icecast':
    case 'icecast-public':
    case 'sam':
    case 'shoutcast':
    case 'shoutcast-public':

        $cacheKey = 'stream.info.'.$chn_key;
        $cached   = $pawtunes->cache->get($cacheKey);

        // A negative marker (stored by the catch below) records a recent provider failure. It is not
        // servable as track data, only as the error shape, so it is kept apart from a real copy.
        $hasCopy = is_array($cached) && empty($cached['error']);
        $fresh   = is_array($cached) && trackInfoFreshUntil($cached) > time();

        // Fresh real copy: serve it, the response may be reused until the copy expires
        if ($hasCopy && $fresh) {

            $info                    = $cached;
            $info['cache']['status'] = true;
            $maxAge                  = trackInfoFreshUntil($cached) - time();
            break;

        }

        // Fresh negative marker: the upstream failed moments ago. Keep returning the error shape without
        // calling it again, so sequential polls do not hammer a dead server between refreshes.
        if ($fresh && ! $hasCopy) {

            $maxAge = max(1, trackInfoFreshUntil($cached) - time());
            break; // $info is still the cache-only error shape set above

        }

        // Not fresh (stale, an expired marker, or a cold start): exactly one request refreshes it (the one
        // that wins the lock), everyone else serves the stale copy if there is one, or a short error shape.
        // The lock covers the cold path too, so a dead upstream with no copy is hit by one request, not all.
        $lockKey   = $cacheKey.'.lock';
        $lockToken = bin2hex(random_bytes(8)); // owner tag, so we only delete the lock while it is still ours
        $locked    = $pawtunes->cache->add($lockKey, $lockToken, STREAM_INFO_LOCK_TTL);

        if ( ! $locked) {

            if ($hasCopy) {

                $info                    = $cached;
                $info['cache']['status'] = true;
                $maxAge                  = 1;

            } else {

                $maxAge = STREAM_INFO_ERROR_MAX_AGE;

            }
            break;

        }

        // Guess className required for specific method
        $className = "\\lib\\PawTunes\\StreamInfo\\".str_replace('-', '', ucwords($channel['stats']['method'], '-'));

        try {

            // Initiate class and get data
            $class = new $className($pawtunes, $channel);
            $info  += $class->getInfo();

            // AI Artwork may only draw artists this station really played, never names from a URL
            ArtworkGenerator::markPlayed($pawtunes, (string) ($info['artist'] ?? ''));

            // Artwork in lazy mode?
            if ($pawtunes->config('artwork_lazy_loading') !== true) {

                $info['artwork'] = $pawtunes->getArtwork(
                    $info['artist'],
                    ( ! $pawtunes->config('artist_images_only')) ? $info['title'] : '',
                    $info['artwork_override'] ?? ''
                );

            } else {

                $info['artwork'] = $pawtunes->lazyArtworkURL($info['artist'], $info['title'], $info['artwork_override'] ?? '');

            }

            // Handle history lazy & normal loading
            if (isset($info['history']) && count($info['history']) > 0) {
                foreach ($info['history'] as $key => $track) {

                    if ($pawtunes->config('artwork_lazy_loading') !== true) {// Slow

                        $info['history'][$key]['artwork'] = $pawtunes->getArtwork(
                            $info['history'][$key]['artist'],
                            ( ! $pawtunes->config('artist_images_only')) ? $info['history'][$key]['title'] : '',
                            $info['history'][$key]['artwork_override'] ?? ''
                        );

                    } else { // Fast

                        $info['history'][$key]['artwork'] = $pawtunes->lazyArtworkURL($track['artist'], $track['title'], $track['artwork_override'] ?? '');

                    }

                    // Delete useless var
                    unset($info['history'][$key]['artwork_override']);

                }
            }


            // Delete useless var
            unset($info['artwork_override']);

            // Set cache: the copy is fresh for "refresh" seconds (see trackInfoFreshUntil) and stays
            // around for the grace period after that, to be served while it is being refreshed
            $pawtunes->cache->set($cacheKey, $info, $info['cache']['refresh'] + STREAM_INFO_STALE_GRACE);

            // Cache for channels list and w/e else
            $pawtunes->cache->set('stream.info.historic.'.$chn_key, $info, 0);

        } catch (PawException $e) {

            // Catch errors, incl. PHP ones
            $pawtunes->writeLog('player_errors', "{$channel['name']}: {$e->getMessage()}");

            // Extra trace info if debug is enabled
            if ($pawtunes->config('debugging') === 'enabled') {
                $pawtunes->writeLog('player_errors', "{$channel['name']}: {$e->getTraceAsString()}");
            }

            // A stale copy beats an empty answer. Either way the failure is remembered for a few seconds, so
            // the fresh/stale/lock logic above shields a dead upstream from sequential polls too, not just
            // concurrent ones: without that, every poll after the lock is released would retry it.
            if ($hasCopy) {

                // The copy counts as fresh until "retry", but keeps its original expiry, so a long outage
                // still ends in the error shape once the copy is too old to be worth showing
                $info                    = $cached;
                $info['cache']['status'] = true;
                $info['cache']['retry']  = time() + STREAM_INFO_ERROR_MAX_AGE;
                $maxAge                  = STREAM_INFO_ERROR_MAX_AGE;

                $expiresIn = trackInfoFetchedAt($cached) + (int) $cached['cache']['refresh'] + STREAM_INFO_STALE_GRACE - time();
                if ($expiresIn > 0) {
                    $pawtunes->cache->set($cacheKey, $info, $expiresIn);
                }

            } else {

                $maxAge   = STREAM_INFO_ERROR_MAX_AGE;
                $negative = [
                    'cache' => ['status' => false, 'date' => date('Y-m-d H:i:s'), 'fetched' => time(), 'refresh' => STREAM_INFO_ERROR_MAX_AGE],
                    'error' => true,
                ];
                $pawtunes->cache->set($cacheKey, $negative, STREAM_INFO_ERROR_MAX_AGE);

            }

        } finally {

            // Only delete the lock while it is still ours: a holder that outlived the TTL must not remove
            // the lock a later refresher has just created. peek() reads the raw value (the lock is created
            // with add() and is not tracked in the disk store, so get() would miss it in disk mode).
            if ($locked && $pawtunes->cache->peek($lockKey) === $lockToken) {
                $pawtunes->cache->delete($lockKey);
            }

        }
        break;


    /* Disabled, simply return defaults
    ============================================================================================== */
    default:

        // Disabled or ERROR occurred
        $info = [
            'artist' => $pawtunes->config('artist_default'),
            'title'  => $pawtunes->config('title_default'),
            'image'  => $pawtunes->getArtwork(null),
            'status' => 'disabled',
        ];

        // Log if not disabled
        if ($channel['stats']['method'] !== 'disabled') {
            $pawtunes->writeLog('errors', "{$channel['name']}: Invalid method! This is truly fancy error which should have never happened!");
        }

}

// Show output (if this is JSONP request, adapt response to its requirements)
outputJson(empty($info) ? [] : $info, $pawtunes, $maxAge);
