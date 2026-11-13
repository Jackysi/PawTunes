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

use APCUIterator;

/**
 * APCu: shared memory of one PHP server (all PHP-FPM workers of a pool share it, the CLI does not).
 */
final class ApcuStore implements Store
{

    /**
     * @var string  Prefix of every key of this install
     */
    private string $namespace;


    /**
     * @param  string  $namespace
     *
     * @throws StoreException
     */
    public function __construct(string $namespace)
    {
        if ( ! function_exists('apcu_enabled') || ! apcu_enabled()) {
            throw new StoreException('APCu is not available (extension not loaded, or apc.enable_cli is off on the command line).');
        }

        $this->namespace = $namespace;
    }


    public function get(string $key): ?string
    {
        $value = apcu_fetch($this->namespace.$key, $found);

        return $found ? (string) $value : null;
    }


    public function set(string $key, string $value, int $ttl): void
    {
        if ( ! apcu_store($this->namespace.$key, $value, $ttl)) {
            throw new StoreException("APCu could not store \"{$key}\" (shared memory full?).");
        }
    }


    public function add(string $key, string $value, int $ttl): bool
    {
        // APCu reports a full cache the same way as an existing key, so a full cache reads as "lock taken"
        return apcu_add($this->namespace.$key, $value, $ttl);
    }


    public function delete(string $key): void
    {
        apcu_delete($this->namespace.$key);
    }


    public function clear(): void
    {
        apcu_delete(new APCUIterator('/^'.preg_quote($this->namespace, '/').'/', APC_ITER_KEY));
    }


    public function name(): string
    {
        return 'apcu';
    }

}
