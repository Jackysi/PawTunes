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

/**
 * Listener list helpers shared by the providers: address masking, a small user agent labeller and the
 * common response shape. Everything here runs before a list is cached or sent, so a raw address never
 * reaches the cache, the log or the browser.
 */
final class Listeners
{

    /**
     * Rows above this are dropped from a response (the total still counts them)
     */
    public const MAX_ROWS = 200;

    /**
     * Longest device label kept from an upstream-provided string
     */
    private const LABEL_MAX_LENGTH = 48;

    /**
     * Dedicated players, devices and tools, matched before the browser patterns because most of them
     * do not look like a browser at all. First match wins, so the more specific entries come first.
     */
    private const PLAYERS = [
        '/\bVLC\b|LibVLC/i'                                    => 'VLC',
        '/\biTunes\b/i'                                        => 'iTunes',
        '/AppleCoreMedia|Apple Music/i'                        => 'Apple device',
        '/\bSonos\b/i'                                         => 'Sonos',
        '/Winamp/i'                                            => 'Winamp',
        '/foobar2000/i'                                        => 'foobar2000',
        '/\bAIMP\b/i'                                          => 'AIMP',
        '/\bKodi\b|\bXBMC\b/i'                                 => 'Kodi',
        '/\bmpv\b|\bMPlayer\b/i'                               => 'mpv',
        '/Music Player Daemon/i'                               => 'MPD',
        '/\bLavf\b|\bFFmpeg\b/i'                               => 'FFmpeg',
        '/GStreamer/i'                                         => 'GStreamer',
        '/NSPlayer|Windows-Media-Player|WMPlayer|WMFSDK/i'     => 'Windows Media Player',
        '/\bTuneIn\b/i'                                        => 'TuneIn',
        '/RadioDroid|Transistor|Radio Browser/i'               => 'Radio app',
        '/\bAlexa\b|\bEcho\b/i'                                => 'Amazon Alexa',
        '/\bCrKey\b/i'                                         => 'Chromecast',
        '/\bRoku\b/i'                                          => 'Roku',
        '/ExoPlayer|stagefright|\bDalvik\b|\bokhttp\b/i'       => 'Android app',
        '/\bIcecast\b|Liquidsoap|\bBUTT\b|Mixxx|RadioBOSS/i'   => 'Relay',
        '/^curl\/|^Wget\/|python-requests|Go-http-client|^Java\//i' => 'Script',
    ];

    /**
     * Browser patterns, in an order where the derivatives come before what they are derived from
     * (Edge and Opera both carry "Chrome/", every Chrome carries "Safari/")
     */
    private const BROWSERS = [
        '/\bEdg(?:e|A|iOS)?\//'          => 'Edge',
        '/\bOPR\/|\bOpera\b/'            => 'Opera',
        '/SamsungBrowser/'               => 'Samsung Internet',
        '/\bFirefox\/|\bFxiOS\//'        => 'Firefox',
        '/\bChrome\/|\bCriOS\//'         => 'Chrome',
        '/\bVersion\/[\d.]+.*Safari\//'  => 'Safari',
        '/\bMSIE\b|\bTrident\//'         => 'Internet Explorer',
    ];

    /**
     * Operating systems, iOS before macOS (an iPad can claim "Macintosh" but still carries "Mobile")
     */
    private const SYSTEMS = [
        '/\biPhone\b|\biPad\b|\biPod\b/' => 'iOS',
        '/\bAndroid\b/'                  => 'Android',
        '/\bWindows\b/'                  => 'Windows',
        '/\bCrOS\b/'                     => 'ChromeOS',
        '/\bMacintosh\b|Mac OS X/'       => 'macOS',
        '/\bLinux\b|\bX11\b/'            => 'Linux',
    ];


    /**
     * Masks an address so a listener cannot be identified: IPv4 keeps its first two octets
     * ("93.103.x.x"), IPv6 the first two groups of its expanded form ("2a01:260:x:x"), an IPv4-mapped
     * IPv6 address is masked as the IPv4 it carries. Anything that is not an address becomes "".
     *
     * @param  string  $ip
     *
     * @return string
     */
    public static function maskIP(string $ip): string
    {
        $ip = trim($ip, " \t\r\n[]");

        // Some servers append the port to IPv4 addresses
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $m)) {
            $ip = $m[1];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {

            $octets = explode('.', $ip);

            return "{$octets[0]}.{$octets[1]}.x.x";

        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {

            $binary = inet_pton($ip);
            if ($binary === false) {
                return '';
            }

            // ::ffff:a.b.c.d carries an IPv4 address in its last four bytes
            if (substr($binary, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
                return self::maskIP(inet_ntop(substr($binary, 12)) ?: '');
            }

            $hex    = bin2hex($binary);
            $first  = ltrim(substr($hex, 0, 4), '0');
            $second = ltrim(substr($hex, 4, 4), '0');

            return ($first === '' ? '0' : $first).':'.($second === '' ? '0' : $second).':x:x';

        }

        return '';
    }


    /**
     * Short human label for a listener's client ("Chrome on Android", "VLC", "Sonos"), "" when unknown.
     * A handful of ordered patterns, no dependency: it only has to be right for the common cases.
     *
     * @param  string  $userAgent
     *
     * @return string
     */
    public static function deviceLabel(string $userAgent): string
    {
        $ua = trim($userAgent);

        // Shoutcast reports a missing agent as "**EMPTY**"
        if ($ua === '' || $ua === '**EMPTY**') {
            return '';
        }

        foreach (self::PLAYERS as $pattern => $label) {
            if (preg_match($pattern, $ua)) {
                return $label;
            }
        }

        $browser = self::firstMatch(self::BROWSERS, $ua);
        $system  = self::firstMatch(self::SYSTEMS, $ua);

        if ($browser !== '' && $system !== '') {
            return "{$browser} on {$system}";
        }

        return $browser.$system;
    }


    /**
     * One listener row: masked address, cleaned label, non-negative seconds
     *
     * @param  string  $ip  Raw address, masked here
     * @param  string  $device  Label from deviceLabel() or from the upstream's own detection
     * @param  int  $connected  Seconds connected
     *
     * @return array
     */
    public static function entry(string $ip, string $device, int $connected): array
    {
        return [
            'ip'        => self::maskIP($ip),
            'device'    => self::cleanLabel($device),
            'connected' => max(0, $connected),
        ];
    }


    /**
     * The response shape: longest connected first, capped at MAX_ROWS, total = every row given
     *
     * @param  array  $rows  entry() rows
     *
     * @return array  ['total' => int, 'listeners' => array]
     */
    public static function finalize(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            return $b['connected'] <=> $a['connected'];
        });

        return [
            'total'     => count($rows),
            'listeners' => array_slice($rows, 0, self::MAX_ROWS),
        ];
    }


    /**
     * Upstream-provided labels (AzuraCast's device detection) are kept short and free of control characters
     *
     * @param  string  $label
     *
     * @return string
     */
    private static function cleanLabel(string $label): string
    {
        $label = (string) preg_replace('/[\x00-\x1F\x7F]+/', '', $label);
        $label = trim((string) preg_replace('/\s{2,}/', ' ', $label));

        // Cut on characters, not bytes (no mbstring needed)
        if (preg_match('/^(.{'.self::LABEL_MAX_LENGTH.'}).+$/su', $label, $m)) {
            return $m[1].'...';
        }

        return $label;
    }


    /**
     * @param  array  $patterns  [regex => label]
     * @param  string  $ua
     *
     * @return string  The first label whose pattern matches, "" when none does
     */
    private static function firstMatch(array $patterns, string $ua): string
    {
        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $ua)) {
                return $label;
            }
        }

        return '';
    }

}
