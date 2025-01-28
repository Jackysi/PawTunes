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

namespace lib;

use RuntimeException;
use Throwable;

/**
 * Class Helpers - used across entire player for functionality
 */
abstract class Helpers
{

    /**
     * @var string|null
     */
    protected ?string $foundDefaultArtwork;


    /**
     * Get the current URL.
     *
     * @return string
     */
    public function currentURL(): string
    {
        // Detect protocol
        $protocol = ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';

        // Get host
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];

        // Build full URL
        $url = $protocol.$host.$_SERVER['REQUEST_URI'];

        return strtok($url, '?'); // Ex

    }


    /**
     * CURL wrap function to make life easier using one function, this is rather simple implementation
     *
     * @param  string  $url
     * @param  array|null  $post  To post this must be array of POST elements (NAME=>VALUE) instead of boolean
     * @param  string|null  $auth  To use HTTP Authorization, string should be passed in format (username:password)
     * @param  bool|callable  $progress  Anonymous function ( $resource, $download_total, $downloaded_so_far, $upload_total, $uploaded_so_far )
     * @param  int  $timeout  Self Explanatory
     * @param  string|int  $error  ERROR message will be returned here
     * @param  array  $options  You can pass custom CURL options via this param, by using existing param you will rewrite existing options
     * @param  boolean  $log  Wish to write to log? Default true
     *
     * @return bool|string
     */
    public function get(
        string $url,
        ?array $post = null,
        ?string $auth = null,
        $progress = false,
        int $timeout = 5,
        &$error = false,
        array $options = [],
        bool $log = true
    ) {

        // Create CURL Object
        $CURL = curl_init();

        // PawTunes defaults
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (PawTunes) AppleWebKit/537.36 (KHTML, like Gecko)',
            CURLOPT_FOLLOWLOCATION => ! ini_get('open_basedir'),
            CURLOPT_CONNECTTIMEOUT => (($timeout < 1 && $timeout !== 0) ? 5 : $timeout),
            //CURLOPT_REFERER        => $this->currentURL(),
            CURLOPT_CAINFO         => __DIR__.DIRECTORY_SEPARATOR.'bundle.crt',
        ];

        // Post data to the URL (expects array)
        if ($post !== null) {
            $opts += [
                CURLOPT_POSTFIELDS    => http_build_query($post, '', '&'),
                CURLOPT_POST          => true,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_FORBID_REUSE  => true,
            ];
        }

        // Use HTTP Authorization
        if ( ! empty($auth)) {
            $opts += [CURLOPT_USERPWD => $auth];
        }

        // Call anonymous $progress_function function
        if ( ! is_bool($progress)) {
            $opts += [
                CURLOPT_NOPROGRESS       => false,
                CURLOPT_PROGRESSFUNCTION => function ($resource, $downloaded, $download, $upload_total, $uploaded) use ($progress) {

                    // Normalize values for compatibility with older libcurl versions
                    if (is_numeric($resource)) {

                        $normalized_download_total = $resource;
                        $normalized_downloaded     = $downloaded;
                        $normalized_upload_total   = $upload_total;
                        $normalized_uploaded       = $uploaded;

                    } else {

                        $normalized_download_total = $downloaded;
                        $normalized_downloaded     = $download;
                        $normalized_upload_total   = $upload_total;
                        $normalized_uploaded       = $uploaded;

                    }

                    // Call the progress function with normalized values
                    return $progress($normalized_download_total, $normalized_downloaded, $normalized_upload_total, $normalized_uploaded);

                },
            ];
        }

        $opts = array_replace($opts, $options);

        // Before executing CURL pass options array to the session
        curl_setopt_array($CURL, $opts);

        // Finally execute CURL
        $data = curl_exec($CURL);

        // Parse ERROR
        if (curl_error($CURL)) {

            // This must be referenced in-memory variable
            $error = curl_error($CURL);

            // Only works when writeLog function is available
            if ($log && method_exists($this, 'writeLog')) {
                $this->writeLog('player_errors', "CURL Request \"{$url}\" failed! LOG: ".curl_error($CURL));
            }

        }

        // Close connection and return data
        curl_close($CURL);

        return $data;

    }


    /**
     *  Helper function to write log files
     *
     * @param        $file
     * @param        $text
     * @param  string  $path
     *
     * @return bool|int
     */
    public function writeLog($file, $text, string $path = 'data/logs/')
    {

        ## Logging is disabled!
        if ($this->settings['debugging'] === 'disabled') {
            return false;
        }

        ## Check if path is writable
        if (is_writable($path)) {
            return file_put_contents($path.$file.".log", "[".date("j.n.Y-G:i")."] {$text}\n", FILE_APPEND);
        }

        return false;

    }


    /**
     *  Get artist image & cache it (Uses additional function for obtaining image)
     *
     * @param        $artist
     * @param  string  $title
     * @param  string  $override
     * @param  bool  $skipCache
     *
     * @return string
     */
    public function getArtwork($artist, string $title = "", string $override = "", bool $skipCache = false): ?string
    {

        // List of sources
        $sources = $this->getSortedArtworkSourcesList();

        $found = null;
        foreach ($sources as $source) {
            try {

                if ( ! isset($this->artworkMethods[$source['method']])) {
                    continue;
                }

                $seeker = "lib\PawTunes\Artwork\\{$this->artworkMethods[ $source['method']]}";

                $art             = new $seeker($this);
                $art->path       = $this->artworkPath;
                $art->cachePath  = $this->settings['cache']['path'] ?? './data/cache';
                $art->extensions = $this->artworkExtensions;
                $artwork         = $art($artist, $title, $override, $skipCache);

                // Yeah! Found
                if ($artwork) {
                    $found = $artwork;
                    break;
                }

            } catch (Throwable $e) {

                $this->handleException($e);

            }
        }

        // Return path to compressed and cached image
        return ( ! $found) ? $this->defaultArtwork() : $found;

    }


    /**
     * @param  \Throwable  $e
     *
     * @return void
     */
    protected function handleException(Throwable $e): void
    {

        if ($this->config('debugging') !== 'disabled') {

            $this->writeLog('player_errors', "FATAL ERROR: {$e->getMessage()}");

            if ($this->config('debugging') === 'enabled') {
                $this->writeLog('player_errors', $e->getTraceAsString());
            }

        }

    }


    /**
     * @return string
     */
    protected function defaultArtwork(): ?string
    {

        // If already found, return
        if ($this->foundDefaultArtwork !== null) {
            return $this->foundDefaultArtwork;
        }

        // Find default
        foreach ($this->artworkExtensions as $ext) {
            if (is_file("{$this->artworkPath}/default.{$ext}")) {
                return $this->foundDefaultArtwork = "{$this->artworkPath}/default.{$ext}";
            }
        }

        return null;

    }


    /**
     * Simple function to parse XML files into arrays
     *
     * @param      $data
     * @param  bool  $lower
     *
     * @return array|mixed
     * @throws \JsonException
     */
    public function xml2array($data, bool $lower = false)
    {

        $vals = json_decode(
            json_encode((array) simplexml_load_string($data), JSON_THROW_ON_ERROR), true, 512,
            JSON_THROW_ON_ERROR
        );

        // Lower / Uppercase array keys
        if ($lower === true && is_array($vals)) {
            return array_change_key_case($vals);
        }

        return $vals;

    }


    /**
     * Function to replace {{$VARIABLE}} placeholders with corresponding values from an array.
     * Supports nested variables using dot notation, e.g., {{$VAR.DEEPER.DEEPER}}.
     *
     * @param  string  $content  The content containing placeholders.
     * @param  array  $array  The array of variables to replace.
     * @param  bool  $uppercase  Whether to convert array keys to uppercase.
     *
     * @return string The content with placeholders replaced.
     * @throws \Exception
     */
    public function template(string $content, array $array, bool $uppercase = false): string
    {

        // Optionally change array keys to uppercase
        if ($uppercase) {
            $array = $this->arrayKeysToUppercase($array);
        }

        // Match all placeholders in the content
        $replace_count = preg_match_all("/{{\\$(.*?)}}/", $content, $matches);

        // Loop over all matches
        for ($i = 0; $i < $replace_count; $i++) {

            // Extract the variable name inside {{$ and }}
            $full_match    = $matches[0][$i];    // The full match including {{$ and }}
            $variable_path = $matches[1][$i]; // The variable path inside the placeholder

            // Get the variable value from the array
            $variable = $this->getNestedValue($array, $variable_path, $uppercase);

            // Replace in content if variable is found
            if (isset($variable)) {
                $content = str_replace($full_match, $variable, $content);
                continue;
            }

            if ($this->settings['debugging'] === 'enabled') {
                throw new RuntimeException("Variable not found: {$variable_path}");
            }

        }

        return $content;

    }


    /**
     * Recursively converts array keys to uppercase.
     *
     * @param  array  $array  The input array.
     *
     * @return array The array with uppercase keys.
     */
    private function arrayKeysToUppercase(array $array): array
    {

        $result = [];
        foreach ($array as $key => $value) {

            $key = strtoupper($key);
            if (is_array($value)) {
                $value = $this->arrayKeysToUppercase($value);
            }

            $result[$key] = $value;

        }

        return $result;

    }


    /**
     * Retrieves a nested value from an array using dot notation.
     *
     * @param  array  $array  The array to search.
     * @param  string  $path  The dot-notated key path, e.g., 'VAR.DEEPER.DEEPER'.
     *
     * @return mixed|null The value if found, or null if any key is missing.
     */
    private function getNestedValue(array $array, string $path, bool $uppercase = false)
    {

        $keys  = explode('.', $path);
        $value = $array;
        foreach ($keys as $key) {

            $key = ($uppercase) ? strtoupper($key) : $key;
            if (isset($value[$key])) {
                $value = $value[$key];
                continue;
            }

            return null;

        }

        return $value;

    }


    /**
     * Shorten strings via specified length
     *
     * @param $text
     * @param $length
     *
     * @return string|string[]|null
     */
    public function shorten($text, $length)
    {

        $text   = strip_tags($text);
        $length = abs((int) $length);
        if (strlen($text) > $length) {
            $text = preg_replace("/^(.{1,$length})(\s.*|$)/s", '\\1...', $text);
        }

        return ($text);

    }


    /**
     * Short function to parse any url format e.g.: http://name.com:port/folder/playlist.pls to http://host:port
     *
     * @param $url
     *
     * @return string|null
     */
    public function parseURL($url): ?string
    {

        // Empty
        if (empty($url)) {
            return null;
        }

        // Regex
        $match = parse_url($url);

        // Make sure URL is ok before returning...
        if (empty($match['host'])) {
            return null;
        }

        // No port or not numeric, default to 80
        if ( ! isset($match['port']) || ! is_int($match['port'])) {
            $match['port'] = 80;
        }

        // Host isn't empty, return :)
        return "{$match['scheme']}://{$match['host']}:{$match['port']}";

    }


    /* File functions (extGet, extDel, etc...)
    ============================================================================== */
    /**
     * @param $filename
     *
     * @return string
     */
    public function extGet($filename): string
    {

        return strtolower(str_replace('.', '', strrchr($filename, '.')));

    }


    /**
     * @param $filename
     *
     * @return bool|string
     */
    public function extDel($filename)
    {

        $ext = strrchr($filename, '.');

        return (( ! empty($ext)) ? substr($filename, 0, -strlen($ext)) : $filename);

    }


    /**
     * @param      $b
     * @param  null  $p
     *
     * @return string
     */
    public function formatBytes($b, $p = null): string
    {

        // Array of units
        $units = ["B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];

        // Temp
        $r = [];

        // Check $p definition
        if ( ! $p && $p !== 0) {
            foreach ($units as $k => $u) {
                if (($b / (1024 ** $k)) >= 1) {
                    $r["bytes"] = $b / (1024 ** $k);
                    $r["units"] = $u;
                }
            }

            return number_format($r["bytes"], 2).' '.$r["units"];

        }

        // pow(1024, $p) == 1024 ** $p
        return number_format($b / (1024 ** $p)).' '.$units[$p];

    }


    /**
     * Simple function to simplify looking for files and directories
     *
     * @param      $path
     * @param  bool  $show_files
     * @param  bool  $show_directories
     * @param  bool  $directory_append
     *
     * @return array
     */
    public function browse($path, bool $show_files = true, bool $show_directories = false, bool $directory_append = true): array
    {

        $files = [];

        // Only if dir exists
        if (
            is_dir($path)
            && $handle = opendir($path)
        ) {

            while (false !== ($entry = readdir($handle))) {

                // Skip back folder signs
                if ($entry === "." || $entry === "..") {
                    continue;
                }

                // Append / to directories
                if ($directory_append === true && is_dir($path.$entry)) {
                    $entry .= '/';
                }

                // If specified dirs will be skipped
                if ($show_directories === false && is_dir($path.$entry)) {
                    continue;
                }

                // If specified files will be skipped
                if ($show_files === false && is_file($path.$entry)) {
                    continue;
                }

                // Finally add to the array (list)
                $files[] = $entry;

            }

            closedir($handle);

        }

        return $files;

    }


    /**
     * Simple function to handle UTF8 encoding, also make sure we don't encode already encoded string
     *
     * @param $string
     *
     * @return string
     */
    public function strToUTF8($string): string
    {

        // String should not be empty!
        if (empty($string)) {
            return "";
        }

        // Check if multibyte string is installed, if not, run old way
        if ( ! function_exists('mb_convert_encoding')) {

            return ((preg_match('!!u', $string)) ? $string : utf8_encode($string));

        }

        // Convert encoding from XXX to UTF-8
        $string = mb_convert_encoding($string, "UTF-8");

        // Escape special characters
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

        // Return modified - UTF-8 string
        return html_entity_decode($string);

    }


    /**
     * Recursively changes all keys in an array from camelCase to snake_case
     *
     * @param  array  $input
     *
     * @return array
     */
    public function arrayKeysCaseToSnakeCase($input): array
    {

        $resultArray = [];
        foreach ($input as $key => $value) {

            $newKey = $this->camelCaseToSnakeCase($key);
            if (is_array($value)) {
                $value = $this->arrayKeysCaseToSnakeCase($value);
            }

            $resultArray[$newKey] = $value;

        }

        return $resultArray;

    }


    /**
     * Changes camelCase to snake_case
     *
     * @param  string  $input
     *
     * @return string
     */
    public function camelCaseToSnakeCase(string $input): string
    {

        // Add an underscore before each uppercase letter (except the first character)
        $pattern = '/(?<!^)[A-Z]/';

        return strtolower(preg_replace($pattern, '_$0', $input));

    }

}