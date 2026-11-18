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

use Memcached;
use Throwable;

/**
 * Memcached through the "memcached" extension. Its result codes tell a miss or an existing key apart
 * from a failed server, so every failure is detected.
 */
final class MemcachedStore extends MemcacheFamilyStore
{

    /**
     * Milliseconds to wait for a connection: a dead server must cost this much, not a full PHP timeout
     */
    private const CONNECT_TIMEOUT_MS = 1000;

    /**
     * @var Memcached
     */
    private Memcached $memcached;


    /**
     * @param  string  $host  Host name, IP, or a unix socket path
     * @param  int  $port  0 for a unix socket
     * @param  string  $namespace
     * @param  array  $options  Memcached::OPT_* => value pairs
     *
     * @throws StoreException
     */
    public function __construct(string $host, int $port, string $namespace, array $options)
    {
        if ( ! class_exists(Memcached::class)) {
            throw new StoreException('PHP extension "memcached" is not loaded.');
        }

        // Persistent: the same PHP worker reuses the connection, so servers are only added on first use
        $this->memcached = new Memcached('pawtunes.'.md5("{$host}:{$port}"));
        if (count($this->memcached->getServerList()) === 0) {

            try {

                $this->memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, self::CONNECT_TIMEOUT_MS);
                foreach ($options as $option => $value) {
                    if (is_int($option)) {
                        $this->memcached->setOption($option, $value);
                    }
                }

            } catch (Throwable $e) {
                throw new StoreException("Memcached option rejected: {$e->getMessage()}", 0, $e);
            }

            $this->memcached->addServer($host, $port);

        }

        parent::__construct($namespace);
    }


    public function name(): string
    {
        return 'memcached';
    }


    protected function read(string $key): ?string
    {
        $value = $this->memcached->get($key);
        if ($value === false) {
            $this->assertResult(Memcached::RES_NOTFOUND);

            return null;
        }

        return (string) $value;
    }


    protected function write(string $key, string $value, int $expiry): void
    {
        if ( ! $this->memcached->set($key, $value, $expiry)) {
            $this->assertResult();
        }
    }


    protected function writeNew(string $key, string $value, int $expiry): bool
    {
        if ($this->memcached->add($key, $value, $expiry)) {
            return true;
        }

        // NOTSTORED means the key exists, any other code means the server failed
        $this->assertResult(Memcached::RES_NOTSTORED);

        return false;
    }


    protected function remove(string $key): void
    {
        if ( ! $this->memcached->delete($key)) {
            $this->assertResult(Memcached::RES_NOTFOUND);
        }
    }


    /**
     * Throw unless the last call ended with success or the given expected code.
     *
     * @param  int|null  $expected  A non-success result code that is a normal answer (e.g. RES_NOTFOUND)
     *
     * @throws StoreException
     */
    private function assertResult(?int $expected = null): void
    {
        $code = $this->memcached->getResultCode();
        if ($code === Memcached::RES_SUCCESS || $code === $expected) {
            return;
        }

        throw new StoreException("Memcached: {$this->memcached->getResultMessage()} (code {$code}).");
    }

}