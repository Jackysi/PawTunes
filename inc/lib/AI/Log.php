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
 * AI log lines in player_errors.log (the panel's log viewer), one prefix per feature: "AI Artwork: ...".
 * Two levels, following the "debugging" setting:
 *  - error(): failures and issues an admin must act on (bad key, provider down, limit reached). Written while
 *    logging is on ("log-only" and "enabled"), with the stack trace only under "enabled".
 *  - debug(): why a step was skipped or what a failed request looked like. Only under "enabled".
 */
final class Log
{

    public static function error(PawTunes $pawtunes, string $feature, string $message, ?Throwable $e = null): void
    {
        $pawtunes->writeLog('player_errors', "{$feature}: {$message}");

        // Where it was thrown and the call path to it: which step (search, concept, drawing, panel test) failed, and
        // the file and line of anything unexpected (a TypeError, a GD error)
        if ($e !== null && self::verbose($pawtunes)) {
            $pawtunes->writeLog('player_errors', "{$feature}: ".get_class($e)." in {$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}");
        }
    }


    public static function debug(PawTunes $pawtunes, string $feature, string $message): void
    {
        if (self::verbose($pawtunes)) {
            $pawtunes->writeLog('player_errors', "{$feature}: {$message}");
        }
    }


    /**
     * Debugging "enabled": callers check this before building an expensive debug message
     */
    public static function verbose(PawTunes $pawtunes): bool
    {
        return $pawtunes->config('debugging') === 'enabled';
    }

}
