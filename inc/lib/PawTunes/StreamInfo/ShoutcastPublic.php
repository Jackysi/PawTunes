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

class ShoutcastPublic extends TrackInfo
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

        // Attempt getting the XML data from the Shoutcast server
        $html = $this->pawtunes->get("{$this->channel['stats']['url']}/7.html?sid={$this->channel['stats']['sid']}");
        if ( ! $html) {
            throw new PawException("Connection to the Shoutcast (Public) server failed!");
        }

        return $this->parseResponse($html);
    }


    /**
     * @throws \lib\PawException
     */
    private function parseResponse($html)
    {
        // Replace HTML tags
        $data = preg_replace("[\n\t]", '', trim(strip_tags($html)));

        // Match output
        if ( ! preg_match('~^(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,(.*?)$~', $data, $match)) {
            throw new PawException("Unable to parse Shoutcast Public API response. The response was \"OK\" but the result is unknown.");
        }

        // Clean-up the parsed data
        $response = [
            'isOnline'               => $match[2] == 1,
            'currentListeners'       => $match[1] * 1,
            'uniqueCurrentListeners' => $match[5] * 1,
            'peakListeners'          => $match[3] * 1,
            'maxConnections'         => $match[4] * 1,
            'quality'                => $match[6] * 1,
            'onAir'                  => (trim($match[7]) !== '' && strtolower(trim($match[7])) !== 'null') ? $this->cleanUpHTMLEntities($match[7]) : null,
        ];

        return $this->handleTrack($response['onAir']);
    }

}