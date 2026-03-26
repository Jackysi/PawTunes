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

/**
 * Stateless utility methods. All methods are static — no instance state needed.
 */
final class Helpers
{

    /**
     * Get the current URL (without query string).
     *
     * @return string
     */
    public static function currentURL(): string
    {
        $protocol = ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host     = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
        $url      = $protocol.$host.$_SERVER['REQUEST_URI'];

        return strtok($url, '?');

    }


    /**
     * Parse XML string into array
     *
     * @param  string  $xml
     * @param  bool  $lower  Lowercase all keys
     *
     * @return array
     */
    public static function xml2array(string $xml, bool $lower = false): array
    {
        $sxe = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($sxe === false) {
            return [];
        }

        $arr = self::xmlToArray($sxe);

        return $lower ? self::changeKeyCaseRecursive($arr, CASE_LOWER) : $arr;
    }


    /**
     * Convert XML node to array recursively.
     *
     * @param $node
     *
     * @return array|string|null
     */
    private static function xmlToArray($node)
    {
        if ($node instanceof \SimpleXMLElement) {

            $children = $node->children();
            $attrs    = $node->attributes();
            $text     = trim((string) $node);

            if (count($children) === 0 && count($attrs) === 0) {
                return $text === '' ? null : $text;
            }

            $out = [];

            foreach ($attrs as $k => $v) {
                $out['@'.$k] = (string) $v;
            }

            foreach ($children as $k => $child) {

                $value = self::xmlToArray($child);
                if (array_key_exists($k, $out)) {

                    if ( ! is_array($out[$k]) || array_keys($out[$k]) !== range(0, count($out[$k]) - 1)) {
                        $out[$k] = [$out[$k]];
                    }

                    $out[$k][] = $value;

                } else {

                    $out[$k] = $value;

                }

            }

            if ($text !== '' && count($children) > 0) {
                $out['#text'] = $text;
            }

            return $out;
        }

        if (is_array($node)) {
            return array_map(fn($n) => self::xmlToArray($n), $node);
        }

        return $node;
    }


    /**
     * Recursively change array keys case
     *
     * @param  array  $arr
     * @param  int  $case
     *
     * @return array
     */
    private static function changeKeyCaseRecursive(array $arr, int $case): array
    {
        $arr = array_change_key_case($arr, $case);
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = self::changeKeyCaseRecursive($v, $case);
            }
        }

        return $arr;
    }


    /**
     * Shorten strings via specified length
     *
     * @param $text
     * @param $length
     *
     * @return string|string[]|null
     */
    public static function shorten($text, $length)
    {

        $text   = strip_tags($text);
        $length = abs((int) $length);
        if (strlen($text) > $length) {
            $text = preg_replace("/^(.{1,$length})(\s.*|$)/s", '\\1...', $text);
        }

        return ($text);

    }


    /**
     * Parse URL to scheme://host:port format
     *
     * @param $url
     *
     * @return string|null
     */
    public static function parseURL($url): ?string
    {

        if (empty($url)) {
            return null;
        }

        $match = parse_url($url);

        if (empty($match['host'])) {
            return null;
        }

        if ( ! isset($match['port']) || ! is_int($match['port'])) {
            $match['port'] = 80;
        }

        return "{$match['scheme']}://{$match['host']}:{$match['port']}";

    }


    /**
     * Get file extension (lowercase, without dot)
     *
     * @param $filename
     *
     * @return string
     */
    public static function extGet($filename): string
    {

        return strtolower(str_replace('.', '', strrchr($filename, '.')));

    }


    /**
     * Remove file extension from filename
     *
     * @param $filename
     *
     * @return bool|string
     */
    public static function extDel($filename)
    {

        $ext = strrchr($filename, '.');

        return (( ! empty($ext)) ? substr($filename, 0, -strlen($ext)) : $filename);

    }


    /**
     * Format bytes into human-readable string
     *
     * @param      $b
     * @param  null  $p
     *
     * @return string
     */
    public static function formatBytes($b, $p = null): string
    {

        $units = ["B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];

        $r = [];

        if ( ! $p && $p !== 0) {
            foreach ($units as $k => $u) {
                if (($b / (1024 ** $k)) >= 1) {
                    $r["bytes"] = $b / (1024 ** $k);
                    $r["units"] = $u;
                }
            }

            return number_format($r["bytes"], 2).' '.$r["units"];

        }

        return number_format($b / (1024 ** $p)).' '.$units[$p];

    }


    /**
     * Browse a directory for files and/or subdirectories
     *
     * @param      $path
     * @param  bool  $show_files
     * @param  bool  $show_directories
     * @param  bool  $directory_append
     *
     * @return array
     */
    public static function browse($path, bool $show_files = true, bool $show_directories = false, bool $directory_append = true): array
    {

        $files = [];

        if (
            is_dir($path)
            && $handle = opendir($path)
        ) {

            while (false !== ($entry = readdir($handle))) {

                if ($entry === "." || $entry === "..") {
                    continue;
                }

                if ($directory_append === true && is_dir($path.$entry)) {
                    $entry .= '/';
                }

                if ($show_directories === false && is_dir($path.$entry)) {
                    continue;
                }

                if ($show_files === false && is_file($path.$entry)) {
                    continue;
                }

                $files[] = $entry;

            }

            closedir($handle);

        }

        return $files;

    }


    /**
     * Safe file deletion
     *
     * @param $path
     *
     * @return bool
     */
    public static function deleteFile($path): bool
    {

        if (file_exists($path)) {
            return unlink($path);
        }

        return false;
    }


    /**
     * Convert string to UTF-8 encoding
     *
     * @param $string
     *
     * @return string
     */
    public static function strToUTF8($string): string
    {

        if (empty($string)) {
            return "";
        }

        // Already valid UTF-8? Return as-is
        if (preg_match('!!u', $string)) {
            return $string;
        }

        // Convert from ISO-8859-1 (most common non-UTF-8 encoding in radio streams) to UTF-8
        $string = mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');

        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

        return html_entity_decode($string);

    }


    /**
     * Recursively convert array keys from camelCase to snake_case
     *
     * @param  array  $input
     *
     * @return array
     */
    public static function arrayKeysCaseToSnakeCase($input): array
    {

        $resultArray = [];
        foreach ($input as $key => $value) {

            $newKey = self::camelCaseToSnakeCase($key);
            if (is_array($value)) {
                $value = self::arrayKeysCaseToSnakeCase($value);
            }

            $resultArray[$newKey] = $value;

        }

        return $resultArray;

    }


    /**
     * Convert camelCase string to snake_case
     *
     * @param  string  $input
     *
     * @return string
     */
    public static function camelCaseToSnakeCase(string $input): string
    {

        $pattern = '/(?<!^)[A-Z]/';

        return strtolower(preg_replace($pattern, '_$0', $input));

    }

}
