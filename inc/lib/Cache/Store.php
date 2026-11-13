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
 * One cache backend. Stores only move strings: serializing values, key prefixing policy, logging and
 * the fallback to disk all live in lib\Cache, so each store is just the backend's own calls.
 *
 * Contract: a miss or an existing key is a normal answer (null / false). Anything that means the
 * backend itself is broken (connection lost, disk not writable) throws StoreException, which is
 * what lets lib\Cache tell "the lock is taken" apart from "the server is down".
 */
interface Store
{

    /**
     * @param  string  $key
     *
     * @return string|null  Stored payload, null on a miss or an expired entry
     * @throws StoreException
     */
    public function get(string $key): ?string;


    /**
     * @param  string  $key
     * @param  string  $value
     * @param  int  $ttl  Seconds, 0 = no expiry
     *
     * @throws StoreException
     */
    public function set(string $key, string $value, int $ttl): void;


    /**
     * Store only when the key does not exist yet, atomically (used for locks).
     *
     * @param  string  $key
     * @param  string  $value
     * @param  int  $ttl  Seconds, 0 = no expiry
     *
     * @return bool  False when the key already exists
     * @throws StoreException
     */
    public function add(string $key, string $value, int $ttl): bool;


    /**
     * Remove a key. A key that does not exist is not an error.
     *
     * @param  string  $key
     *
     * @throws StoreException
     */
    public function delete(string $key): void;


    /**
     * Remove every key of this PawTunes install (and only those, the backend may be shared).
     *
     * @throws StoreException
     */
    public function clear(): void;


    /**
     * @return string  Mode name as used in the config (disk, apcu, redis, memcached)
     */
    public function name(): string;

}
