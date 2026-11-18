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

namespace lib\Cache;

use Memcache;

/**
 * Memcache through the older "memcache" extension. Its calls answer false both for "miss / key exists"
 * and for "server down", so a false from add() or delete() is double-checked with getStats(), which only
 * fails when the server is gone. That is what keeps a dead server from looking like a taken lock.
 * A get() that fails reads as a miss; the connect at startup is what catches a server that is down.
 */
final class MemcacheStore extends MemcacheFamilyStore
{

    /**
     * Seconds to wait for a connection: a dead server must cost this much, not a full PHP timeout
     */
    private const CONNECT_TIMEOUT = 1;

    /**
     * @var Memcache
     */
    private Memcache $memcache;


    /**
     * @param  string  $host  Host name, IP, or a unix socket path
     * @param  int  $port  0 for a unix socket
     * @param  string  $namespace
     *
     * @throws StoreException
     */
    public function __construct(string $host, int $port, string $namespace)
    {
        if ( ! class_exists(Memcache::class)) {
            throw new StoreException('PHP extension "memcache" is not loaded.');
        }

        $this->memcache = new Memcache();

        // Persistent: the same PHP worker reuses the connection
        $server = ($port > 0) ? $host : 'unix://'.$host;
        if ( ! @$this->memcache->pconnect($server, $port, self::CONNECT_TIMEOUT)) {
            throw new StoreException("Memcache connection to {$host}:{$port} failed.");
        }

        parent::__construct($namespace);
    }


    public function name(): string
    {
        return 'memcache';
    }


    protected function read(string $key): ?string
    {
        $value = $this->memcache->get($key);

        return ($value === false) ? null : (string) $value;
    }


    protected function write(string $key, string $value, int $expiry): void
    {
        if ( ! $this->memcache->set($key, $value, 0, $expiry)) {
            throw new StoreException("Memcache could not store \"{$key}\".");
        }
    }


    protected function writeNew(string $key, string $value, int $expiry): bool
    {
        if ($this->memcache->add($key, $value, 0, $expiry)) {
            return true;
        }

        $this->assertAlive();

        return false;
    }


    protected function remove(string $key): void
    {
        if ( ! $this->memcache->delete($key)) {
            $this->assertAlive();
        }
    }


    /**
     * @throws StoreException
     */
    private function assertAlive(): void
    {
        if (@$this->memcache->getStats() === false) {
            throw new StoreException('Memcache server is not responding.');
        }
    }

}