<?php

namespace lib\Cache;

use Throwable;

class DiskCache implements BackendInterface
{

    use TracksBackendFailures;

    protected string $path;

    protected string $extension;

    protected bool $encrypt;


    public function __construct(array $options)
    {
        $this->path      = $options['path'] ?: realpath(getcwd()).DIRECTORY_SEPARATOR.'cache';
        $this->extension = $options['ext'] ?? '.cache';
        $this->encrypt   = ($options['encrypt'] ?? false) === true;
    }


    public function isAvailable(): bool
    {
        if ( ! is_dir($this->path) && ! mkdir($this->path, 0755, true) && ! is_dir($this->path)) {
            return $this->fail("Disk cache directory \"{$this->path}\" could not be created.");
        }

        if ( ! is_writable($this->path)) {
            return $this->fail("Disk cache directory \"{$this->path}\" is not writable.");
        }

        $this->clearFailure();

        return true;
    }


    public function get(string $key)
    {
        $file = $this->filePath($key);
        if ( ! is_file($file)) {
            return false;
        }

        $data = file_get_contents($file);
        if ($data === false) {
            $this->fail("Disk cache file \"{$file}\" could not be read.");

            return false;
        }

        if ($this->encrypt) {
            $decoded = base64_decode($data, true);
            $data    = ($decoded === false) ? $data : $decoded;
        }

        try {
            $serialized = @unserialize($data, ['allowed_classes' => false]);
        } catch (Throwable $e) {
            $serialized = false;
        }

        if ($serialized !== false || $data === 'b:0;') {
            return $serialized;
        }

        return $data;
    }


    public function set(string $key, $data, int $ttl): bool
    {
        if ( ! $this->isAvailable()) {
            return false;
        }

        if ( ! is_string($data)) {
            $data = serialize($data);
        }

        if ($this->encrypt) {
            $data = base64_encode($data);
        }

        $file = $this->filePath($key);
        if (file_put_contents($file, $data) === false) {
            return $this->fail("Disk cache file \"{$file}\" could not be written.");
        }

        $this->clearFailure();

        return true;
    }


    public function delete(string $key): bool
    {
        $file = $this->filePath($key);
        if ( ! is_file($file)) {
            return false;
        }

        if ( ! unlink($file)) {
            return $this->fail("Disk cache file \"{$file}\" could not be deleted.");
        }

        $this->clearFailure();

        return true;
    }


    public function direct()
    {
        return $this;
    }


    public function mode(): string
    {
        return 'disk';
    }


    public function filePath(string $key): string
    {
        return rtrim($this->path, '/\\').DIRECTORY_SEPARATOR.$key.$this->extension;
    }

}
