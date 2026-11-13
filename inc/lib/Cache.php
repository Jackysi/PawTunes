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

namespace lib;

use lib\Cache\ApcuStore;
use lib\Cache\DiskStore;
use lib\Cache\MemcacheStore;
use lib\Cache\MemcachedStore;
use lib\Cache\RedisStore;
use lib\Cache\Store;
use lib\Cache\StoreException;

/**
 * The cache used by the player and the panel.
 *
 * Responsibilities kept here, once, instead of in every backend (lib\Cache\Store):
 * - values are serialized, so arrays, numbers and booleans round-trip on every backend;
 * - a backend that fails (at startup or mid-request) is replaced by the disk store for the rest of the
 *   request, and skipped for OFFLINE_SECONDS so a dead server costs one timeout, not one per request;
 * - failures are logged and reported to the panel through getDebugInfo().
 *
 * get() returns false on a miss, so storing false itself is indistinguishable from a miss.
 * Objects are not allowed in cached values (they are never unserialized as objects).
 */
final class Cache
{

    /**
     * Seconds a failed backend is skipped in favour of the disk store
     */
    private const OFFLINE_SECONDS = 30;

    /**
     * Disk key that records a recent backend failure (see OFFLINE_SECONDS)
     */
    private const OFFLINE_KEY = 'cache.backend.offline';

    /**
     * Default port per backend, when the configured host has none
     */
    private const DEFAULT_PORTS = ['redis' => 6379, 'memcache' => 11211, 'memcached' => 11211];

    /**
     * Key written and read back by prepare()
     */
    private const PROBE_KEY = 'cache.probe';

    /**
     * @var \lib\Cache\Store|null  Active store, null when not even the disk store works
     */
    private ?Store $store = null;

    /**
     * @var \lib\Cache\DiskStore|null  Fallback store, created on first need
     */
    private ?DiskStore $disk = null;

    /**
     * @var string
     */
    private string $requestedMode;

    /**
     * @var string
     */
    private string $path;

    /**
     * @var string
     */
    private string $extension;

    /**
     * @var string
     */
    private string $host;

    /**
     * @var array
     */
    private array $extra;

    /**
     * @var string  Prefix of every key, so installs sharing a Redis/Memcached/APCu do not collide
     */
    private string $namespace;

    /**
     * @var callable|null
     */
    private $logger;

    /**
     * @var string|null
     */
    private ?string $lastError = null;

    /**
     * @var string[]
     */
    private array $events = [];


    /**
     * @param  array  $options  mode (disk|apcu|redis|memcache|memcached), path, ext, host ("host:port" or a socket path),
     *                          extra (backend options), prefix, logger (callable(string $message))
     */
    public function __construct(array $options = [])
    {
        $this->requestedMode = $this->normalizeMode((string) ($options['mode'] ?? ''));
        $this->path          = ((string) ($options['path'] ?? '') !== '') ? (string) $options['path'] : getcwd().DIRECTORY_SEPARATOR.'cache';
        $this->extension     = ((string) ($options['ext'] ?? '') !== '') ? (string) $options['ext'] : '.cache';
        $this->host          = (string) ($options['host'] ?? '');
        $this->extra         = is_array($options['extra'] ?? null) ? $options['extra'] : [];
        $this->namespace     = str_replace(' ', '_', (string) ($options['prefix'] ?? ''));
        $this->logger        = is_callable($options['logger'] ?? null) ? $options['logger'] : null;

        $this->store = $this->boot();
    }


    /**
     * @param  string  $key
     *
     * @return mixed  The stored value, false on a miss
     */
    public function get(string $key)
    {
        $payload = $this->run(function (Store $store) use ($key) {
            return $store->get($key);
        }, null);

        if ($payload === null) {
            return false;
        }

        // A payload that is not valid serialized data (another app's key under the same name) is a miss
        $value = @unserialize($payload, ['allowed_classes' => false]);

        return ($value === false && $payload !== serialize(false)) ? false : $value;
    }


    /**
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $ttl  Seconds, 0 = no expiry
     *
     * @return bool
     */
    public function set(string $key, $value, int $ttl = 600): bool
    {
        return $this->run(function (Store $store) use ($key, $value, $ttl): bool {
            $store->set($key, serialize($value), max(0, $ttl));

            return true;
        }, false);
    }


    /**
     * Store only when the key does not exist yet, atomically. Used for locks: true means "you own it".
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $ttl  Seconds, 0 = no expiry
     *
     * @return bool  False when the key already exists (or no cache works at all)
     */
    public function add(string $key, $value, int $ttl): bool
    {
        return $this->run(function (Store $store) use ($key, $value, $ttl): bool {
            return $store->add($key, serialize($value), max(0, $ttl));
        }, false);
    }


    /**
     * @param  string  $key
     *
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->run(function (Store $store) use ($key): bool {
            $store->delete($key);

            return true;
        }, false);
    }


    /**
     * Remove every cached entry of this install.
     *
     * @return bool
     */
    public function clear(): bool
    {
        return $this->run(function (Store $store): bool {
            $store->clear();

            return true;
        }, false);
    }


    /**
     * Get a cache configuration ready before switching to it (panel settings save): a write/read round trip
     * proves the backend works, then this install's keys there are emptied, so entries left from an earlier
     * use of that backend are not served (the templates list never expires).
     *
     * The cache in use is not touched: nothing is logged and no backend is marked offline, so a wrong host
     * typed in the panel cannot send the live player to the disk fallback. A recorded failure of the previous
     * backend is removed, so the new one is tried right away instead of after OFFLINE_SECONDS.
     *
     * @param  array  $options  Same as the constructor (the prefix must be the one the player will use)
     *
     * @return string|null  Null when the backend is ready, otherwise why it cannot be used
     */
    public static function prepare(array $options): ?string
    {
        // Booting in disk mode reads no offline marker and connects to nothing
        $cache = new self(['mode' => 'disk', 'logger' => null] + $options);
        $mode  = self::normalizeMode((string) ($options['mode'] ?? ''));

        try {

            $store = ($mode === 'disk') ? $cache->disk : $cache->connect($mode);
            if ($store === null) {
                return $cache->lastError;
            }

            $store->set(self::PROBE_KEY, '1', 60);
            if ($store->get(self::PROBE_KEY) !== '1') {
                throw new StoreException('A test value could not be read back.');
            }

            $store->clear();

        } catch (StoreException $e) {
            return "[{$mode}] {$e->getMessage()}";
        }

        // Disk mode: clear() above already removed the marker with the other entries
        if ($mode !== 'disk' && $cache->disk !== null) {
            try {
                $cache->disk->delete(self::OFFLINE_KEY);
            } catch (StoreException $e) {
                // The marker expires on its own within OFFLINE_SECONDS
            }
        }

        return null;
    }


    /**
     * State for the panel's requirement check.
     *
     * @return array
     */
    public function getDebugInfo(): array
    {
        $activeMode = ($this->store !== null) ? $this->store->name() : null;

        return [
            'requested_mode'  => $this->requestedMode,
            'active_mode'     => $activeMode,
            'fallback_active' => $activeMode !== null && $activeMode !== $this->requestedMode,
            'startup'         => $this->store !== null,
            'path'            => $this->path,
            'host'            => $this->host,
            'last_error'      => $this->lastError,
            'events'          => $this->events,
        ];
    }


    /**
     * Pick the store for this request: the configured backend, or the disk store when the backend is
     * unavailable or failed within the last OFFLINE_SECONDS.
     *
     * @return \lib\Cache\Store|null
     */
    private function boot(): ?Store
    {
        if ($this->requestedMode === 'disk') {
            return $this->disk();
        }

        $recentFailure = $this->recentFailure();
        if ($recentFailure !== null) {

            // Not logged again: the failure itself was logged when it happened
            $this->lastError = "[{$this->requestedMode}] skipped for up to ".self::OFFLINE_SECONDS." s after a failure: {$recentFailure}";

            return $this->disk();

        }

        try {
            return $this->connect($this->requestedMode);
        } catch (StoreException $e) {
            $this->recordFailure($this->requestedMode, 'startup', $e->getMessage(), true);

            return $this->disk();
        }
    }


    /**
     * @param  string  $mode
     *
     * @return \lib\Cache\Store
     * @throws StoreException
     */
    private function connect(string $mode): Store
    {
        switch ($mode) {
            case 'apcu':
                return new ApcuStore($this->namespace);

            case 'redis':
                [$host, $port] = $this->hostAndPort(self::DEFAULT_PORTS['redis']);

                return new RedisStore($host, $port, $this->namespace, $this->extra);

            case 'memcache':
                [$host, $port] = $this->hostAndPort(self::DEFAULT_PORTS['memcache']);

                return new MemcacheStore($host, $port, $this->namespace);

            case 'memcached':
                [$host, $port] = $this->hostAndPort(self::DEFAULT_PORTS['memcached']);

                return new MemcachedStore($host, $port, $this->namespace, $this->extra);

            default:
                throw new StoreException("Unknown cache mode \"{$mode}\".");
        }
    }


    /**
     * The disk store, created once. Null when the cache directory is unusable.
     *
     * @return \lib\Cache\DiskStore|null
     */
    private function disk(): ?DiskStore
    {
        if ($this->disk === null) {
            try {
                $this->disk = new DiskStore($this->path, $this->extension);
            } catch (StoreException $e) {
                $this->recordFailure('disk', 'startup', $e->getMessage(), false);
            }
        }

        return $this->disk;
    }


    /**
     * Run one operation on the active store. When a backend other than disk fails, the disk store takes
     * over for the rest of this request and the operation is retried on it. A failing disk store keeps
     * serving: one unwritable file must not switch the cache off for the whole request.
     *
     * @param  callable  $operation  function (Store $store)
     * @param  mixed  $default  Result when no store works
     *
     * @return mixed
     */
    private function run(callable $operation, $default)
    {
        if ($this->store === null) {
            return $default;
        }

        try {
            return $operation($this->store);
        } catch (StoreException $e) {

            $failed = $this->store->name();
            $this->recordFailure($failed, 'runtime', $e->getMessage(), $failed !== 'disk');

            if ($failed === 'disk') {
                return $default;
            }

            $this->store = $this->disk();

            return $this->run($operation, $default);

        }
    }


    /**
     * @param  string  $mode
     * @param  string  $stage  startup or runtime
     * @param  string  $reason
     * @param  bool  $markOffline  Skip this backend for OFFLINE_SECONDS on the next requests
     */
    private function recordFailure(string $mode, string $stage, string $reason, bool $markOffline): void
    {
        $message          = "[{$mode}] {$stage}: {$reason}";
        $this->lastError  = $message;
        $this->events[]   = date('Y-m-d H:i:s').' '.$message;

        if ($this->logger !== null && (error_reporting() & (E_WARNING | E_USER_WARNING)) !== 0) {
            call_user_func($this->logger, "WARNING: Cache {$message}");
        }

        $disk = $markOffline ? $this->disk() : null;
        if ($disk !== null) {
            try {
                $disk->set(self::OFFLINE_KEY, $message, self::OFFLINE_SECONDS);
            } catch (StoreException $e) {
                // Nothing to add: the cache directory problem is reported when the disk store is used
            }
        }
    }


    /**
     * @return string|null  The failure recorded within OFFLINE_SECONDS, if any
     */
    private function recentFailure(): ?string
    {
        $disk = $this->disk();

        return ($disk !== null) ? $disk->get(self::OFFLINE_KEY) : null;
    }


    /**
     * Older configs may name "apc", the PHP 5 extension that APCu replaced. Public so the panel reads
     * a configured mode the same way the cache does.
     *
     * @param  string  $mode
     *
     * @return string
     */
    public static function normalizeMode(string $mode): string
    {
        $mode    = strtolower(trim($mode));
        $aliases = ['' => 'disk', 'apc' => 'apcu'];

        return $aliases[$mode] ?? $mode;
    }


    /**
     * @param  int  $defaultPort
     *
     * @return array{0:string,1:int}  [host, port]; port 0 for a unix socket path
     */
    private function hostAndPort(int $defaultPort): array
    {
        $host = ($this->host !== '') ? $this->host : '127.0.0.1';

        if (strpos($host, '/') !== false) {
            return [preg_replace('~^unix://~', '', $host), 0];
        }

        // "[::1]:6379" or "10.0.0.5:6379". A bare IPv6 address ("::1") has several colons and keeps the default port.
        if (preg_match('/^\[(.+)]:(\d+)$/', $host, $matches) || preg_match('/^([^:]+):(\d+)$/', $host, $matches)) {
            return [$matches[1], (int) $matches[2]];
        }

        return [trim($host, '[]'), $defaultPort];
    }

}
