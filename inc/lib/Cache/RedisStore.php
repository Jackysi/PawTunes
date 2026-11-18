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

use Redis;
use Throwable;

/**
 * Redis through the phpredis extension. phpredis throws RedisException when the connection drops,
 * every call is wrapped so that surfaces as a StoreException.
 */
final class RedisStore implements Store
{

    /**
     * Seconds to wait for a connection: a dead Redis must cost this much once, not a full PHP timeout
     */
    private const CONNECT_TIMEOUT = 1.0;

    /**
     * @var Redis
     */
    private Redis $redis;

    /**
     * @var string  Prefix of every key of this install
     */
    private string $namespace;


    /**
     * @param  string  $host  Host name, IP, or a unix socket path
     * @param  int  $port  0 for a unix socket
     * @param  string  $namespace
     * @param  array  $options  "auth" (password, or [user, password]) plus Redis::OPT_* => value pairs.
     *                          Do not set Redis::OPT_PREFIX, the namespace already prefixes every key.
     *
     * @throws StoreException
     */
    public function __construct(string $host, int $port, string $namespace, array $options)
    {
        if ( ! class_exists(Redis::class)) {
            throw new StoreException('PHP extension "redis" is not loaded.');
        }

        $this->namespace = $namespace;

        $this->call(function () use ($host, $port, $options) {

            // Persistent: the connection is reused by the same PHP worker, so a poll pays no TCP handshake
            $this->redis = new Redis();
            $connected   = ($port > 0)
                ? $this->redis->pconnect($host, $port, self::CONNECT_TIMEOUT)
                : $this->redis->pconnect($host);

            if ( ! $connected) {
                throw new StoreException("Redis connection to {$host}:{$port} failed.");
            }

            if (isset($options['auth']) && ! $this->redis->auth($options['auth'])) {
                throw new StoreException("Redis authentication failed for {$host}:{$port}.");
            }

            foreach ($options as $option => $value) {
                if (is_int($option)) {
                    $this->redis->setOption($option, $value);
                }
            }

            // clear() walks keys with SCAN: retry empty pages instead of stopping early
            $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);

        });
    }


    public function get(string $key): ?string
    {
        $value = $this->call(function () use ($key) {
            return $this->redis->get($this->namespace.$key);
        });

        return ($value === false) ? null : (string) $value;
    }


    public function set(string $key, string $value, int $ttl): void
    {
        $stored = $this->call(function () use ($key, $value, $ttl) {
            return ($ttl > 0)
                ? $this->redis->set($this->namespace.$key, $value, $ttl)
                : $this->redis->set($this->namespace.$key, $value);
        });

        if ( ! $stored) {
            throw new StoreException("Redis could not store \"{$key}\".");
        }
    }


    public function add(string $key, string $value, int $ttl): bool
    {
        // SET NX is atomic, and answers false only when the key exists (errors throw)
        return (bool) $this->call(function () use ($key, $value, $ttl) {
            return $this->redis->set($this->namespace.$key, $value, ($ttl > 0) ? ['nx', 'ex' => $ttl] : ['nx']);
        });
    }


    public function delete(string $key): void
    {
        $this->call(function () use ($key) {
            return $this->redis->del($this->namespace.$key);
        });
    }


    public function clear(): void
    {
        // Only this install's keys: the server may hold other applications' data. Glob characters in the
        // namespace are escaped so they match literally.
        $pattern = addcslashes($this->namespace, '*?[]\\').'*';

        $this->call(function () use ($pattern) {

            $iterator = null;
            while (($keys = $this->redis->scan($iterator, $pattern, 1000)) !== false) {
                if (count($keys) > 0) {
                    $this->redis->del($keys);
                }
            }

        });
    }


    public function name(): string
    {
        return 'redis';
    }


    /**
     * Run a Redis call, turning connection errors into StoreException.
     *
     * @param  callable  $operation
     *
     * @return mixed
     * @throws StoreException
     */
    private function call(callable $operation)
    {
        try {
            return $operation();
        } catch (StoreException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new StoreException("Redis: {$e->getMessage()}", 0, $e);
        }
    }

}