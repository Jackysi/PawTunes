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

class Azuracast extends TrackInfo
{

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