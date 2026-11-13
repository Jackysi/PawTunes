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
     * Five results so one by the right artist can be picked when the top hit is someone else
     */
    private const SEARCH_URL = 'https://api.spotify.com/v1/search?q=%s&type=%s&limit=5';

    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';

    /**
     * "client_id:client_secret"
     *
     * @var string
     */
    private $apiKey;

    /**
     * "artist" (artist photos, Artist Images Mode) or "track" (album covers)
     *
     * @var string
     */
    private $type = 'track';


    /**
     * @param  PawTunes  $pawtunes
     * @param  null  $override
     */
    public function __construct(PawTunes $pawtunes, $override = null)
    {
        // Parent constructor (parses settings)
        parent::__construct($pawtunes, $override);

        // Required!
        if (empty($this->pawtunes->config('artwork_sources')['spotify']['api_key'])) {
            throw new PawException('Missing API key for the Spotify API!');
        }

        $this->apiKey = $this->pawtunes->config('artwork_sources')['spotify']['api_key'];

        // We can use artist or track images
        $this->type = $this->pawtunes->config('artist_images_only') ? 'artist' : 'track';
    }


    /**
     * @param  string  $artist
     * @param  string  $title
     *
     * @return string|null
     */
    protected function getArtworkURL($artist, $title = '')
    {
        $bearer = $this->getOAuthToken();

        // Field filters narrow a track search to this exact song, an artist search needs just the name
        $query = ($this->type === 'track' && ! empty($title)) ? "track:{$title} artist:{$artist}" : (string) $artist;

        $response = $this->pawtunes->get(
            sprintf(self::SEARCH_URL, rawurlencode($query), $this->type),
            null,
            null,
            false,
            10,
            $error,
            [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer '.$bearer,
                ],
            ]
        );

        $data = json_decode((string) $response, true);
        if ( ! is_array($data)) {
            return null;
        }

        // No artist or title in the message, they are untrusted text and the logs are viewable in the panel
        if ( ! empty($data['error'])) {
            throw new PawException('Spotify artwork search failed: '.($data['error']['message'] ?? 'unknown error'));
        }

        foreach ($data[$this->type.'s']['items'] ?? [] as $item) {

            // Artist results carry their own photos, track results the album's cover (largest first)
            $name   = ($this->type === 'artist') ? ($item['name'] ?? '') : ($item['artists'][0]['name'] ?? '');
            $images = ($this->type === 'artist') ? ($item['images'] ?? []) : ($item['album']['images'] ?? []);

            if (empty($images[0]['url']) || ! $this->isSameArtist((string) $artist, (string) $name)) {
                continue;
            }

            return $images[0]['url'];

        }

        return null;
    }


    /**
     * Client credentials token, cached per credentials (editing them in the panel takes effect immediately)
     *
     * @return string
     */
    private function getOAuthToken(): string
    {
        $cacheKey = 'spotify_token.'.md5($this->apiKey);

        // Try to use token from cache otherwise we might be blocked really fast.
        if ($token = $this->pawtunes->cache->get($cacheKey)) {
            return $token;
        }

        $response = $this->pawtunes->get(
            self::TOKEN_URL,
            ['grant_type' => 'client_credentials'],
            null,
            false,
            10,
            $authError,
            [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic '.base64_encode($this->apiKey),
                    'Content-Type: application/x-www-form-urlencoded',
                ],
            ]
        );

        if ( ! $response) {
            throw new PawException('Unable to get OAuth token for the Spotify API! Details: '.$authError);
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new PawException('Unable to get OAuth token for the Spotify API! Details: '.($data['error_description'] ?? $data['error'] ?? 'invalid response'));
        }

        // Expire a minute early so a request never goes out with a token that dies in flight
        $this->pawtunes->cache->set($cacheKey, $data['access_token'], max(60, (int) ($data['expires_in'] ?? 3600) - 60));

        return $data['access_token'];
    }

}
