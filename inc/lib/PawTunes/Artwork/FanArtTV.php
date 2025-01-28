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

use \Guzzle\Http\Client;
use lib\PawException;
use lib\PawTunes;
use \MusicBrainz\HttpAdapters\GuzzleHttpAdapter;
use \MusicBrainz\MusicBrainz;

class FanArtTV extends Artwork
{

    /**
     * @var string
     */
    private $brainzAPI = "https://musicbrainz.org/ws/2/artist/?query={{\$rawArtist}}&fmt=json";

    /**
     * @var string
     */
    private $fanArtAPI = "https://webservice.fanart.tv/v3/music/{{\$id}}?api_key={{\$apiKey}}";

    /**
     * @var mixed|null
     */
    private $apiKey;


    /**
     * @param       $apiKey
     * @param  array  $settings
     */
    public function __construct(PawTunes $pawtunes, $override = null)
    {
        // Parent constructor (parses settings)
        parent::__construct($pawtunes, $override);

        // Required!
        if (empty($this->pawtunes->config('artwork_sources')['fanarttv']['api_key'])) {
            throw new PawException('Missing API key for FanArtTV artwork API! Please get one from https://fanart.tv/get-an-api-key/.');
        }

        $this->apiKey = $this->pawtunes->config('artwork_sources')['fanarttv']['api_key'] ?? null;
    }


    /**
     * @param  string  $artist
     * @param  string|null  $title
     *
     * @return mixed
     */
    protected function getArtworkURL($artist, $title = '')
    {
        // Check API Key existance
        if ( ! $this->apiKey) {
            return null;
        }

        // Use MusicBrainz to get the ID of an artist
        $id = $this->getArtistID($artist);
        if ( ! $id) {
            return null;
        }

        // We have ID, now access FanArt API and get our image!!!
        $data = null;
        $api  = $this->pawtunes->get($this->pawtunes->template($this->fanArtAPI, ['id' => $id, 'apiKey' => $this->apiKey], false));
        if ($api) {
            $data = json_decode($api, true);
        }

        // API Response was success, try using artistbackground
        if ($data && ! empty($data['artistbackground']) && count($data['artistbackground']) > 0) {

            return $data['artistbackground'][0]['url'];

        }

        // API Response was success, try using artistthumb
        if ($data && ! empty($data['artistthumb']) && count($data['artistthumb']) > 0) {

            return $data['artistthumb'][0]['url'];

        }

        return null;
    }


    /**
     * Use MusicBrainz to get the ID of an artist
     *
     * @param  string  $artist
     *
     * @return mixed|null
     */
    private function getArtistID(string $artist)
    {
        // Get MusicBrainz ID of an artist
        $getID = $this->pawtunes->get($this->pawtunes->template($this->brainzAPI, ['rawArtist' => rawurlencode($artist)], false));

        // API Response was success
        if ($getID) {

            $data = json_decode($getID, true);
            if ($data && ! empty($data['artists']) && count($data['artists']) > 0) {

                return $data['artists'][0]['id'];

            }

        }

        return null;
    }

}