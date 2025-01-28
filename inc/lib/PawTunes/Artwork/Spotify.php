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

namespace lib\PawTunes\Artwork;

use lib\PawException;
use lib\PawTunes;

class Spotify extends Artwork
{

    /**
     * @var string
     */
    private $url = "https://api.spotify.com/v1/search?q={{\$rawArtist}}&type={{\$type}}&limit=1";

    /**
     * @var mixed|null
     */
    private $apiKey;

    /**
     * @var string
     */
    private $type = 'track';


    /**
     * @param       $apiKey
     * @param  array  $settings
     */
    public function __construct(PawTunes $pawtunes, $override = null)
    {
        // Parent constructor (parses settings)
        parent::__construct($pawtunes, $override);

        // Required!
        if (empty($this->pawtunes->config('artwork_sources')['spotify']['api_key'])) {
            throw new PawException('Missing API key for the Spotify API!');
        }

        $this->apiKey = $this->pawtunes->config('artwork_sources')['spotify']['api_key'] ?? null;

        // We can use artist or track images
        $this->type = $this->pawtunes->config('artist_images_only') ? 'artist' : 'track';
    }


    /**
     * @param  string  $artist
     * @param  string  $title
     *
     * @return mixed
     */
    protected function getArtworkURL($artist, $title = '')
    {
        $barrer = $this->getOAuthToken();
        if ( ! $this->apiKey || ! $barrer) {
            return null;
        }

        $data = json_decode(
            $this->pawtunes->get(
                $this->pawtunes->template($this->url, ['rawArtist' => rawurlencode($artist), 'type' => $this->type], false),
                null,
                null,
                false,
                30,
                $curl_error,
                [
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$barrer],
                ]
            ), true
        );

        // Unable to find artist
        if ( ! empty($data['error']) && $this->debug) {
            throw new PawException("Spotify Artwork Search for \"{$artist}\" failed! Response: ".$data['error']['message']);
        }

        // Check if tracks were found
        if ( ! empty($data['error']) || count($data[$this->type.'s']['items']) < 1) {
            return null;
        }

        // Album images?
        if (empty($data[$this->type.'s']['items'][0]['album']['images'])) {
            return null;
        }

        return $data[$this->type.'s']['items'][0]['album']['images'][0]['url'];
    }


    /**
     * Authorize with the Spotify API
     *
     * @return mixed
     */
    private function getOAuthToken()
    {
        // Try to use token from cache otherwise we might be blocked really fast.
        if ($this->pawtunes->cache->get('spotify_token')) {
            return $this->pawtunes->cache->get('spotify_token');
        }

        $headers = [
            'Authorization: Basic '.base64_encode($this->apiKey),
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $try = $this->pawtunes->get(
            'https://accounts.spotify.com/api/token',
            ['grant_type' => 'client_credentials'],
            null,
            false,
            20,
            $authError,
            [
                CURLOPT_HTTPHEADER => $headers,
            ]
        );

        if ( ! $try) {
            throw new PawException('Unable to get OAuth token for the Spotify API!, Details: '.$authError);
        }

        $data = json_decode($try);
        if ( ! $data || empty($data->access_token)) {
            throw new PawException('Unable to get OAuth token for the Spotify API!, Details: '.$data->error);
        }

        $this->pawtunes->cache->set('spotify_token', $data->access_token, $data->expires_in);

        return $data->access_token;
    }

}