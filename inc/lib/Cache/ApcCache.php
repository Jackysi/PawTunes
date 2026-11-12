<?php

namespace lib\Cache;

class ApcCache implements BackendInterface
{

    use TracksBackendFailures;

    protected string $mode;


    public function __construct(string $mode)
    {
        $this->mode = $mode;
    }


    public function isAvailable(): bool
    {
        if ( ! extension_loaded($this->mode)) {
            return $this->fail("PHP extension \"{$this->mode}\" is not loaded.");
        }

        if ( ! function_exists($this->mode.'_fetch')) {
            return $this->fail("PHP extension \"{$this->mode}\" does not expose cache functions.");
        }

        if ( ! (bool) ini_get('apc.enabled')) {
            return $this->fail('APC/APCu is installed but disabled by php.ini setting "apc.enabled".');
        }

        $this->clearFailure();

        return true;
    }


    public function get(string $key)
    {
        $success = false;
        $fetch   = $this->mode.'_fetch';
        $data    = $fetch($key, $success);

        if ($success) {
            $this->clearFailure();

            return $data;
        }

        return false;
    }


    public function set(string $key, $data, int $ttl): bool
    {
        $store = $this->mode.'_store';

        if ( ! $store($key, $data, $ttl)) {
            return $this->fail("{$this->mode} failed to store key \"{$key}\".");
        }

        $this->clearFailure();

        return true;
    }


    public function delete(string $key): bool
    {
        $delete = $this->mode.'_delete';

        if ( ! $delete($key)) {
            return false;
        }

        $this->clearFailure();

        return true;
    }


    public function direct()
    {
        return null;
    }


    public function mode(): string
    {
        return $this->mode;
    }

}
