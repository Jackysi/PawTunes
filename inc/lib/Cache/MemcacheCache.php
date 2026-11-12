<?php

namespace lib\Cache;

use Memcache;
use Throwable;

class MemcacheCache implements BackendInterface
{

    use ParsesCacheHost;
    use TracksBackendFailures;

    protected array $options;

    protected ?Memcache $memcache = null;


    public function __construct(array $options)
    {
        $this->options = $options;
    }


    public function isAvailable(): bool
    {
        if ( ! extension_loaded('memcache') || ! class_exists(Memcache::class)) {
            return $this->fail('PHP extension "memcache" is not loaded.');
        }

        $this->memcache = new Memcache();
        [$host, $port] = $this->parseCacheHost($this->options['host']);

        try {
            if ( ! $this->memcache->addServer($host, $port)) {
                return $this->fail("Memcache server {$host}:{$port} could not be added.");
            }
        } catch (Throwable $e) {
            $this->memcache = null;

            return $this->fail("Memcache setup failed: {$e->getMessage()}");
        }

        $this->clearFailure();

        return true;
    }


    public function get(string $key)
    {
        try {
            $data = $this->memcache->get($key);
            $this->clearFailure();

            return $data;
        } catch (Throwable $e) {
            $this->fail("Memcache get failed for key \"{$key}\": {$e->getMessage()}");
            throw $e;
        }
    }


    public function set(string $key, $data, int $ttl): bool
    {
        if ($this->memcache->replace($key, $data, false, $ttl)) {
            return true;
        }

        if ( ! $this->memcache->set($key, $data, false, $ttl)) {
            return $this->fail("Memcache failed to store key \"{$key}\".");
        }

        $this->clearFailure();

        return true;
    }


    public function delete(string $key): bool
    {
        $deleted = (bool) $this->memcache->delete($key);
        if ($deleted) {
            $this->clearFailure();
        }

        return $deleted;
    }


    public function direct()
    {
        return $this->memcache;
    }


    public function mode(): string
    {
        return 'memcache';
    }

}
