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
use mysqli;

class SAM extends TrackInfo
{

    private $mysql;


    /**
     * @throws \lib\PawException
     */
    public function getInfo()
    {
        $this->requireMySQLi()
             ->requireAuth()
             ->requireHost()
             ->requireDatabase();

        // Connect to database
        $this->connectToDatabase();

        // Query database
        $sam = mysqli_fetch_assoc(
            $this->mysql->query("SELECT songID, artist, title, date_played, duration FROM {$this->channel['stats']['db']}.historylist ORDER BY `historylist`.`date_played` DESC LIMIT 0,1"
            )
        );

        if ($this->mysql->error) {
            throw new PawException("SAM Database query failed with error: {$this->mysql->error}");
        }

        return $this->handleQueryResponse($sam);
    }


    /**
     * @throws \lib\PawException
     */
    private function requireDatabase()
    {
        if (empty($this->channel['stats']['db'])) {
            throw new PawException("SAM Broadcaster's database name is missing!");
        }

        return $this;
    }


    /**
     * @throws \lib\PawException
     */
    private function requireHost()
    {
        if (empty($this->channel['stats']['host'])) {
            throw new PawException("SAM Broadcaster's database host is missing!");
        }

        return $this;
    }


    /**
     * @throws \lib\PawException
     */
    private function requireMySQLi()
    {

        if ( ! class_exists('mysqli')) {
            throw new PawException("MySQLi extension is not loaded, unable to connect to the database!");
        }

        return $this;

    }


    /**
     * @throws \lib\PawException
     */
    private function connectToDatabase()
    {

        // Since 1.21 we also allow sockets and ports
        $p_url = parse_url($this->channel['stats']['host']);

        // maybe sock?
        if (empty($p_url['host']) && is_file($this->channel['stats']['host'])) {

            $this->channel['stats']['socket'] = $this->channel['stats']['host'];
            $this->channel['stats']['host']   = '127.0.0.1';

        } elseif ( ! empty($p_url['port'])) { // Port added?

            $this->channel['stats']['host'] = $p_url['host'];
            $this->channel['stats']['port'] = $p_url['port'];

        } else {

            // Not necessary, but we still define the variables just in case
            $this->channel['stats']['socket'] = null;
            $this->channel['stats']['port']   = null;

        }

        // Attempt connection
        $this->mysql = new mysqli(
            $this->channel['stats']['host'],
            $this->channel['stats']['auth-user'],
            $this->channel['stats']['auth-pass'],
            $this->channel['stats']['db'],
            $this->channel['stats']['port'],
            $this->channel['stats']['socket']
        );

        // Check if connection was established
        if ($this->mysql->connect_errno > 0) {
            throw new PawException("Database connection failed, MySQL returned: {$this->mysql->connect_error}");
        }

    }


    /**
     * A little different response for SAM as it uses database which could have faulty ID3 tag scans
     *
     * @param $data
     *
     * @return array
     */
    private function handleQueryResponse($data)
    {
        // Sometimes SAM ID3 tags are incorrect (use artist as track name)
        if ( ! empty($data['artist']) && empty($data['title'])) {
            return $this->handleTrack($data['artist']);
        }

        // Sometimes SAM ID3 tags are incorrect (use title as track name)
        if (empty($data['artist']) && ! empty($data['title'])) {
            return $this->handleTrack($data['title']);
        }

        return $this->handleTrack(
            null,
            [
                'artist' => $this->pawtunes->strToUTF8($data['artist']),
                'title'  => $this->pawtunes->strToUTF8($data['title']),
            ]
        );
    }

}