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
use lib\PawTunes\Listeners;

class Shoutcast extends TrackInfo implements ListenersInterface
{

    /**
     * The admin password the track info already uses is all the listener list needs
     *
     * @return bool
     */
    public function supportsListeners(): bool
    {
        return ! empty($this->channel['stats']['url']) && ! empty($this->channel['stats']['auth']);
    }


    /**
     * DNAS 2: admin.cgi?mode=viewjson&page=3 (a JSON list with hostname, useragent, connecttime).
     * DNAS 1 only knows the XML view, so a non-JSON answer falls back to mode=viewxml and its
     * LISTENERS/LISTENER elements. Admin connections count as listeners on the server side.
     *
     * @return array
     * @throws \lib\PawException
     */
    public function getListeners(): array
    {
        $this->requireCURLExt()
             ->requireXMLExt()
             ->requireURLSet()
             ->requireAuthParam();

        $query = http_build_query([
            'pass' => $this->channel['stats']['auth'],
            'sid'  => $this->channel['stats']['sid'] ?? '',
            'page' => 3,
        ]);

        $body = $this->pawtunes->get("{$this->channel['stats']['url']}/admin.cgi?mode=viewjson&{$query}");
        if ( ! $body) {
            throw new PawException("Connection to the Shoutcast server failed!");
        }

        $rows = [];
        $json = json_decode($body, true);

        // The listener list is a JSON array. An object instead ({"error": "..."} for a wrong password or stream id)
        // is an error, not an empty list: reporting zero listeners would hide the misconfiguration.
        if (is_array($json) && $json !== [] && array_keys($json) !== range(0, count($json) - 1)) {
            $error = is_string($json['error'] ?? null) ? $json['error'] : 'unexpected answer';
            throw new PawException("Shoutcast listener list failed: {$error}");
        }

        if (is_array($json)) {

            foreach ($json as $item) {

                if ( ! is_array($item)) {
                    continue;
                }

                $rows[] = Listeners::entry(
                    (string) ($item['hostname'] ?? ''),
                    Listeners::deviceLabel((string) ($item['useragent'] ?? '')),
                    (int) ($item['connecttime'] ?? 0)
                );

            }

            return Listeners::finalize($rows);

        }

        // Not JSON: an older DNAS, read the XML view
        $xml = $this->pawtunes->get("{$this->channel['stats']['url']}/admin.cgi?mode=viewxml&{$query}");
        if ( ! $xml) {
            throw new PawException("Connection to the Shoutcast server failed!");
        }

        $parsed = $this->pawtunes->xml2array($xml, true);
        if ( ! is_array($parsed) || ! array_key_exists('listeners', $parsed)) {
            throw new PawException("Unable to parse the Shoutcast listener list!");
        }

        $listeners = $parsed['listeners']['listener'] ?? [];
        if (isset($listeners['hostname']) || isset($listeners['useragent'])) {
            $listeners = [$listeners];
        }

        foreach ($listeners as $listener) {

            if ( ! is_array($listener)) {
                continue;
            }

            $rows[] = Listeners::entry(
                (string) ($listener['hostname'] ?? ''),
                Listeners::deviceLabel((string) ($listener['useragent'] ?? '')),
                (int) ($listener['connecttime'] ?? 0)
            );

        }

        return Listeners::finalize($rows);
    }


    /**
     * @throws \lib\PawException
     */
    private function requireAuthParam()
    {
        if (empty($this->channel['stats']['auth'])) {
            throw new PawException("Unable to connect to the stream because authentication info is missing!");
        }

        return $this;
    }


    /**
     * Make sure requirements are meet and then get info, parse it and return
     *
     * @throws \lib\PawException
     */
    public function getInfo()
    {
        $this->requireCURLExt()
             ->requireXMLExt()
             ->requireURLSet()
             ->requireAuthParam();

        // Attempt getting the XML data from the Shoutcast server
        $xml = $this->pawtunes->get("{$this->channel['stats']['url']}/admin.cgi?pass={$this->channel['stats']['auth']}&mode=viewxml&sid={$this->channel['stats']['sid']}");
        if ( ! $xml) {
            throw new PawException("Connection to the Shoutcast server failed!");
        }

        return $this->parseResponse($xml);
    }


    /**
     * @throws \lib\PawException
     */
    private function parseResponse($data)
    {
        $parsed = $this->pawtunes->xml2array($data, true);

        // Throw exception if XML parsing failed
        if (empty($parsed) || ! is_array($parsed) || ! isset($parsed['songtitle'])) {
            throw new PawException("Unable to parse Shoutcast XML response!");
        }

        // Parse track info
        $track = $this->handleTrack($parsed['songtitle']);

        // Handle history
        if ($this->channel['stats']['sc-history'] && isset($parsed['songhistory']) && count($parsed['songhistory']['song']) >= 1) {

            $track['history'] = [];
            foreach ($parsed['songhistory']['song'] as $song) {

                // Parse as you would any track, but add the time it was played
                $track['history'][] = array_merge(
                    $this->handleTrack($song['title']),
                    ['time' => ((int) $song['playedat'] * 1000)]
                );

            }

        }

        return $track;
    }

}