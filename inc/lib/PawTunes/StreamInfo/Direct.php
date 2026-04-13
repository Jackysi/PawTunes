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
                // Request ICY METADATA (Shoutcast/MP3)
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

                    // MP3/Shoutcast logic: We know exactly where metadata is
                    if ($icyMetaint !== null && $icyMetaint > 0) {

                        if (strlen($buf) >= $icyMetaint + 4000) {
                            return 0;
                        }

                    } else { // Ogg/General logic: Read up to 32KB to capture header comments

                        if (strlen($buf) >= 32768) {
                            return 0;
                        }

                    }

                    return strlen($chunk);

                },
            ],
            false // Always err, because we abort on 0
        );

        // Something broke
        if (empty($buf)) {
            throw new PawException($err ?: 'Empty response from stream.');
        }

        // Handle Ogg/Vorbis/Opus where icyMetaint is missing
        if ($icyMetaint === null) {
            $isOgg = strpos($contentType ?? '', 'ogg') !== false
                     || strpos($contentType ?? '', 'vorbis') !== false
                     || strpos($contentType ?? '', 'opus') !== false
                     || strpos($buf, 'OggS') === 0;

            if ($isOgg) {
                $icyMetaint = 0; // Use start of buffer
            }
        }

        // We have metaint (or forced Ogg 0), let's parse it
        if (is_int($icyMetaint) && $icyMetaint >= 0) {

            // For Ogg we scan the whole buffer, for MP3 we scan the interval
            $slice = ($icyMetaint === 0)
                ? $buf
                : substr($buf, $icyMetaint, 4000);

            if ($slice !== false && $slice !== '') {

                // Shoutcast: StreamTitle='...';
                if (strpos($slice, 'StreamTitle=') !== false) {
                    if (preg_match("/StreamTitle='(.*?)';/s", $slice, $m)) {
                        $result = $m[1];
                    }
                }

                // Icecast/Ogg: TITLE=... ARTIST=...
                if ( ! $result && (stripos($slice, 'TITLE=') !== false || stripos($slice, 'ARTIST=') !== false)) {

                    // Parse loosely specifically for Ogg where binary nulls exist
                    $artist = '';
                    $title  = '';

                    if (preg_match('/TITLE=([^\x00-\x1F]+)/i', $slice, $mT)) {
                        $title = trim($mT[1]);
                    }
                    if (preg_match('/ARTIST=([^\x00-\x1F]+)/i', $slice, $mA)) {
                        $artist = trim($mA[1]);
                    }

                    if ($title || $artist) {
                        $combo  = $artist !== '' && $title !== '' ? "$artist - $title" : ($artist ?: $title);
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