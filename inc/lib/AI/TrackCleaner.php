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

namespace lib\AI;

use lib\PawTunes;
use Throwable;

/**
 * AI Assistant's "Fix unreadable track titles": stream titles that the Track RegEx cannot split get a second
 * chance with the language model instead of showing the default artist and title.
 *
 * Cost stays bounded: each distinct raw text is asked about once, and the answer (also "not a song") is cached
 * for 30 days. Only text from the configured stream server reaches this, never listener input.
 */
final class TrackCleaner
{

    private const CACHE_TTL = 30 * 86400;

    /**
     * After a failed call (timeout, API down, bad key) the same text is retried after 10 minutes, not every poll
     */
    private const RETRY_AFTER = 600;

    /**
     * Marks a call in flight, so listeners polling at the same moment do not each start one
     */
    private const PENDING_TTL = 30;

    private const NOT_MUSIC = 'not-music';

    private const FAILED = 'failed';

    private const PENDING = 'pending';


    /**
     * @param  PawTunes  $pawtunes
     * @param  string  $text  Raw stream title the regex could not split
     *
     * @return array|null ['artist' => ..., 'title' => ...], or null (feature off, not a song, failure)
     */
    public static function split(PawTunes $pawtunes, string $text): ?array
    {
        $config = $pawtunes->config('ai')['text'] ?? [];
        if (empty($config['enabled']) || empty($config['clean_metadata']) || trim($text) === '') {
            return null;
        }

        $key    = 'ai.track.'.md5($text);
        $cached = $pawtunes->cache->get($key);

        if (is_array($cached)) {
            return $cached;
        }

        if ($cached !== false) {
            return null; // not a song, recently failed, or another request is asking right now
        }

        // Fail closed: if the cache cannot remember that we asked, every poll would ask again (and cost again)
        if ( ! $pawtunes->cache->set($key, self::PENDING, self::PENDING_TTL)) {
            return null;
        }

        try {

            $model = Providers::textModel($pawtunes);
            $track = ($model !== null) ? (new Assistant($model))->splitTrack($text) : null;

            $pawtunes->cache->set($key, $track ?? self::NOT_MUSIC, self::CACHE_TTL);

            return $track;

        } catch (Throwable $e) {

            $pawtunes->writeLog('player_errors', 'AI Assistant could not read a stream title: '.$e->getMessage());
            $pawtunes->cache->set($key, self::FAILED, self::RETRY_AFTER);

            return null;

        }
    }

}
