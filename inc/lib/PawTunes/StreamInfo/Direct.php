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

namespace lib\PawTunes\StreamInfo;

use lib\PawException;

class Direct extends TrackInfo
{

    /**
     * Make sure extensions are loaded and stream URL is set
     * Then get info, parse it and return
     *
     * @throws \lib\PawException
     */
    public function getInfo()
    {
        $this->requireURLSet()
             ->requireCURLExt();

        $get_info = $this->readStream($this->channel['stats']['url']);

        if ((empty($get_info)) && ! empty($this->channel['fallback'])) {
            $get_info = $this->readStream($this->channel['fallback']);
        }

        return $this->handleTrack($get_info);
    }


    /**
     * Open stream and read its content to parse current playing track
     *
     * @param $url
     *
     * @return bool|string
     */
    private function readStream($url)
    {
        $result      = false;
        $buf         = '';
        $icyMetaint  = null;
        $contentType = null;
        $headers     = [];
        $err         = null;

        // Heavy lifting here
        $this->pawtunes->get(
            $url,
            null,
            null,
            false,
            10,
            $err,
            [
                // Request ICY METADATA
                CURLOPT_HTTPHEADER     => [
                    'Icy-MetaData: 1',
                ],
                // We search for icy-metaint header to get refresh time
                CURLOPT_HEADERFUNCTION => static function ($ch, $headerLine) use (&$headers, &$icyMetaint, &$contentType) {

                    $len   = strlen($headerLine);
                    $parts = explode(':', $headerLine, 2);
                    if (count($parts) === 2) {

                        $name           = strtolower(trim($parts[0]));
                        $value          = trim($parts[1]);
                        $headers[$name] = $value;

                        if ($name === 'icy-metaint') {
                            $icyMetaint = is_numeric($value) ? (int) $value : null;
                        }
                        if ($name === 'content-type') {
                            $contentType = strtolower($value);
                        }

                    }

                    return $len;

                },
                CURLOPT_WRITEFUNCTION  => static function ($ch, $chunk) use (&$buf, &$icyMetaint) {

                    $buf .= $chunk;

                    // Abort, otherwise this will go indefinitely
                    if ($icyMetaint === null) {
                        return 0;
                    }

                    // Once we have at least $icyMetaint + 600 bytes, stop
                    if (strlen($buf) >= $icyMetaint + 600) {
                        return 0;
                    }

                    return strlen($chunk);

                },
            ],
            false // Always err, because we abort on 0
        );

        // OGG without metaint → treat as 0 (prefix match!)
        if ($icyMetaint === null && $contentType && str_starts_with($contentType, 'application/ogg')) {
            $icyMetaint = 0;
        }

        // Something broke
        if (empty($buf)) {
            throw new PawException($err ?: 'Empty response from stream.');
        }

        // We have metaint, let's parse it
        if ($icyMetaint !== null && $icyMetaint >= 0) {

            $slice = strlen($buf) < ($icyMetaint + 600)
                ? substr($buf, $icyMetaint)
                : substr($buf, $icyMetaint, 600);

            if ($slice !== false && $slice !== '') {

                // Shoutcast: StreamTitle='...';
                if (strpos($slice, 'StreamTitle=') !== false) {

                    $parts = explode('StreamTitle=', $slice, 2);
                    $title = trim($parts[1]);

                    if (preg_match("/^'(.*?)';/s", $title, $m)) {
                        $result = $m[1];
                    }

                }

                // Icecast KV: TITLE=... ARTIST=...
                if ( ! $result && strpos($slice, 'TITLE=') !== false && strpos($slice, 'ARTIST=') !== false) {

                    // tolerate noise and line breaks between fields
                    if (preg_match('/TITLE=(?P<title>.*?)ARTIST=(?P<artist>.*?)(?:ENCODEDBY|$)/s', $slice, $m)) {

                        $clean  = static fn($s) => preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $s);
                        $artist = $clean($m['artist'] ?? '');
                        $title  = $clean($m['title'] ?? '');
                        $combo  = trim($artist) !== '' && trim($title) !== '' ? "$artist - $title" : ($artist ?: $title);
                        $result = trim($combo, " \t\n\r\0\x0B-");

                    }
                }
            }
        }

        if (empty($result)) {
            throw new PawException("Failed to parse stream info from {$this->channel['stats']['url']}.");
        }

        return $result;

    }

}