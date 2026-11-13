<?php

namespace lib\Cache;

use Redis;
use Throwable;

class RedisCache implements BackendInterface
{

    use ParsesCacheHost;
    use TracksBackendFailures;

    protected array $options;

    protected ?Redis $redis = null;


    public function __construct(array $options)
    {
        $this->options = $options;
    }


    public function isAvailable(): bool
    {
        if ( ! extension_loaded('redis') || ! class_exists(Redis::class)) {
            return $this->fail('PHP extension "redis" is not loaded.');
        }

        $this->redis = new Redis();
        [$host, $port] = $this->parseCacheHost($this->options['host']);

        try {
            if ( ! $this->redis->pconnect($host, $port, 2)) {
                return $this->fail("Redis connection to {$host}:{$port} failed.");
            }

            if (isset($this->options['extra']['auth']) && ! $this->redis->auth($this->options['extra']['auth'])) {
                return $this->fail("Redis authentication failed for {$host}:{$port}.");
            }

            foreach ($this->options['extra'] as $option => $value) {
                if ($option === 'auth') {
                    continue;
                }

                $this->redis->setOption($option, $value);
            }
        } catch (Throwable $e) {
            $this->redis = null;

            return $this->fail("Redis connection failed: {$e->getMessage()}");
        }

        $this->clearFailure();

        return true;
    }


    public function get(string $key)
    {
        try {
            $data = $this->redis->get($key);
            $this->clearFailure();

            return $data;
        } catch (Throwable $e) {
            $this->fail("Redis get failed for key \"{$key}\": {$e->getMessage()}");
            throw $e;
        }
    }


    public function set(string $key, $data, int $ttl): bool
    {
        if ($ttl <= 0) {
            $stored = (bool) $this->redis->set($key, $data);
        } else {
            $stored = (bool) $this->redis->setex($key, $ttl, $data);
        }

        if ( ! $stored) {
            return $this->fail("Redis failed to store key \"{$key}\".");
        }

        $this->clearFailure();

        return true;
    }


    public function add(string $key, $data, int $ttl): bool
    {
        // SET NX EX is a single atomic command; Redis answers nil (false here) when the key exists
        $options = ($ttl > 0) ? ['nx', 'ex' => $ttl] : ['nx'];

        return (bool) $this->redis->set($key, $data, $options);
    }


    public function delete(string $key): bool
    {
        if (method_exists($this->redis, 'delete')) {
            $deleted = (bool) $this->redis->delete($key);
        } else {
            $deleted = (bool) $this->redis->del($key);
        }

        if ($deleted) {
            $this->clearFailure();
        }

        return $deleted;
    }


    public function direct()
    {
        return $this->redis;
    }


    public function mode(): string
    {
        return 'redis';
    }

}
