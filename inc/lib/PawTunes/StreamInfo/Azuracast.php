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

class Azuracast extends TrackInfo implements ListenersInterface
{

    /**
     * The listener list needs an API key (stats "api-key", never sent to the browser), the station and
     * a now-playing or websocket URL the API base can be derived from
     *
     * @return bool
     */
    public function supportsListeners(): bool
    {
        return ! empty($this->channel['stats']['api-key'])
            && ! empty($this->channel['stats']['station'])
            && $this->apiBase() !== null;
    }


    /**
     * GET /api/station/{station}/listeners with the X-API-Key header ("View Station Reports" permission).
     * AzuraCast already detects the device; its browser/OS families are used when it knows them.
     *
     * @return array
     * @throws \lib\PawException
     */
    public function getListeners(): array
    {
        $this->requireCURLExt()
             ->requireStationCode();

        if (empty($this->channel['stats']['api-key'])) {
            throw new PawException("AzuraCast API key is not set, the listener list needs one!");
        }

        $base = $this->apiBase();
        if ($base === null) {
            throw new PawException("Unable to derive the AzuraCast API base from the configured URL!");
        }

        $json = $this->pawtunes->get(
            "{$base}/api/station/".rawurlencode($this->channel['stats']['station']).'/listeners',
            null,
            null,
            false,
            5,
            $error,
            [CURLOPT_HTTPHEADER => ['X-API-Key: '.$this->channel['stats']['api-key'], 'Accept: application/json']]
        );

        if ($json === false) {
            throw new PawException("Connection to the AzuraCast API failed!");
        }

        $parsed = json_decode($json, true);
        if ( ! is_array($parsed)) {
            throw new PawException("Unable to parse the AzuraCast listeners response!");
        }

        // An error is an object with a message (permission denied, wrong key), a list is a plain array
        if (isset($parsed['message']) && ! isset($parsed[0])) {
            throw new PawException("AzuraCast API returned ERROR: {$parsed['message']}");
        }

        $rows = [];
        foreach ($parsed as $item) {

            if ( ! is_array($item)) {
                continue;
            }

            $rows[] = Listeners::entry(
                (string) ($item['ip'] ?? ''),
                $this->deviceLabel($item),
                (int) ($item['connected_time'] ?? 0)
            );

        }

        return Listeners::finalize($rows);
    }


    /**
     * Base URL of the AzuraCast install, derived from the now-playing or websocket URL
     * ("https://radio.example.com/api/nowplaying/x" or "wss://radio.example.com/api/live/..." -> "https://radio.example.com")
     *
     * @return string|null
     */
    private function apiBase(): ?string
    {
        $url = parse_url((string) ($this->channel['stats']['url'] ?? ''));
        if (empty($url['host'])) {
            return null;
        }

        $scheme = strtolower($url['scheme'] ?? 'https');
        $scheme = ['wss' => 'https', 'ws' => 'http'][$scheme] ?? $scheme;
        if ( ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        // Keep a sub-directory install prefix, drop everything from /api/ on
        $path   = $url['path'] ?? '';
        $apiPos = strpos($path, '/api/');
        $path   = ($apiPos === false) ? '' : substr($path, 0, $apiPos);

        return $scheme.'://'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '').rtrim($path, '/');
    }


    /**
     * "Chrome on Android" from AzuraCast's own detection when it names both families, its client name
     * otherwise, and our own user agent labeller as the last resort
     *
     * @param  array  $item  One listener of the API response
     *
     * @return string
     */
    private function deviceLabel(array $item): string
    {
        $device  = is_array($item['device'] ?? null) ? $item['device'] : [];
        $browser = trim((string) ($device['browser_family'] ?? ''));
        $system  = trim((string) ($device['os_family'] ?? ''));

        if ($browser !== '' && $system !== '') {
            return "{$browser} on {$system}";
        }

        if ( ! empty($device['client'])) {
            return trim((string) $device['client']);
        }

        return Listeners::deviceLabel((string) ($item['user_agent'] ?? ''));
    }


    /**
     * @return array
     * @throws \lib\PawException
     */
    public function getInfo()
    {
        $this->requireCURLExt()
             ->requireURLSet()
             ->requireStationCode();

        // Parse URL
        $url = parse_url($this->channel['stats']['url']);
        if ($url['scheme'] === 'wss') {
            return $this->handleTrack(null, ['artist' => "", 'title' => ""]);
        }

        // Attempt to get JSON response from API
        $json = $this->pawtunes->get("{$this->channel['stats']['url']}");
        if ( ! $json) {
            throw new PawException("Connection to the Azuracast API failed!");
        }

        // Can we decode it?
        $parsed = json_decode($json, true);
        if ( ! $parsed) {
            throw new PawException("Unable to parse Azuracast API response!");
        }

        // Was error returned?
        if ( ! empty($parsed['error'])) {
            throw new PawException("Azuracast API returned ERROR: {$parsed['error']}");
        }

        // Make sure we got the station not array
        if ( ! isset($parsed['station']['shortcode']) || $parsed['station']['shortcode'] !== $this->channel['stats']['station']) {
            throw new PawException("Azuracast API returned no station data for station: {$this->channel['stats']['station']}!");
        }

        return $this->AzuracastParseResponse($parsed);
    }


    /**
     * @throws \lib\PawException
     */
    private function requireStationCode()
    {
        if (empty($this->channel['stats']['station'])) {
            throw new PawException("Unable to connect to the stream because station name is not set!");
        }

        return $this;
    }


    /**
     * Replacement for Centova cast because method is slightly different here
     *
     * @param $track
     *
     * @return array
     */
    private function AzuracastParseResponse($response)
    {
        $info  = [];
        $track = [];

        // Simplify
        $track['artist'] = $response['now_playing']['song']['artist'] ?? null;
        $track['title']  = $response['now_playing']['song']['title'] ?? null;

        $info['artist'] = (! $track || (empty($track['artist'])) ? $this->pawtunes->config('artist_default') : trim($track['artist']));
        $info['title']  = (! $track || (empty($track['title'])) ? $this->pawtunes->config('title_default') : trim($track['title']));

        $info['artwork_override'] = $response['now_playing']['song']['art'] && $this->channel['stats']['use-cover'] ? $response['now_playing']['song']['art'] : null;

        // Live DJ (templates that support it show a badge): AzuraCast sends is_live on every response
        $info['live'] = [
            'is_live'  => ! empty($response['live']['is_live']),
            'streamer' => trim((string) ($response['live']['streamer_name'] ?? '')),
        ];

        // Up next, only when the channel opted in. AzuraCast sends playing_next as null while nothing is
        // queued, and a queued item without a title is not worth announcing.
        if ( ! empty($this->channel['stats']['up-next']) && ! empty($response['playing_next']['song']['title'])) {

            $nextSong = $response['playing_next']['song'];
            if ($this->channel['stats']['use-cover']) {
                $nextSong['artwork'] = $nextSong['art'] ?? null; // handleTrack reads artwork/image
            }

            $info['next'] = $this->handleTrack('', $nextSong);

        }

        // Handle history
        if ($this->channel['stats']['azura-history'] && isset($response['song_history']) && count($response['song_history']) >= 1) {

            $info['history'] = [];
            foreach ($response['song_history'] as $entry) {

                // Little hack to use artwork from AzuraCast if preferred (handleTrack reads the artwork key)
                if ($this->channel['stats']['use-cover']) {
                    $entry['song']['artwork'] = $entry['song']['art'] ?? null;
                }

                // Parse as you would any track, but add the time it was played
                $info['history'][] = array_merge(
                    $this->handleTrack("", $entry['song']),
                    ['time' => ((int) $entry['played_at'] * 1000)]
                );

            }

        }

        return $info;
    }

}