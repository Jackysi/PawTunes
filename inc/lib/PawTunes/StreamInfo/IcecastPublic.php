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

class IcecastPublic extends TrackInfo
{

    /**
     * Make sure requirements are meet and then get info, parse it and return
     *
     * @throws \lib\PawException
     */
    public function getInfo()
    {

        $this->requireURLSet()
             ->requireCURLExt();

        // Attempt getting the JSON data from the Icecast server
        $get = $this->pawtunes->get("{$this->channel['stats']['url']}/status-json.xsl");
        if ( ! $get) {
            throw new PawException("Connection to the Icecast server failed!");
        }

        // Check auth status
        if (preg_match("/You need to authenticate/i", $get)) {
            throw new PawException("The Iceacast server requires authentication!");
        }

        // Attempt parsing the XML data
        return $this->handleTrack($this->parseResponse($get));
    }


    /**
     * @throws \lib\PawException
     */
    private function parseResponse($data)
    {

        $ice    = [];
        $parsed = json_decode($data, true, JSON_THROW_ON_ERROR);

        // Remove leading / in setting if exists
        $this->channel['stats']['mount'] = trim($this->channel['stats']['mount'], '/');

        // Throw exception if JSON fields are missing
        if (empty($parsed) || ! is_array($parsed)) {
            throw new PawException("Icecast Public JSON has invalid structure!");
        }

        // In JSON there is no "mount" specified, that's not good!
        if (isset($parsed['icestats']['source']) && is_array($parsed['icestats']['source'])) {
            foreach ($parsed['icestats']['source'] as $mount) {
                $ice[basename($mount['listenurl'])] = $mount;
            }
        }

        // Do mounts exist?
        if ( ! isset($ice[$this->channel['stats']['mount']]) || ! is_array($ice[$this->channel['stats']['mount']])) {
            throw new PawException("Specified mount not found in the response!");
        }

        $mount = $this->channel['stats']['mount'];

        // Attempt to use main mount — extract artist/title string
        if ( ! empty($ice[$mount]['title']) || ! empty($ice[$mount]['artist'])) {

            if (empty($ice[$mount]['artist'])) {

                $ice = $ice[$mount]['title'];

            } else {

                $ice = $ice[$mount]['artist'].' - '.$ice[$mount]['title'];

            }

        } else {

            // Silent stream or no metadata — return empty string for default fallback
            $ice = '';

        }

        return $ice;
    }
    
}