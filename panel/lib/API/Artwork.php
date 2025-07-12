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

use lib\ImageResize;

class Artwork extends Base
{

    /**
     * @var string[]
     */
    private array $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];


    public function artworkLookup()
    {
        $text = strip_tags($_GET['text']);
        $skip = isset($_GET['ignore_cache']) && (bool) $_GET['ignore_cache'] === true;

        $art = $this->pawtunes->getArtwork($text, '', '', $skip);
        if ( ! $art) {
            http_response_code(404);
            exit;
        }

        $response['artwork'] = $art;
        $this->sendJSON($response);
    }


    public function getArtwork()
    {
        // Read list of files
        $files = $this->pawtunes->browse('data/images/');

        $response = [];
        if (count($files) >= 1) {

            // Loop
            foreach ($files as $file) {

                // Skip logo files
                if (preg_match('/^logo\.\d+/i', $file)) {
                    continue;
                }

                // Skip non image files
                if ( ! preg_match('/\.(jpe?g|png|webp|svg)$/i', $file)) {
                    continue;
                }

                // Create array of files to respond with
                $response[] = [
                    'name' => $this->pawtunes->extDel(basename($file)),
                    'path' => "data/images/{$file}",
                    'size' => $this->pawtunes->formatBytes(filesize("data/images/{$file}")),
                ];

            }

            if (count($response) >= 1) {
                ksort($response);
            }

        }

        $this->sendJSON($response ?? []);
    }


    public function deleteArtwork()
    {
        $this->sendJSON(['success' => $this->panel->deleteArtwork($_GET['name'])]);
    }


    public function importArtwork()
    {
        header('Content-Type: text/event-stream');
        flush();

        // Check if path is specified
        if (empty($_GET['path'])) {

            $this->sendSSE('<span class="text-red">You did not specify an import path, unable to continue...</span>');
            $this->closeSSE();

        }

        // Get protocol
        if (preg_match('/ftps?:\/\//i', $_GET['path'])) {

            $this->importFTPArtwork($_GET['path']);
            $this->closeSSE();

        }

        // Default
        $this->importLocalArtwork($_GET['path']);
    }


    private function importFTPArtwork($path)
    {
        if ( ! filter_var($path, FILTER_VALIDATE_URL)) {
            $this->sendSSE('<span class="text-red">The provided FTP Address does not appear to be valid!</span>');
            $this->closeSSE();
        }

        // Before we actually start looping and doing all kinds of stuff, we need to verify connection
        if ($handle = @opendir($path)) {

            // Handle was ok, show import message
            $this->sendSSE('FTP Connected! Attempting to import images, this may take a while...');

            // Loop now
            while (false !== ($file = readdir($handle))) {

                if ( ! in_array($this->pawtunes->extGet($file), $this->allowed, true)) {

                    $this->sendSSE(
                        '<span class="text-red">Unable to import file <b>"'.str_replace("'", '', basename($file)).'"</b> because it is not an image file!</span>'
                    );

                } else {

                    // New file name & path
                    $new_file     = "data/images/".$this->pawtunes->parseTrack(basename($file));
                    $new_img_data = file_get_contents("{$path}/{$file}");

                    // Attempt copy
                    if (strlen($new_img_data) > 10 && file_put_contents($new_file, $new_img_data)) {

                        $size = "{$this->pawtunes->config( 'images_size' )}x{$this->pawtunes->config( 'images_size' )}";
                        ImageResize::handle($new_file, $size, 'crop');
                        $this->sendSSE(
                            '<span class="text-green">'.str_replace("'", '', basename($file)).' successfully imported.</span>'
                        );

                    } else {

                        $this->sendSSE(
                            '<span class="text-red">Unable to import file <b>"'.str_replace("'", '', basename($file)).'"</b> - UNKNOWN ERROR!</span>'
                        );

                    }

                }

            }

            $this->sendSSE('Artwork import process has been completed!');
            closedir($handle);

        } else {

            $last_error = error_get_last();
            $this->sendSSE('<span class="text-red">FTP Connection failed!<br>Details: '.$last_error['message'].'</span>');

        }

        $this->closeSSE();
    }


    private function importLocalArtwork($path)
    {
        $lock_local = realpath(getcwd());
        $directory  = realpath($lock_local.'/'.$path);

        // Does folder exist?
        if ( ! is_dir($directory)) {

            $this->sendSSE('<span class="text-red">Specified directory does not exist or it\'s not readable!</span>');
            $this->closeSSE();

        }

        // We are limiting imports to player directory
        if (strpos($directory, $lock_local) !== 0) {

            $this->sendSSE('<span class="text-red">You are not allowed to import images from directory other than where the script is located!</span>');
            $this->closeSSE();

        }

        // Read specified directory for files
        $import = $this->pawtunes->browse("{$directory}/");
        if (count($import) < 1) {

            $this->sendSSE('<span class="text-red">Unable to find any files located in the specified folder!</span>');
            $this->closeSSE();

        }

        // Show message
        $this->sendSSE('Found <b>'.count($import).'</b> files, attempting to import them, this may take a while...');

        // Loop
        foreach ($import as $file) {

            if ( ! in_array($this->pawtunes->extGet($file), $this->allowed, true)) {

                $this->sendSSE(
                    '<span class="text-red">Unable to import file <b>"'.str_replace("'", '', basename($file)).'"</b> because it is not an image file!</span>'
                );

            } else {

                // New file name & path
                $new_file = "{$lock_local}/data/images/".$this->pawtunes->parseTrack(basename($file));

                // Attempt copy
                if (is_file($new_file)) {

                    $this->sendSSE(
                        '<span class="text-green">'.str_replace("'", '', basename($this->pawtunes->extDel($file))).' already exists, skipping...</span>');

                } elseif (copy("{$directory}/{$file}", $new_file)) {

                    $size = "{$this->pawtunes->config( 'images_size' )}x{$this->pawtunes->config( 'images_size' )}";
                    ImageResize::handle($new_file, $size, 'crop');
                    $this->sendSSE(
                        '<span class="text-green">'.str_replace("'", '', basename($this->pawtunes->extDel($file))).' successfully imported.</span>'
                    );

                } else {

                    $this->sendSSE(
                        '<span class="text-red">Unable to import file <b>"'.str_replace("'", '', basename($file)).'"</b> - UNKNOWN ERROR!</span>'
                    );

                }

            }

        }

        $this->sendSSE('All non-existing image files were imported!');
        $this->closeSSE();
    }


    public function handleError($e)
    {
        // If error is thrown from importArtwork function
        if ($e->getTrace()[0]['function'] === 'importArtwork' || $e->getTrace()[1]['function'] === 'importArtwork') {

            $this->sendSSE('<b><span class="text-danger">FATAL ERROR: '.$e->getMessage().'</span></b>');
            $this->closeSSE();

        }

        header("Content-Type: application/json");
        $this->sendJSON(['error' => $e->getMessage()]);
    }

}