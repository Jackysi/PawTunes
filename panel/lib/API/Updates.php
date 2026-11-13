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

namespace API;

use Exception;
use JsonException;
use RuntimeException;
use ZipArchive;

class Updates extends Base
{

    /**
     * @var false|string
     */
    public $path;

    /**
     * @var string
     */
    protected string $sseName = 'update';

    /**
     * Files to skip when extracting the release
     *
     * @var string[]
     */
    private array $skipFiles
        = [
            'inc/config/general.php'  => true,
            'inc/config/channels.php' => true,
            'data/images/default.png' => true,
        ];


    /**
     * @param $pawtunes
     * @param $panel
     */
    public function __construct($pawtunes, $panel)
    {
        parent::__construct($pawtunes, $panel);
        $this->path = realpath(getcwd());
    }


    /**
     * @return void
     */
    public function __invoke()
    {
        header('Content-Type: text/event-stream');
        flush();

        if (is_file($this->path.'/data/updates/lock')) {

            $this->sendSSE('<div class="text-danger">Update already in progress, please try again later or delete <strong>"/data/updates/lock"</strong> file!</div>');
            $this->closeSSE();

        }

        if ( ! is_writable($this->path.'/data/updates/')) {

            $this->sendSSE('<div class="text-danger">Unable to write to <strong>"./data/updates/"</strong> folder!</div>');
            $this->closeSSE();

        }

        // The version becomes part of the download URL, so only a plain release tag (1.2.3 or v1.2.3) is accepted
        $version = (string) ($_GET['version'] ?? '');
        if ( ! preg_match('/^v?\d+\.\d+\.\d+$/', $version)) {

            $this->sendSSE('<div class="text-danger">No valid version specified, please try again.</div>');
            $this->closeSSE();

        }

        // Start
        file_put_contents($this->path.'/data/updates/lock', '');

        // closeSSE() exits, which skips finally blocks, and a fatal error skips them too. A shutdown function runs
        // in every case, so the lock (and a partial or bogus download) can never block the next update attempt.
        // Registered only after this request created the lock, so it never removes another request's lock.
        $updatesDir = $this->path.'/data/updates';
        register_shutdown_function(static function () use ($updatesDir) {
            foreach (['update.zip', 'lock'] as $file) {
                if (is_file("{$updatesDir}/{$file}")) {
                    @unlink("{$updatesDir}/{$file}");
                }
            }
        });

        try {

            $this->downloadUpdate($version);
            $this->extractUpdate();

            $this->sendSSE("<div>Processing installation...</div>");
            $this->postUpdate($this->path);

        } catch (Exception $e) {

            $this->sendSSE('<div class="text-red"><b>ERROR</b>: '.$this->escape($e->getMessage()).'</div>');
            $this->sendSSE('<div class="text-red">UPDATE FAILED!</div>');

        }

        $this->closeSSE();
    }


    /**
     * Download the update with progress
     *
     * @param  string  $version  Validated release tag
     *
     * @throws RuntimeException
     */
    private function downloadUpdate(string $version): void
    {
        $this->sendSSE('Establishing connection to the update server...');

        // Setup fopen so we stream to disk
        $zipFile    = $this->path.'/data/updates/update.zip';
        $saveHandle = fopen($zipFile, 'wb+');
        if ( ! $saveHandle) {
            throw new RuntimeException('Saving an update file failed, it\'s possible that directory /data/updates/ is not writable!');
        }

        // Show initial message
        $this->sendSSE('<div>Connecting to the download server... (<span class="progress-status progress">0</span>%)</div>');

        // Track last sent percent
        $lastSentPercent = 0;
        $this->pawtunes->get(
            str_replace('{version}', $version, $this->panel->updateDownloadURL),
            null,
            null,
            function ($total, $now) use (&$lastSentPercent) {
                $progress = ($now > 0 && $total > 0) ? floor(($now / $total) * 100) : 0;
                if ($progress > $lastSentPercent) {

                    $this->sendSSE("<div>Downloading the latest update... (<span class=\"progress-status progress\">{$progress}</span>%)</div>");
                    $lastSentPercent = $progress;

                }
            },
            0,
            $curl_error,
            [
                CURLOPT_FILE           => $saveHandle,
                CURLOPT_RETURNTRANSFER => false,
            ]
        );

        fclose($saveHandle);

        if ( ! empty($curl_error)) {
            throw new RuntimeException('Downloading the latest update failed! '.$curl_error.'.');
        }

        // HttpClient saves the body for any HTTP status, so an error page would otherwise be "extracted"
        $status = $this->pawtunes->http->getLastStatus();
        if ($status !== 200) {
            throw new RuntimeException('The update server responded with HTTP status '.($status ?? 'unknown').' instead of the update file.');
        }

        // A 200 can still be something other than the release (a proxy or captive portal page): require the
        // ZIP local file header signature before anything is extracted
        $handle = fopen($zipFile, 'rb');
        $magic  = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($magic !== "PK\x03\x04") {
            throw new RuntimeException('The downloaded update is not a valid ZIP archive.');
        }
    }


    /**
     * @return void
     *
     * @throws RuntimeException
     */
    private function extractUpdate(): void
    {
        $this->sendSSE("<div>Extracting update... (<span class=\"progress-status progress\">0</span>%)</div>");

        if ( ! class_exists('ZipArchive')) {
            throw new RuntimeException('Failed to extract update, PHP ZIP Extension is missing!');
        }

        // Initiate extract
        $zip   = new ZipArchive;
        $files = $zip->open($this->path.'/data/updates/update.zip');        // Open update zip

        if ($files !== true) {
            throw new RuntimeException('Unable to read the downloaded update files!');
        }

        // Nothing is written before this check: a release that cannot run here must leave the install untouched
        $this->requireReleasePhp($zip);

        $total           = $zip->numFiles;
        $lastSentPercent = 0;
        for ($i = 0; $i < $total; $i++) {

            $tmp = $zip->getNameIndex($i);

            // Skip specific files
            if (isset($this->skipFiles[$tmp])) {

                unset($this->skipFiles[$tmp]);
                continue;

            }

            $zip->extractTo($this->path, [$tmp]);

            $file    = $i + 1;
            $percent = floor(($file / $total) * 100);

            // Notify progress on each file if below 200 files
            if ($percent >= $lastSentPercent + 1 || $file === $total) {

                $this->sendSSE("<div>Extracting update... (<span class=\"progress-status progress\">{$percent}</span>%)</div>");
                $lastSentPercent = $percent;

            }

        }

        $zip->close();

    }


    /**
     * Refuses a release whose composer.json needs a newer PHP than this server runs. Its vendor/ would fatal on the
     * first request (composer's platform check), taking the player and this panel down with no way to roll back.
     * The constraint's first version is the minimum (">=8.1", "^8.1", "~8.1.0"), which covers how PawTunes writes it.
     * A release without composer.json (older zips) is not checked.
     *
     * Kept PHP 7.4 compatible: this check runs in the updater of the release being replaced.
     *
     * @param  ZipArchive  $zip
     *
     * @throws RuntimeException
     */
    private function requireReleasePhp(ZipArchive $zip): void
    {
        $manifest = $zip->getFromName('composer.json');
        if ($manifest === false) {
            return;
        }

        $composer   = json_decode($manifest, true);
        $constraint = is_array($composer) ? (string) ($composer['require']['php'] ?? '') : '';

        if ( ! preg_match('/(\d+\.\d+(?:\.\d+)?)/', $constraint, $match)) {
            return;
        }

        if (version_compare(PHP_VERSION, $match[1], '<')) {
            throw new RuntimeException(
                "This release requires PHP {$match[1]} or newer, this server runs PHP ".PHP_VERSION.'. Nothing was changed: upgrade PHP first, then run the update again.'
            );
        }
    }


    public function postUpdate($path): void
    {
        // Trigger post update script
        if (file_exists($path.'/post-update.php')) {
            include $path.'/post-update.php';
        }

        $this->sendSSE('<b><span class="text-success">Completed successfully!</span></b>');
    }


    public function manualPostUpdate()
    {
        header('Content-Type: text/event-stream');
        flush();

        $this->postUpdate($this->path);
        $this->closeSSE();
    }


    public function handleError($e)
    {
        // The lock is released by the shutdown function registered in __invoke(). Deleting it here could remove
        // the lock of an update another request is still running (this request may never have created it).
        $this->sendSSE('<b><span class="text-danger">FATAL ERROR: '.$this->escape($e->getMessage()).'</span></b>');
        $this->closeSSE();
    }


    /**
     * @throws JsonException
     */
    public function checkForUpdates()
    {
        header("Content-Type: application/json");
        if ( ! $curl = $this->pawtunes->cache->get('releases')) {

            $curl = $this->pawtunes->get($this->panel->updateCheckURL, null, false, false, 10, $err);

            if ( ! empty($err)) {
                $curl = json_encode(['error' => true, 'message' => $err], JSON_THROW_ON_ERROR);
            }

            // 10min cache
            $this->pawtunes->cache->set('releases', $curl, 60 * 10);

        }

        echo $curl;
    }


    public function getHistory()
    {
        header("Content-Type: text/plain");
        if ( ! is_file(realpath(getcwd()).'/CHANGELOG.md')) {
            echo "";
            exit();
        }

        // Fix line breaks
        $contents = file_get_contents(realpath(getcwd()).'/CHANGELOG.md');
        echo str_replace("\r", "", $contents);
    }

}