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

use lib\Cache\ApcCache;
use lib\Cache\BackendInterface;
use lib\Cache\DiskCache;
use lib\Cache\MemcacheCache;
use lib\Cache\MemcachedCache;
use lib\Cache\RedisCache;
use Throwable;

class Cache
{

    /**
     * @var array
     */
    protected array $options;

    /**
     * @var string
     */
    protected string $requestedMode = 'disk';

    /**
     * @var string|null
     */
    protected ?string $lastError = null;

    /**
     * @var array
     */
    protected array $debugEvents = [];

    /**
     * @var \lib\Cache\BackendInterface|null
     */
    protected ?BackendInterface $backend = null;

    /**
     * @var bool
     */
    protected bool $startup = false;

    /**
     * Key metadata: key => ['expires' => unix timestamp, 0 = never]. In disk mode this is the only
     * expiry information there is. The other backends expire keys on their own and the store only
     * serves deleteAll(), flush() and clean().
     *
     * @var array
     */
    private array $store = [];

    /**
     * Entries changed during this request: key => entry, or null when the key was removed. The store
     * is written back only when this is not empty (at the end of the request), and the changes are
     * merged into the latest stored copy so concurrent requests do not wipe each other's entries.
     *
     * @var array
     */
    private array $storeChanges = [];


    /**
     * @param  array  $options
     */
    public function __construct(array $options = [])
    {
        $this->options       = $this->normalizeOptions($options);
        $this->requestedMode = $this->options['mode'];
        $this->startup = $this->bootBackend($this->options['mode']);

        if ($this->startup) {
            $this->loadStore();
        }
    }


    protected function normalizeOptions(array $options): array
    {
        $defaults = [
            'path'    => realpath(getcwd()).DIRECTORY_SEPARATOR.'cache',
            'host'    => '127.0.0.1:11211',
            'ext'     => '.cache',
            'encrypt' => false,
            'mode'    => 'disk',
            'extra'   => [],
            'prefix'  => '',
            'logger'  => null,
        ];

        $options = $options + $defaults;

        if (empty($options['path'])) {
            $options['path'] = $defaults['path'];
        }

        if (empty($options['mode'])) {
            $options['mode'] = 'disk';
        }

        if ( ! is_array($options['extra'])) {
            $options['extra'] = [];
        }

        return $options;
    }


    protected function bootBackend(string $mode): bool
    {
        $backend = $this->makeBackend($mode);
        if ($backend->isAvailable()) {
            $this->backend         = $backend;
            $this->options['mode'] = $backend->mode();

            return true;
        }

        $this->recordFailure($backend->mode(), 'startup', $backend->failureReason() ?? "Cache backend \"{$mode}\" is unavailable.");

        if ($mode !== 'disk') {
            return $this->bootBackend('disk');
        }

        return false;
    }


    protected function makeBackend(string $mode): BackendInterface
    {
        switch ($mode) {
            case 'apc':
            case 'apcu':
                return new ApcCache($mode);

            case 'redis':
                return new RedisCache($this->options);

            case 'memcache':
                return new MemcacheCache($this->options);

            case 'memcached':
                return new MemcachedCache($this->options);

            case 'disk':
            default:
                return new DiskCache($this->options);
        }
    }


    protected function activateDiskFallback(): bool
    {
        if ($this->options['mode'] === 'disk') {
            return false;
        }

        $backend = new DiskCache($this->options);
        if ( ! $backend->isAvailable()) {
            $this->recordFailure('disk', 'fallback', $backend->failureReason() ?? 'Disk cache fallback is unavailable.');

            return false;
        }

        $this->backend         = $backend;
        $this->options['mode'] = 'disk';
        $this->loadStore();

        return true;
    }


    protected function recordFailure(string $mode, string $operation, string $reason): void
    {
        $message = "[{$mode}] {$operation}: {$reason}";

        $this->lastError     = $message;
        $this->debugEvents[] = [
            'mode'      => $mode,
            'operation' => $operation,
            'reason'    => $reason,
            'date'      => date('Y-m-d H:i:s'),
        ];

        $this->writeWarningLog($message);
    }


    protected function writeWarningLog(string $message): void
    {
        if ( ! is_callable($this->options['logger'])) {
            return;
        }

        if ((error_reporting() & (E_WARNING | E_USER_WARNING)) === 0) {
            return;
        }

        call_user_func($this->options['logger'], "WARNING: Cache {$message}");
    }


    /**
     * Load the store from the active backend. Pending changes are dropped: they belonged to the
     * backend that was active before (this runs at boot and when the disk fallback kicks in).
     */
    protected function loadStore(): void
    {
        $this->store        = [];
        $this->storeChanges = [];

        $store = $this->backendGet($this->parseKey('cache_store'));
        if (is_array($store)) {
            $this->store = $this->normalizeStore($store);
        }
    }


    /**
     * Keep only the expiry of each entry. Older releases also stored a "hits" counter that nothing
     * read; it disappears from the stored copy on the next write.
     *
     * @param  array  $store
     *
     * @return array
     */
    protected function normalizeStore(array $store): array
    {
        $normalized = [];
        foreach ($store as $key => $meta) {
            $normalized[$key] = ['expires' => (int) ($meta['expires'] ?? 0)];
        }

        return $normalized;
    }


    /**
     * Persist the pending changes. They are merged into the latest stored copy (another request may
     * have added or removed keys since this one booted), expired entries are purged while we are
     * writing anyway, and the result is written once.
     *
     * @return bool
     */
    protected function writeStore(): bool
    {
        if (count($this->storeChanges) < 1) {
            return true;
        }

        // When the latest copy cannot be read, fall back to this request's own view of it
        $latest = $this->backendGet($this->parseKey('cache_store'), false);
        $store  = is_array($latest) ? $this->normalizeStore($latest) : $this->store;

        foreach ($this->storeChanges as $key => $entry) {
            if ($entry === null) {
                unset($store[$key]);
            } else {
                $store[$key] = $entry;
            }
        }

        $now = time();
        foreach ($store as $key => $entry) {
            if ($entry['expires'] !== 0 && $entry['expires'] <= $now) {
                unset($store[$key]);
            }
        }

        $this->store        = $store;
        $this->storeChanges = [];

        return $this->backendSet($this->parseKey('cache_store'), $store, 0, false);
    }


    protected function isFresh(string $key): bool
    {
        if ( ! isset($this->store[$key])) {
            return false;
        }

        return $this->store[$key]['expires'] === 0 || time() < $this->store[$key]['expires'];
    }


    protected function remember(string $key, int $ttl): void
    {
        $entry = ['expires' => ($ttl === 0) ? 0 : time() + $ttl];

        $this->store[$key]        = $entry;
        $this->storeChanges[$key] = $entry;
    }


    /**
     * Drop a key from the store. Keys this request never saw (lock keys, for instance) are not tracked,
     * so forgetting them changes nothing and does not cause a write.
     *
     * @param  string  $key
     *
     * @return bool  Whether the store changed
     */
    protected function forget(string $key): bool
    {
        if ( ! isset($this->store[$key])) {
            return false;
        }

        unset($this->store[$key]);
        $this->storeChanges[$key] = null;

        return true;
    }


    /**
     * @param  string  $key
     * @param  bool  $fallback
     *
     * @return mixed|false
     */
    protected function backendGet(string $key, bool $fallback = true)
    {
        $mode = $this->backend->mode();

        try {
            return $this->backend->get($key);
        } catch (Throwable $e) {
            $this->recordFailure($mode, 'get', $this->backend->failureReason() ?? $e->getMessage());

            if ($fallback && $this->activateDiskFallback()) {
                return $this->backend->get($key);
            }
        }

        return false;
    }


    /**
     * @param  string  $key
     * @param  mixed  $data
     * @param  int  $ttl
     * @param  bool  $fallback
     *
     * @return bool
     */
    protected function backendSet(string $key, $data, int $ttl, bool $fallback = true): bool
    {
        $mode = $this->backend->mode();

        try {
            $stored = $this->backend->set($key, $data, $ttl);
            if ( ! $stored) {
                $this->recordFailure($mode, 'set', $this->backend->failureReason() ?? "Cache backend \"{$mode}\" returned false.");
            }

            if ($stored || ! $fallback || $this->options['mode'] === 'disk') {
                return $stored;
            }
        } catch (Throwable $e) {
            $this->recordFailure($mode, 'set', $this->backend->failureReason() ?? $e->getMessage());

            if ( ! $fallback) {
                return false;
            }
        }

        if ($this->activateDiskFallback()) {
            return $this->backend->set($key, $data, $ttl);
        }

        return false;
    }


    protected function backendDelete(string $key, bool $fallback = true): bool
    {
        $mode = $this->backend->mode();

        try {
            return $this->backend->delete($key);
        } catch (Throwable $e) {
            $this->recordFailure($mode, 'delete', $this->backend->failureReason() ?? $e->getMessage());

            if ($fallback && $this->activateDiskFallback()) {
                return $this->backend->delete($key);
            }
        }

        return false;
    }


    /**
     * Get existing record from cache. Missing records return false.
     *
     * @param  string  $key
     *
     * @return mixed|false
     */
    public function get($key)
    {
        if ( ! $this->startup) {
            return false;
        }

        $wasDisk = $this->options['mode'] === 'disk';
        if ($wasDisk && $key !== 'cache_store' && ! $this->isFresh($key)) {
            return false;
        }

        $data = $this->backendGet($this->parseKey($key));

        if ( ! $wasDisk && $this->options['mode'] === 'disk' && $key !== 'cache_store' && ! $this->isFresh($key)) {
            return false;
        }

        return $data;
    }


    /**
     * Set cache by key, data and expiration time.
     *
     * @param  string  $key
     * @param  mixed  $data
     * @param  int  $ttl  0 means unlimited
     *
     * @return bool
     */
    public function set($key, $data, $ttl = 600): bool
    {
        if ( ! $this->startup) {
            return false;
        }

        $stored = $this->backendSet($this->parseKey($key), $data, (int) $ttl);
        if ($key === 'cache_store') {
            return $stored;
        }

        if ($stored) {
            $this->remember($key, (int) $ttl);
        }

        return $stored;
    }


    /**
     * Delete key from cache.
     *
     * @param  string  $key
     *
     * @return bool
     */
    public function delete($key): bool
    {
        if ( ! $this->startup) {
            return false;
        }

        $deleted = $this->backendDelete($this->parseKey($key));
        if ($deleted && $key !== 'cache_store') {
            $this->forget($key);
        }

        return $deleted;
    }


    /**
     * Convert keys to values supported by all caching modes.
     *
     * @param  string  $key
     *
     * @return string
     */
    protected function parseKey($key): string
    {
        return str_replace(' ', '_', $this->options['prefix'].$key);
    }


    /**
     * Delete all keys matching a regex.
     *
     * @param  string  $regex
     *
     * @return array|bool
     */
    public function deleteAll($regex = '.*')
    {
        if ( ! $this->startup || count($this->store) < 1) {
            return false;
        }

        $deleted = [];
        foreach (array_keys($this->store) as $key) {
            if ($key === 'cache_store') {
                continue;
            }

            if (preg_match('/'.$regex.'/i', $key)) {
                $deleted[] = $key;
                $this->delete($key);
            }
        }

        return $deleted;
    }


    /**
     * Access the underlying caching object directly.
     *
     * @return mixed|false
     */
    public function direct()
    {
        if ( ! $this->startup) {
            return false;
        }

        if ($this->options['mode'] === 'disk') {
            return $this;
        }

        return $this->backend->direct();
    }


    public function getLastError(): ?string
    {
        return $this->lastError;
    }


    public function getDebugInfo(): array
    {
        return [
            'requested_mode'  => $this->requestedMode,
            'active_mode'     => $this->options['mode'],
            'fallback_active' => $this->startup && $this->requestedMode !== $this->options['mode'],
            'startup'         => $this->startup,
            'path'            => $this->options['path'],
            'host'            => $this->options['host'],
            'last_error'      => $this->lastError,
            'events'          => $this->debugEvents,
        ];
    }


    /**
     * Clean up expired or missing cache entries from the metadata store.
     *
     * @return array
     */
    public function clean(): array
    {
        $cleaned = [];
        if ( ! $this->startup || count($this->store) < 1) {
            return $cleaned;
        }

        foreach (array_keys($this->store) as $key) {
            if ( ! $this->get($key)) {
                $this->forget($key);
                $cleaned[] = $key;

                if ($this->options['mode'] === 'disk') {
                    $this->backendDelete($this->parseKey($key), false);
                }
            }
        }

        return $cleaned;
    }


    /**
     * Flush all cache keys tracked by this cache instance.
     *
     * @return array|bool
     */
    public function flush()
    {
        if ( ! $this->startup) {
            return false;
        }

        $deleted = [];
        foreach (array_keys($this->store) as $key) {
            $deleted[] = $key;
            $this->backendDelete($this->parseKey($key));
        }

        // The store itself is gone, so there is nothing left to write back at the end of the request
        $this->store        = [];
        $this->storeChanges = [];
        $this->backendDelete($this->parseKey('cache_store'), false);

        return $deleted;
    }


    public function __destruct()
    {
        if ($this->startup && count($this->storeChanges) > 0) {
            $this->writeStore();
        }
    }

}
