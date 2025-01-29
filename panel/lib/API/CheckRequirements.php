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

use RuntimeException;

class CheckRequirements extends Base
{

    /**
     * @throws \JsonException
     */
    public function __invoke()
    {
        // First attempt to create missing folders
        $folders = [$this->pawtunes->config('cache')['path'], 'data/images', 'data/logs', 'data/updates'];
        $created = [];
        foreach ($folders as $folder) {

            if ( ! is_dir($folder)) {

                if ( ! mkdir($folder, 0755, true) && ! is_dir($folder)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $folder));
                }

                // Copy default artwork
                if ($folder === 'data/images') {
                    $artworkFolder = true;
                    copy("assets/img/default.png", "data/images/default.png");
                }

                $created[] = $folder;

            }

        }

        // Created folders messages
        if (count($created) >= 1) {
            $spelling   = ((count($created) > 1) ? 'were' : 'was');
            $response[] = [
                'type'    => 'info',
                'message' => "Folder(s) \"".implode('", "', $created)."\" {$spelling} missing and {$spelling} created automatically.",
            ];
        }

        // Find default artwork
        $extensions = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
        $artwork    = false;
        foreach ($extensions as $ext) {
            if (is_file("data/images/default.{$ext}")) {
                $artwork = "default.{$ext}";
                break;
            }
        }

        // Check if default artwork exists
        if ($artwork === false && ! isset($artworkFolder)) {
            $response[] = ['type' => 'warning', 'message' => 'PawTunes does not have **default ARTWORK** installed! Please use artwork manager and upload artist image called **"default"**.'];
        }

        // PHP Version
        if (PHP_VERSION < 7.0) {
            $response[] = ['type' => 'warning', 'message' => 'The server is running **PHP '.PHP_VERSION.'** while this app requires at least **PHP 7.0** or above!'];
        }

        // Simple XML
        if ( ! function_exists('simplexml_load_string')) {
            $response[] = ['type' => 'warning', 'message' => 'PHP extension **"SimpleXML" ** could not be detected, this will cause SERIOUS issues with the player!'];
        }

        // CURL
        if ( ! function_exists('curl_version')) {
            $response[] = ['type' => 'warning', 'message' => 'PHP extension **"CURL"** is not enabled! This script does not work without the extension!'];
        }

        // Simple XML
        if ( ! extension_loaded('gd')) {
            $response[] = ['type' => 'warning', 'message' => 'PHP extension  **"GD"** could not be detected, this will cause issues with Artworks caching and uploading!'];
        }

        // Cache
        if ( ! is_writable($this->pawtunes->config('cache')['path'])) {
            $response[] = ['type' => 'warning', 'message' => 'Directory **'.$this->pawtunes->config('cache')['path'].'** is not writable! This will cause extreme slow performance! You can fix this issue by setting **chmod** of folder **'.$this->pawtunes->config('cache')['path'].'** to **755**.'];
        }

        // Images folder
        if ( ! is_writable("data/images")) {
            $response[] = ['type' => 'warning', 'message' => 'Directory **/data/images/** is not writable! You will not be able to upload custom artist images or channel logo(s)! You can fix this issue by setting **chmod** of folder **/data/images/** to **755**.'];
        }

        // Logs folder
        if ( ! is_writable("data/logs")) {
            $response[] = ['type' => 'warning', 'message' => 'Directory **/data/logs/** is not writable! This means that player will not be able to write error log! You can fix this issue by setting **chmod** of folder **/data/logs/** to **755**.'];
        }

        // Present error logs
        if ($this->pawtunes->config('debugging') !== 'disabled' && is_file('data/logs/player_errors.log')) {
            $response[] = ['type' => 'log-warning', 'message' => 'ERROR log file is present, please check it out or delete it!'];
        }

        // Upgrade not completed
        if (( ! $this->pawtunes->config('development') || $this->pawtunes->config('development') !== true) && is_file("post-update.php")) {
            $response[] = ['type' => 'finish-upgrade', 'message' => 'Upgrade was partially completed!'];
        }

        $this->sendJSON($response ?? []);
    }

}