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
use mysqli_sql_exception;

/**
 * SAM Broadcaster: the current track is the newest historylist row of its MySQL database.
 *
 * The class is named "Sam" (not "SAM") because the handler derives the class name from the method key
 * ("sam" -> "Sam") and PSR-4 lookups are case-sensitive on Linux.
 */
class Sam extends TrackInfo
{

    /**
     * @var mysqli|null
     */
    private $mysql;

    /**
     * Seconds to connect, and to wait for a query answer. This runs while the track info refresh lock is held
     * (30 s, see handler.php): the defaults (60 s connect, read without limit) would outlive it on a dead database.
     */
    private const CONNECT_TIMEOUT = 5;

    private const READ_TIMEOUT = 10;


    /**
     * @return array
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

        // Newest history row = what plays now
        $sam = $this->fetchRow(
            "SELECT songID, artist, title, date_played, duration
            FROM {$this->table('historylist')}
            ORDER BY date_played DESC
            LIMIT 0,1"
        );

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
     * Opens the connection. PHP 8.1+ reports mysqli failures as exceptions by default, older versions
     * through connect_errno: both end as a PawException so the handler keeps its JSON contract.
     *
     * @throws \lib\PawException
     */
    private function connectToDatabase()
    {

        // Since 1.21 we also allow sockets and ports (mysqli takes null for whichever is not used)
        $p_url = parse_url($this->channel['stats']['host']);

        $this->channel['stats']['socket'] = null;
        $this->channel['stats']['port']   = null;

        // maybe sock?
        if (empty($p_url['host']) && is_file($this->channel['stats']['host'])) {

            $this->channel['stats']['socket'] = $this->channel['stats']['host'];
            $this->channel['stats']['host']   = '127.0.0.1';

        } elseif ( ! empty($p_url['port'])) { // Port added?

            $this->channel['stats']['host'] = $p_url['host'];
            $this->channel['stats']['port'] = $p_url['port'];

        }

        // Attempt connection. mysqli_init() + real_connect(), because timeouts must be set before connecting.
        $this->mysql = mysqli_init();
        if ( ! $this->mysql) {
            throw new PawException("Database connection failed, unable to initialize MySQLi!");
        }

        $this->mysql->options(MYSQLI_OPT_CONNECT_TIMEOUT, self::CONNECT_TIMEOUT);
        if (defined('MYSQLI_OPT_READ_TIMEOUT')) { // mysqlnd only
            $this->mysql->options(MYSQLI_OPT_READ_TIMEOUT, self::READ_TIMEOUT);
        }

        try {

            // @: PHP < 8.1 also raises a warning, which would end up in the JSON answer; the error is read below
            $connected = @$this->mysql->real_connect(
                $this->channel['stats']['host'],
                $this->channel['stats']['auth-user'],
                $this->channel['stats']['auth-pass'],
                $this->channel['stats']['db'],
                $this->channel['stats']['port'],
                $this->channel['stats']['socket']
            );

        } catch (mysqli_sql_exception $e) {

            throw new PawException("Database connection failed, MySQL returned: {$e->getMessage()}");

        }

        // Check if connection was established (PHP < 8.1 reports failures here, not as exceptions)
        if ( ! $connected || $this->mysql->connect_errno > 0) {
            throw new PawException("Database connection failed, MySQL returned: {$this->mysql->connect_error}");
        }

    }


    /**
     * Runs a query and returns its first row, null when there is none
     *
     * @param  string  $sql
     *
     * @return array|null
     * @throws \lib\PawException
     */
    private function fetchRow(string $sql): ?array
    {
        try {

            $result = $this->mysql->query($sql);

        } catch (mysqli_sql_exception $e) {

            throw new PawException("SAM Database query failed with error: {$e->getMessage()}");

        }

        if ($result === false) {
            throw new PawException("SAM Database query failed with error: {$this->mysql->error}");
        }

        $row = $result->fetch_assoc();
        $result->free();

        return $row ?: null;
    }


    /**
     * Backtick-quoted `database`.`table` reference (the database name is admin-entered, not SQL)
     *
     * @param  string  $table
     *
     * @return string
     */
    private function table(string $table): string
    {
        $db = str_replace('`', '', $this->channel['stats']['db']);

        return "`{$db}`.`{$table}`";
    }


    /**
     * A little different response for SAM as it uses database which could have faulty ID3 tag scans
     *
     * @param  array|null  $data
     *
     * @return array
     */
    private function handleQueryResponse(?array $data)
    {
        // Empty history table: fall back to the configured defaults
        if (empty($data)) {
            return $this->handleTrack(null, ['artist' => '', 'title' => '']);
        }

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
