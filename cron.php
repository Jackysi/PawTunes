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
 * Cron job: keeps the track history complete while nobody has the player open.
 *
 * The history grows whenever track info is fetched, and without listeners nobody fetches it. Run this
 * once a minute, from the command line or by URL:
 *
 *   * * * * * php /path/to/pawtunes/cron.php > /dev/null 2>&1
 *   * * * * * curl -s https://example.com/player/cron.php > /dev/null
 *
 * On Windows the Task Scheduler runs it (Command Prompt as Administrator):
 *
 *   schtasks /Create /TN "PawTunes Cron" /SC MINUTE /MO 1 /RU SYSTEM /TR "\"C:\php\php.exe\" \"C:\path\to\pawtunes\cron.php\""
 *
 * From the command line, run it as the web server's user: disk cache files created by another user
 * (root) cannot be updated by the player afterwards.
 *
 * It asks for each channel's track info exactly like a listener's player does (inc/track-info.php), so
 * a channel a listener fetched within the refresh interval is answered from the cache and its stream
 * server is not contacted again. That is also why the URL needs no password: it can do nothing a
 * visitor polling the player could not.
 */

chdir(__DIR__);

require 'vendor/autoload.php';
require 'inc/track-info.php';

use lib\PawTunes;

$pawtunes = new PawTunes();

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
}

// Same logging rules as the player (index.php); nothing is ever displayed, the output is the report below
error_reporting(($pawtunes->config('debugging') !== 'enabled') ? E_ALL & ~E_NOTICE : E_ALL);
ini_set('log_errors', $pawtunes->config('debugging') !== 'disabled');
ini_set('error_log', getcwd().'/data/logs/player_errors.log');
ini_set('display_errors', '0');

// Several channels with slow stream servers may take longer than the web server waits for us
ignore_user_abort(true);
set_time_limit(0);

if ($pawtunes->config('history') !== true) {
    exit("Track History is turned off (panel: Settings), nothing to do.\n");
}

foreach ($pawtunes->getChannels() as $chn_key => $channel) {

    if ( ! is_array($channel) || ! in_array($channel['stats']['method'] ?? '', STREAM_INFO_METHODS, true)) {
        continue;
    }

    try {

        [$info] = trackInfo($pawtunes, $chn_key, $channel);

        if ( ! isset($info['artist'], $info['title'])) {
            $result = 'no track info (see data/logs/player_errors.log)';
        } elseif ( ! empty($info['cache']['retry'])) {
            $result = 'stream server did not answer, last known track kept (see data/logs/player_errors.log)';
        } elseif ( ! empty($info['cache']['status'])) {
            $result = 'skipped, fetched '.max(0, time() - trackInfoFetchedAt($info)).' s ago';
        } else {
            $result = "{$info['artist']} - {$info['title']}";
        }

    } catch (Throwable $e) {

        // One broken channel must not stop the others
        $pawtunes->writeLog('player_errors', "{$channel['name']}: cron: {$e->getMessage()}");
        $result = 'failed (see data/logs/player_errors.log)';

    }

    echo "{$channel['name']}: {$result}\n";

}
