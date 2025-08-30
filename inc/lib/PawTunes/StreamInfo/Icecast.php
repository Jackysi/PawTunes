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

class Icecast extends TrackInfo
{

    /**
     * Make sure requirements are meet and then get info, parse it and return
     *
     * @throws \lib\PawException
     */
    public function getInfo()
    {
        $this->requireURLSet()
             ->requireCURLExt()
             ->requireXMLExt()
             ->requireAuth();

        // Attempt getting the XML data from the Icecast server
        $xml = $this->pawtunes->get("{$this->channel['stats']['url']}/admin/stats", null, "{$this->channel['stats']['auth-user']}:{$this->channel['stats']['auth-pass']}");
        if ( ! $xml) {
            throw new PawException("Connection to the Icecast server failed!");
        }

        // Check auth status
        if (preg_match("/You need to authenticate/i", $xml)) {
            throw new PawException("Authentication to the Iceacast server failed!");
        }

        // Attempt parsing the XML data
        return $this->handleTrack($this->parseResponse($xml));
    }


    /**
     * @throws \lib\PawException
     */
    private function parseResponse($data)
    {
        $ice    = [];
        $parsed = $this->pawtunes->xml2array($data, true);
        var_dump($parsed);

        // Throw exception if XML parsing failed
        if (empty($parsed) || ! is_array($parsed) || ! is_array($parsed['source'])) {
            throw new PawException("Unable to parse Icecast XML response!");
        }

        // Handle multi-mount setup
        if (isset($parsed['source'][0]) && is_array($parsed['source'][0])) {

            foreach ($parsed['source'] as $mount) {
                $ice[$mount['@mount']] = $mount;
            }

        } else { // Handle single mount setup

            $ice[$parsed['source']['@mount']] = $parsed['source'];

        }

        // Now use parsed data to determine problems
        if ( ! is_array($ice[$this->channel['stats']['mount']]) && ! is_array($ice[$this->channel['stats']['fallback']])) {
            throw new PawException("Specified mount and fall-back mount were not found!");
        }

        // Attempt to use main mount, else use backup one
        if ( ! empty($ice[$this->channel['stats']['mount']]['title']) || ! empty($ice[$this->channel['stats']['mount']]['artist'])) {

            // Determine Artist/Title on PRIMARY Mount
            if (empty($ice[$this->channel['stats']['mount']]['artist'])) {

                $ice = $ice[$this->channel['stats']['mount']]['title'];

            } else {

                $ice = $ice[$this->channel['stats']['mount']]['artist'].' - '.$ice[$this->channel['stats']['mount']]['title'];

            }

        } else {

            // Fallback mount determine Artist/Title
            if (empty($ice[$this->channel['stats']['fallback']]['artist'])) {

                $ice = $ice[$this->channel['stats']['fallback']]['title'];

            } else {

                $ice = $ice[$this->channel['stats']['fallback']]['artist'].' - '.$ice[$this->channel['stats']['fallback']]['title'];

            }

        }

        return $ice;
    }


}