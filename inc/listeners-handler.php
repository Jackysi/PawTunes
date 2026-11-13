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
 * Listener list request handler: ?listeners&channel=<name>
 * Required scope: $pawtunes (PawTunes)
 *
 * Private by default: the active template's "showListeners" option gates the endpoint server-side, so
 * nothing a client sends can open it. Addresses are masked by the provider before they are cached or
 * sent (lib\PawTunes\Listeners). The list is an admin API call upstream, so it is cached for
 * LISTENERS_TTL and only one request refreshes it at a time, whatever the number of viewers.
 *
 * Responses (JSON): {status: "ok", total, listeners: [{ip, device, connected}], refresh}
 *                   {status: "error", refresh}     upstream failed, remembered LISTENERS_ERROR_TTL
 *                   {status: "pending", refresh}   another request is fetching, retry in a moment
 *                   {status: "disabled"} 403       the template option is off
 *                   {status: "unsupported"} 404    unknown channel, or its method cannot list listeners
 */

use lib\PawException;

/**
 * Seconds a fetched list is served from the cache (and the client's polling interval)
 */
const LISTENERS_TTL = 30;

/**
 * Seconds a failed upstream call is remembered, so a dead admin API is not hammered
 */
const LISTENERS_ERROR_TTL = 10;

/**
 * Lifetime of the refresh lock: outlives the 5 s upstream timeout with margin
 */
const LISTENERS_LOCK_TTL = 15;

/**
 * Send the JSON response and stop. A positive max-age lets browsers and shared caches reuse it.
 *
 * @param  array  $data
 * @param  int  $status  HTTP status
 * @param  int  $maxAge  Seconds, 0 = no-store
 *
 * @throws JsonException
 */
function outputListeners(array $data, int $status, int $maxAge): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: '.($maxAge > 0 ? "public, max-age={$maxAge}" : 'no-store'));

    exit(json_encode($data, JSON_THROW_ON_ERROR));
}

// The gate: whatever the request says, only the template option opens this
if ( ! $pawtunes->listenersEnabled()) {
    outputListeners(['status' => 'disabled'], 403, 0);
}

// Exact channel name, no fallback to the first channel: the client hides the tab on a 404
$channel = null;
$chn_key = null;
foreach ($pawtunes->getChannels() as $key => $search) {
    if (isset($search['name'], $_GET['channel']) && $search['name'] === $_GET['channel']) {
        $channel = $search;
        $chn_key = $key;
        break;
    }
}

$provider = ($channel !== null) ? $pawtunes->listenersProvider($channel) : null;
if ($provider === null) {
    outputListeners(['status' => 'unsupported'], 404, 0);
}

// Cached copy (a list or an error marker), still fresh? Serve it for the time it has left.
$cacheKey = 'listeners.'.$chn_key;
$cached   = $pawtunes->cache->get($cacheKey);
$hasList  = is_array($cached) && ($cached['status'] ?? '') === 'ok';

if (is_array($cached) && isset($cached['fetched'])) {

    $remaining = (int) $cached['fetched'] + ($hasList ? LISTENERS_TTL : LISTENERS_ERROR_TTL) - time();
    if ($remaining > 0) {
        unset($cached['fetched']);
        outputListeners($cached, 200, $remaining);
    }

}

// Not fresh: the request that wins the lock fetches, the others get the stale list or a "pending"
$lockKey   = $cacheKey.'.lock';
$lockToken = bin2hex(random_bytes(8));

if ( ! $pawtunes->cache->add($lockKey, $lockToken, LISTENERS_LOCK_TTL)) {

    if ($hasList) {
        unset($cached['fetched']);
        outputListeners($cached, 200, 1);
    }

    outputListeners(['status' => 'pending', 'refresh' => 2], 200, 0);

}

try {

    $data = $provider->getListeners() + ['status' => 'ok', 'refresh' => LISTENERS_TTL];

    // Kept a little past its freshness so lock losers can serve it while it is being refreshed
    $pawtunes->cache->set($cacheKey, $data + ['fetched' => time()], LISTENERS_TTL + 5);
    $result = [$data, 200, LISTENERS_TTL];

} catch (PawException $e) {

    $pawtunes->writeLog('player_errors', "{$channel['name']}: listeners unavailable, {$e->getMessage()}");

    $data = ['status' => 'error', 'refresh' => LISTENERS_ERROR_TTL];
    $pawtunes->cache->set($cacheKey, $data + ['fetched' => time()], LISTENERS_ERROR_TTL);
    $result = [$data, 200, LISTENERS_ERROR_TTL];

} finally {

    // Only delete the lock while it is still ours (see handler.php for the reasoning)
    if ($pawtunes->cache->peek($lockKey) === $lockToken) {
        $pawtunes->cache->delete($lockKey);
    }

}

outputListeners($result[0], $result[1], $result[2]);
