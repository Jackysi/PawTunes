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

class LastFM extends Artwork
{

    /**
     * @var string
     */
    private $url = "https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&artist={{\$rawArtist}}&api_key={{\$apiKey}}";

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
        if (empty($this->pawtunes->config('artwork_sources')['lastfm']['api_key'])) {
            throw new PawException('Missing API key for LastFM artwork API! Please get one from https://www.last.fm/api/account/create.');
        }

        $this->apiKey = $this->pawtunes->config('artwork_sources')['lastfm']['api_key'] ?? null ?? null;
    }


    /**
     * @param  string  $artist
     * @param  string|null  $title
     *
     * @return mixed
     */
    protected function getArtworkURL($artist, $title = '')
    {
        if ( ! $this->apiKey) {
            return null;
        }

        $data = $this->pawtunes->xml2array(
            $this->pawtunes->get(
                $this->pawtunes->template($this->url, ['rawArtist' => rawurlencode($artist), 'apiKey' => $this->apiKey], false),
                null,
                null,
                false,
                30)
        );

        // Unable to find artist
        if ( ! empty($data['error'])) {
            throw new PawException("LastFM Artwork Search for \"{$artist}\" failed! Response: ".$data['error']);
        }

        return ( ! empty($data['artist']['image'][4])) ? $data['artist']['image'][4] : $data['artist']['image'][3];
    }

}