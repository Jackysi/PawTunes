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

        $file = $this->filePath($key);
        if (file_put_contents($file, $this->encode($data)) === false) {
            return $this->fail("Disk cache file \"{$file}\" could not be written.");
        }

        $this->clearFailure();

        return true;
    }


    /**
     * Atomic create: fopen() with the "x" flag fails when the file exists, so only one caller wins.
     * Disk files carry no TTL of their own, so a leftover entry older than the TTL (a request that
     * died while holding a lock) counts as free and may be reclaimed.
     *
     * The reclaim happens only after the create fails, by re-checking the mtime of the file that is
     * actually there, then retrying the atomic create. Unlinking up front (on the mtime seen before the
     * create) could delete a fresh lock another request created in between.
     */
    public function add(string $key, $data, int $ttl): bool
    {
        if ( ! $this->isAvailable()) {
            return false;
        }

        $file   = $this->filePath($key);
        $handle = @fopen($file, 'x');

        if ($handle === false) {

            // The file exists. Reclaim it only if it is still an expired leftover, then retry the create;
            // 'x' means the retry still loses to any request that recreated the file in the meantime.
            clearstatcache(true, $file);
            if ($ttl <= 0 || ! is_file($file) || filemtime($file) + $ttl > time()) {
                return false;
            }

            @unlink($file);
            $handle = @fopen($file, 'x');
            if ($handle === false) {
                return false;
            }

        }

        fwrite($handle, $this->encode($data));
        fclose($handle);
        $this->clearFailure();

        return true;
    }


    /**
     * @param  mixed  $data
     *
     * @return string
     */
    protected function encode($data): string
    {
        if ( ! is_string($data)) {
            $data = serialize($data);
        }

        return $this->encrypt ? base64_encode($data) : $data;
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
