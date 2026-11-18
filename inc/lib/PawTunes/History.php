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

namespace lib\PawTunes;

use lib\PawTunes;

/**
 * Track history kept by PawTunes itself, one list per channel, in the cache.
 *
 * Every track info fetch (a listener's poll or cron.php) passes through record(): the track on air is
 * added when it changed, and a history the stream server sent along (Shoutcast, AzuraCast, Custom) is
 * merged in. A new visitor therefore gets the full list at once, whatever the track info method, and
 * with cron.php the list also grows while nobody listens.
 *
 * record() runs only in the request that holds the track info refresh lock (inc/track-info.php), so
 * a channel's list has one writer at a time and needs no lock of its own.
 */
final class History
{

    /**
     * Entries kept while the "historyLength" setting is missing or invalid
     */
    public const DEFAULT_LENGTH = 20;

    /**
     * Upper limit of the "historyLength" setting: the whole list is part of every track info response
     */
    public const MAX_LENGTH = 200;

    /**
     * Seconds a list survives without a track change (a channel that was removed, a station that is off)
     */
    private const TTL = 604800;


    /**
     * @param  PawTunes  $pawtunes
     *
     * @return int  The configured number of entries to keep
     */
    public static function length(PawTunes $pawtunes): int
    {
        $length = (int) $pawtunes->config('historyLength');

        return ($length >= 1) ? min($length, self::MAX_LENGTH) : self::DEFAULT_LENGTH;
    }


    /**
     * Add a fresh provider answer to the channel's stored history and return the list, newest first.
     *
     * @param  PawTunes  $pawtunes
     * @param  int|string  $chnKey  Channel index (cache keys use it)
     * @param  array  $channel
     * @param  array  $info  What the provider returned: artist, title, artwork_override, optional history[]
     *
     * @return array  [['artist', 'title', 'time' (ms), 'artwork_override'], ...]
     */
    public static function record(PawTunes $pawtunes, $chnKey, array $channel, array $info): array
    {
        $key    = 'stream.history.'.$chnKey;
        $stored = $pawtunes->cache->get($key);

        // Cache keys use the channel index, which a channel delete or reorder hands to another channel:
        // a list recorded for a different name is not this channel's history
        $tracks = (is_array($stored) && ($stored['channel'] ?? null) === $channel['name'] && is_array($stored['tracks'] ?? null))
            ? $stored['tracks']
            : [];

        // The default track means "nothing known" (stream offline, a WebSocket channel), not a played song
        $current = null;
        $artist  = (string) ($info['artist'] ?? '');
        $title   = (string) ($info['title'] ?? '');
        if ($artist !== '' && $title !== '' && ($artist !== $pawtunes->config('artist_default') || $title !== $pawtunes->config('title_default'))) {
            $current = ['artist' => $artist, 'title' => $title, 'artwork_override' => $info['artwork_override'] ?? null];
        }

        $provider = is_array($info['history'] ?? null) ? $info['history'] : [];
        $merged   = self::merge(array_merge($tracks, $provider), $current, (int) round(microtime(true) * 1000), self::length($pawtunes));

        // Most fetches find the same track still on air: no write then
        if ($merged !== $tracks) {
            $pawtunes->cache->set($key, ['channel' => $channel['name'], 'tracks' => $merged], self::TTL);
        }

        return $merged;
    }


    /**
     * Build one history from entries of any origin (stored ones, the stream server's) and the track on air.
     *
     * The same play usually arrives twice with different times: the stream server knows when the track
     * started, PawTunes only when it first saw it. Sorted by time those two are neighbours, so neighbours
     * with the same artist and title count as one play and the earliest time is kept.
     *
     * @param  array  $entries  [['artist', 'title', 'time', 'artwork_override'?], ...] in any order
     * @param  array|null  $current  ['artist', 'title', 'artwork_override'] of the track on air, null for none
     * @param  int  $now  Unix time in milliseconds
     * @param  int  $limit  Entries to keep
     *
     * @return array  Newest first
     */
    public static function merge(array $entries, ?array $current, int $now, int $limit): array
    {
        $list = [];
        foreach ($entries as $entry) {

            // An entry without a time cannot be placed
            if ( ! is_array($entry) || ! isset($entry['artist'], $entry['title']) || ! is_numeric($entry['time'] ?? null) || $entry['time'] <= 0) {
                continue;
            }

            // Custom sources may send seconds; anything this small as milliseconds would be a date in 1973
            $time = (int) $entry['time'];
            if ($time < 100000000000) {
                $time *= 1000;
            }

            $list[] = [
                'artist'           => (string) $entry['artist'],
                'title'            => (string) $entry['title'],
                'time'             => $time,
                'artwork_override' => $entry['artwork_override'] ?? null,
            ];

        }

        // Oldest first (a stable sort: entries with the same time keep their order)
        usort($list, static function (array $a, array $b): int {
            return $a['time'] <=> $b['time'];
        });

        $out = [];
        foreach ($list as $entry) {

            $last = count($out) - 1;
            if ($last >= 0 && self::sameTrack($out[$last], $entry)) {

                // Same play seen twice: the earlier entry stays, an artwork only the later one has is kept
                $out[$last]['artwork_override'] = $out[$last]['artwork_override'] ?? $entry['artwork_override'];
                continue;

            }

            $out[] = $entry;

        }

        // The track on air goes on top unless it is there already. Never earlier than the newest entry:
        // a stream server whose clock runs ahead would otherwise sort above it, and the track would be
        // added again on every fetch.
        $last = count($out) - 1;
        if ($current !== null && ($last < 0 || ! self::sameTrack($out[$last], $current))) {
            $out[] = [
                'artist'           => (string) $current['artist'],
                'title'            => (string) $current['title'],
                'time'             => ($last < 0) ? $now : max($now, $out[$last]['time'] + 1),
                'artwork_override' => $current['artwork_override'] ?? null,
            ];
        }

        return array_slice(array_reverse($out), 0, max(1, $limit));
    }


    /**
     * @param  array  $a
     * @param  array  $b
     *
     * @return bool  Same artist and title, ignoring case and surrounding spaces
     */
    private static function sameTrack(array $a, array $b): bool
    {
        return strcasecmp(trim($a['artist']), trim($b['artist'])) === 0
            && strcasecmp(trim($a['title']), trim($b['title'])) === 0;
    }

}