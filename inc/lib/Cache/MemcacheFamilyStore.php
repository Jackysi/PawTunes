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

/**
 * What the two memcache PHP extensions ("memcached" and the older "memcache") share: key rules, expiry
 * rules, and clearing by generation. Subclasses only connect and wrap their client's four calls.
 *
 * Memcache servers cannot list keys, so clear() cannot delete "only this install's keys". Instead every
 * key carries a generation number, and clear() moves to a new generation: old keys are never read again
 * and the server evicts them on its own. Reading the generation is one lookup per request, and it
 * doubles as the check that the server is reachable.
 */
abstract class MemcacheFamilyStore implements Store
{

    /**
     * Memcache reads an expiry above 30 days as a unix timestamp instead of a duration
     */
    private const MAX_RELATIVE_TTL = 2592000;

    /**
     * @var string  Install prefix, without the generation
     */
    private string $namespace;

    /**
     * @var string  Install prefix plus the current generation, prefixes every data key
     */
    private string $prefix;


    /**
     * Call at the end of the subclass constructor, once the client is connected.
     *
     * @param  string  $namespace
     *
     * @throws StoreException
     */
    protected function __construct(string $namespace)
    {
        $this->namespace = $namespace;
        $this->prefix    = $namespace.$this->generation().'.';
    }


    /**
     * @param  string  $key  Full key
     *
     * @return string|null  Null on a miss
     * @throws StoreException
     */
    abstract protected function read(string $key): ?string;


    /**
     * @param  string  $key  Full key
     * @param  string  $value
     * @param  int  $expiry  As the server expects it (see expiry())
     *
     * @throws StoreException
     */
    abstract protected function write(string $key, string $value, int $expiry): void;


    /**
     * @param  string  $key  Full key
     * @param  string  $value
     * @param  int  $expiry  As the server expects it (see expiry())
     *
     * @return bool  False when the key exists
     * @throws StoreException
     */
    abstract protected function writeNew(string $key, string $value, int $expiry): bool;


    /**
     * @param  string  $key  Full key
     *
     * @throws StoreException
     */
    abstract protected function remove(string $key): void;


    public function get(string $key): ?string
    {
        return $this->read($this->key($key));
    }


    public function set(string $key, string $value, int $ttl): void
    {
        $this->write($this->key($key), $value, $this->expiry($ttl));
    }


    public function add(string $key, string $value, int $ttl): bool
    {
        return $this->writeNew($this->key($key), $value, $this->expiry($ttl));
    }


    public function delete(string $key): void
    {
        $this->remove($this->key($key));
    }


    public function clear(): void
    {
        $generation = (string) mt_rand(1, PHP_INT_MAX);
        $this->write($this->namespace.'generation', $generation, 0);
        $this->prefix = $this->namespace.$generation.'.';
    }


    /**
     * Current generation of this install's keys, created on first use.
     *
     * @return string
     * @throws StoreException
     */
    private function generation(): string
    {
        $key        = $this->namespace.'generation';
        $generation = $this->read($key);
        if ($generation !== null) {
            return $generation;
        }

        // Two requests may start a generation at once: writeNew() lets one win, the other reads the winner's
        $generation = (string) mt_rand(1, PHP_INT_MAX);
        if ($this->writeNew($key, $generation, 0)) {
            return $generation;
        }

        return $this->read($key) ?? $generation;
    }


    /**
     * Keys are at most 250 bytes without whitespace or control characters; others are hashed.
     *
     * @param  string  $key
     *
     * @return string
     */
    private function key(string $key): string
    {
        $full = $this->prefix.$key;
        if (strlen($full) > 250 || preg_match('/[\s\x00-\x1f\x7f]/', $full)) {
            $full = $this->prefix.md5($key);
        }

        return $full;
    }


    /**
     * @param  int  $ttl  Seconds, 0 = no expiry
     *
     * @return int  A duration up to 30 days, a unix timestamp beyond that
     */
    private function expiry(int $ttl): int
    {
        return ($ttl > self::MAX_RELATIVE_TTL) ? time() + $ttl : $ttl;
    }

}
