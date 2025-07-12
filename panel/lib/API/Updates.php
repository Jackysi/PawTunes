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

        if (empty($_GET['version'])) {

            $this->sendSSE('<div class="text-danger">No version specified, please try again.</div>');
            $this->closeSSE();

        }

        // Start
        file_put_contents($this->path.'/data/updates/lock', '');

        try {

            $this->downloadUpdate();
            $this->extractUpdate();

            $this->sendSSE("<div>Processing installation...</div>");
            $this->postUpdate($this->path);

            // Delete zip file & temp file
            $this->pawtunes::deleteFile($this->path.'/data/updates/update.zip');
            $this->pawtunes::deleteFile($this->path.'/data/updates/lock');

            $this->closeSSE();

        } catch (Exception $e) {

            $this->sendSSE('<div class="text-red"><b>ERROR</b>: '.$e->getMessage().'</div>');
            $this->sendSSE('<div class="text-red">UPDATE FAILED!</div>');
            $this->closeSSE();

        }
    }


    /**
     * Download the update with progress
     */
    private function downloadUpdate(): void
    {
        $this->sendSSE('Establishing connection to the update server...');

        // Setup fopen so we stream to disk
        $saveHandle = fopen($this->path.'/data/updates/update.zip', 'wb+');
        if ( ! $saveHandle) {

            $this->pawtunes::deleteFile($this->path.'/data/updates/lock');
            $this->sendSSE('<div class="text-red">Saving an update file failed, it\'s possible that directory <b>/data/update/</b> is not writable!</div>');
            $this->closeSSE();

        }

        // Show initial message
        $this->sendSSE('<div>Connecting to the download server... (<span class="progress-status progress">0</span>%)</div>');

        // Track last sent percent
        $lastSentPercent = 0;
        $this->pawtunes->get(
            str_replace('{version}', $_GET['version'] ?? null, $this->panel->updateDownloadURL),
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

            $this->pawtunes::deleteFile($this->path.'/data/updates/lock');
            $this->sendSSE('<div class="text-red">Downloading the latest update failed! '.$curl_error.'.</div>');
            $this->closeSSE();

        }
    }


    /**
     * @return void
     */
    private function extractUpdate(): void
    {
        $this->sendSSE("<div>Extracting update... (<span class=\"progress-status progress\">0</span>%)</div>");

        // If ZipArchive is missing in the system, use splitbrains pure Zip implementation
        if ( ! class_exists('ZipArchive')) {

            $this->sendSSE("<div>Failed to extract update, PHP ZIP Extension is missing!</div>");
            $this->closeSSE();

            return;

        }

        // Initiate extract
        $zip   = new ZipArchive;
        $files = $zip->open($this->path.'/data/updates/update.zip');        // Open update zip

        if ($files !== true) {

            $this->sendSSE('<div class="text-red">Unable to read the downloaded update files!</div>');
            $this->sendSSE('<div class="text-red">UPDATE FAILED!</div>');
            $this->closeSSE();

        } else {

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
        if (is_file($this->path.'/data/updates/lock')) {
            $this->pawtunes::deleteFile($this->path.'/data/updates/lock');
        }

        $this->sendSSE('<b><span class="text-danger">FATAL ERROR: '.$e->getMessage().'</span></b>');
        $this->closeSSE();
    }


    /**
     * @throws \JsonException
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