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
 * Track info of one channel: the cached copy, or a fresh one from the channel's track info method.
 * Shared by the player API (inc/handler.php) and the cron job (cron.php), so both obey the same
 * cache and the same refresh lock.
 */

use lib\AI\ArtworkGenerator;
use lib\PawException;
use lib\PawTunes\History;

/**
 * Track info methods with a provider class in lib\PawTunes\StreamInfo. Anything else is "disabled".
 */
const STREAM_INFO_METHODS = ['azuracast', 'centovacast', 'custom', 'direct', 'icecast', 'icecast-public', 'sam', 'shoutcast', 'shoutcast-public'];

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
 * Artwork of a track as the API returns it: resolved now, or a lazy URL the browser resolves later
 *
 * @param  lib\PawTunes  $pawtunes
 * @param  array  $track  artist, title, optional artwork_override
 *
 * @return string|null
 */
function trackInfoArtwork($pawtunes, array $track): ?string
{
    if ($pawtunes->config('artwork_lazy_loading') === true) { // Fast
        return $pawtunes->lazyArtworkURL($track['artist'], $track['title'], $track['artwork_override'] ?? '');
    }

    return $pawtunes->getArtwork( // Slow
        $track['artist'],
        ( ! $pawtunes->config('artist_images_only')) ? $track['title'] : '',
        $track['artwork_override'] ?? ''
    );
}

/**
 * Get the track info of a channel, calling its provider only when the cached copy is no longer fresh
 * and no other request is refreshing it already.
 *
 * @param  lib\PawTunes  $pawtunes
 * @param  int|string  $chn_key  Channel index (cache keys use it)
 * @param  array  $channel
 *
 * @return array{0:array,1:int}  [track info, seconds an HTTP response carrying it may be reused]
 */
function trackInfo($pawtunes, $chn_key, array $channel): array
{
    // Cache related info
    $info['cache']['status']  = false;
    $info['cache']['date']    = date('Y-m-d H:i:s');
    $info['cache']['fetched'] = time();
    $info['cache']['refresh'] = (($pawtunes->config('stats_refresh') - 1) <= 1) ? 10 : ($pawtunes->config('stats_refresh') - 1);

    // HTTP caching: how long this response may be reused, decided below
    $maxAge = $info['cache']['refresh'];

    /* Disabled, simply return defaults
    ============================================================================================== */
    if ( ! in_array($channel['stats']['method'], STREAM_INFO_METHODS, true)) {

        // Log if not disabled
        if ($channel['stats']['method'] !== 'disabled') {
            $pawtunes->writeLog('errors', "{$channel['name']}: Invalid method! This is truly fancy error which should have never happened!");
        }

        // Disabled or ERROR occurred
        return [
            [
                'artist' => $pawtunes->config('artist_default'),
                'title'  => $pawtunes->config('title_default'),
                'image'  => $pawtunes->getArtwork(null),
                'status' => 'disabled',
            ],
            $maxAge,
        ];

    }

    /* Now do the heavy work, use configured method to get stats information
    ============================================================================================== */
    $cacheKey = 'stream.info.'.$chn_key;
    $cached   = $pawtunes->cache->get($cacheKey);

    // A negative marker (stored by the catch below) records a recent provider failure. It is not
    // servable as track data, only as the error shape, so it is kept apart from a real copy.
    $hasCopy = is_array($cached) && empty($cached['error']);
    $fresh   = is_array($cached) && trackInfoFreshUntil($cached) > time();

    // Fresh real copy: serve it, the response may be reused until the copy expires
    if ($hasCopy && $fresh) {

        $cached['cache']['status'] = true;

        return [$cached, trackInfoFreshUntil($cached) - time()];

    }

    // Fresh negative marker: the upstream failed moments ago. Keep returning the error shape without
    // calling it again, so sequential polls do not hammer a dead server between refreshes.
    if ($fresh && ! $hasCopy) {
        return [$info, max(1, trackInfoFreshUntil($cached) - time())]; // $info is still the cache-only error shape set above
    }

    // Not fresh (stale, an expired marker, or a cold start): exactly one request refreshes it (the one
    // that wins the lock), everyone else serves the stale copy if there is one, or a short error shape.
    // The lock covers the cold path too, so a dead upstream with no copy is hit by one request, not all.
    $lockKey   = $cacheKey.'.lock';
    $lockToken = bin2hex(random_bytes(8)); // owner tag, so we only delete the lock while it is still ours
    $locked    = $pawtunes->cache->add($lockKey, $lockToken, STREAM_INFO_LOCK_TTL);

    if ( ! $locked) {

        if ($hasCopy) {

            $cached['cache']['status'] = true;

            return [$cached, 1];

        }

        return [$info, STREAM_INFO_ERROR_MAX_AGE];

    }

    // Guess className required for specific method
    $className = "\\lib\\PawTunes\\StreamInfo\\".str_replace('-', '', ucwords($channel['stats']['method'], '-'));

    try {

        // Initiate class and get data
        $class = new $className($pawtunes, $channel);
        $info  += $class->getInfo();

        // AI Artwork may only draw artists this station really played, never names from a URL
        ArtworkGenerator::markPlayed($pawtunes, (string) ($info['artist'] ?? ''), (string) ($info['title'] ?? ''));

        // Track History on: the response carries the history PawTunes keeps for the channel, which holds
        // the stream server's history (when it sent one) and every track seen here. Nothing recorded yet
        // (a WebSocket channel, a stream that is off) leaves the provider's answer as it is.
        if ($pawtunes->config('history') === true && ($history = History::record($pawtunes, $chn_key, $channel, $info)) !== []) {
            $info['history'] = $history;
        }

        $info['artwork'] = trackInfoArtwork($pawtunes, $info);

        // History: same artwork treatment for every entry (slow unless artwork lazy loading is on)
        if (isset($info['history']) && count($info['history']) > 0) {
            foreach ($info['history'] as $key => $track) {

                $info['history'][$key]['artwork'] = trackInfoArtwork($pawtunes, $track);

                // Delete useless var
                unset($info['history'][$key]['artwork_override']);

            }
        }

        // Up next (AzuraCast queue, SAM queue): same artwork treatment as the current track
        if (isset($info['next'])) {

            $info['next']['artwork'] = trackInfoArtwork($pawtunes, $info['next']);
            unset($info['next']['artwork_override']);

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
        $maxAge = STREAM_INFO_ERROR_MAX_AGE;
        if ($hasCopy) {

            // The copy counts as fresh until "retry", but keeps its original expiry, so a long outage
            // still ends in the error shape once the copy is too old to be worth showing
            $info                    = $cached;
            $info['cache']['status'] = true;
            $info['cache']['retry']  = time() + STREAM_INFO_ERROR_MAX_AGE;

            $expiresIn = trackInfoFetchedAt($cached) + (int) $cached['cache']['refresh'] + STREAM_INFO_STALE_GRACE - time();
            if ($expiresIn > 0) {
                $pawtunes->cache->set($cacheKey, $info, $expiresIn);
            }

        } else {

            $negative = [
                'cache' => ['status' => false, 'date' => date('Y-m-d H:i:s'), 'fetched' => time(), 'refresh' => STREAM_INFO_ERROR_MAX_AGE],
                'error' => true,
            ];
            $pawtunes->cache->set($cacheKey, $negative, STREAM_INFO_ERROR_MAX_AGE);

        }

    } finally {

        // Only delete the lock while it is still ours: a holder that outlived the TTL must not remove
        // the lock a later refresher has just created
        if ($pawtunes->cache->get($lockKey) === $lockToken) {
            $pawtunes->cache->delete($lockKey);
        }

    }

    return [$info, $maxAge];
}
