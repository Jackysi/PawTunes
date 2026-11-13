<?php

namespace lib\Cache;

use Memcached;
use Throwable;

class MemcachedCache implements BackendInterface
{

    use ParsesCacheHost;
    use TracksBackendFailures;

    protected array $options;

    protected ?Memcached $memcached = null;


    public function __construct(array $options)
    {
        $this->options = $options;
    }


    public function isAvailable(): bool
    {
        if ( ! extension_loaded('memcached') || ! class_exists(Memcached::class)) {
            return $this->fail('PHP extension "memcached" is not loaded.');
        }

        $this->memcached = new Memcached();

        foreach ($this->options['extra'] as $option => $value) {
            $this->memcached->setOption($option, $value);
        }

        if (count($this->memcached->getServerList()) > 0) {
            $this->clearFailure();

            return true;
        }

        [$host, $port] = $this->parseCacheHost($this->options['host']);

        try {
            if ( ! $this->memcached->addServer($host, $port)) {
                return $this->fail('Memcached server could not be added: '.$this->resultMessage());
            }

            $versions = $this->memcached->getVersion();
            foreach ($versions as $version) {
                if ($version && $version !== '255.255.255') {
                    $this->clearFailure();

                    return true;
                }
            }

            return $this->fail("Memcached server {$host}:{$port} is not reachable: ".$this->resultMessage());
        } catch (Throwable $e) {
            $this->memcached = null;

            return $this->fail("Memcached setup failed: {$e->getMessage()}");
        }
    }


    public function get(string $key)
    {
        $data = $this->memcached->get($key);
        if ($data !== false || $this->isResultNotFound()) {
            $this->clearFailure();

            return $data;
        }

        $reason = "Memcached get failed for key \"{$key}\": ".$this->resultMessage();
        $this->fail($reason);

        throw new \RuntimeException($reason);
    }


    public function set(string $key, $data, int $ttl): bool
    {
        if ($this->memcached->replace($key, $data, $ttl)) {
            $this->clearFailure();

            return true;
        }

        if ( ! $this->memcached->set($key, $data, $ttl)) {
            return $this->fail("Memcached failed to store key \"{$key}\": ".$this->resultMessage());
        }

        $this->clearFailure();

        return true;
    }


    public function add(string $key, $data, int $ttl): bool
    {
        // Memcached::add is atomic; RES_NOTSTORED means the key exists, anything else is a failure
        if ($this->memcached->add($key, $data, $ttl)) {
            $this->clearFailure();

            return true;
        }

        if (defined('Memcached::RES_NOTSTORED') && $this->memcached->getResultCode() === Memcached::RES_NOTSTORED) {
            return false;
        }

        $reason = "Memcached add failed for key \"{$key}\": ".$this->resultMessage();
        $this->fail($reason);

        throw new \RuntimeException($reason);
    }


    public function delete(string $key): bool
    {
        $deleted = (bool) $this->memcached->delete($key);
        if ($deleted || $this->isResultNotFound()) {
            $this->clearFailure();

            return $deleted;
        }

        $reason = "Memcached delete failed for key \"{$key}\": ".$this->resultMessage();
        $this->fail($reason);

        throw new \RuntimeException($reason);
    }


    public function direct()
    {
        return $this->memcached;
    }


    public function mode(): string
    {
        return 'memcached';
    }


    protected function resultMessage(): string
    {
        $message = $this->memcached ? $this->memcached->getResultMessage() : '';

        return $message ?: 'unknown error';
    }


    protected function isResultNotFound(): bool
    {
        return $this->memcached
            && defined('Memcached::RES_NOTFOUND')
            && $this->memcached->getResultCode() === Memcached::RES_NOTFOUND;
    }

}
