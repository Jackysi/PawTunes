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

/**
 * One file per key: "<expires unix time, 0 = never>\n<payload>".
 *
 * - Writes are atomic: the file is written under a temporary name and renamed over the old one, so a
 *   reader sees the old or the new entry, never half of one.
 * - The expiry lives in the file itself, so there is no shared index to read on every request.
 * - Expired files are swept now and then (see PRUNE_CHANCE), so the directory does not grow forever.
 *
 * The directory is per install (data/cache by default), so keys need no install prefix here. It also
 * holds downloaded artwork: clear() and the sweep only touch files with the cache extension.
 */
final class DiskStore implements Store
{

    /**
     * One in this many writes sweeps expired entries. A sweep reads one line per cache file, so it is
     * cheap enough to run regularly and rare enough not to matter on the hot path.
     */
    private const PRUNE_CHANCE = 100;

    /**
     * Seconds after which a leftover temporary file (a write interrupted by a crash) is removed.
     */
    private const TEMP_MAX_AGE = 3600;

    /**
     * @var string
     */
    private string $directory;

    /**
     * @var string
     */
    private string $extension;


    /**
     * @param  string  $directory
     * @param  string  $extension  File extension of cache entries, e.g. ".cache"
     *
     * @throws StoreException
     */
    public function __construct(string $directory, string $extension)
    {
        $this->directory = rtrim($directory, '/\\');
        $this->extension = $extension;

        // A fresh install or a git clone can lack the directory (an empty folder is not shipped), so it
        // is created here. Two requests may race for it: the loser's mkdir fails quietly and is_dir()
        // below still passes.
        if ( ! is_dir($this->directory)) {
            @mkdir($this->directory, 0755, true);
        }

        if ( ! is_dir($this->directory) || ! is_writable($this->directory)) {
            throw new StoreException("Disk cache directory \"{$this->directory}\" could not be created or is not writable.");
        }
    }


    public function get(string $key): ?string
    {
        $handle = @fopen($this->path($key), 'rb');
        if ($handle === false) {
            return null;
        }

        $header  = fgets($handle);
        $payload = stream_get_contents($handle);
        fclose($handle);

        if ($header === false || $payload === false || $this->isExpired((int) $header)) {
            return null;
        }

        return $payload;
    }


    public function set(string $key, string $value, int $ttl): void
    {
        $file      = $this->path($key);
        $temporary = $file.'.'.bin2hex(random_bytes(6)).'.tmp';
        $expires   = ($ttl > 0) ? time() + $ttl : 0;

        if (file_put_contents($temporary, $expires."\n".$value) === false) {
            throw new StoreException("Disk cache file \"{$temporary}\" could not be written.");
        }

        // rename() replaces the target atomically on POSIX, and on Windows PHP replaces an existing file too
        if ( ! @rename($temporary, $file)) {
            @unlink($temporary);
            throw new StoreException("Disk cache file \"{$file}\" could not be replaced.");
        }

        if (mt_rand(1, self::PRUNE_CHANCE) === 1) {
            $this->prune();
        }
    }


    public function add(string $key, string $value, int $ttl): bool
    {
        // "Check, then write" is only atomic while every add() holds the same lock. A single lock file is
        // enough: add() is used for refresh locks, a handful of calls per refresh interval.
        $lock = @fopen($this->directory.DIRECTORY_SEPARATOR.'add.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new StoreException("Disk cache lock in \"{$this->directory}\" could not be acquired.");
        }

        try {

            if ($this->get($key) !== null) {
                return false;
            }

            $this->set($key, $value, $ttl);

            return true;

        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }


    public function delete(string $key): void
    {
        $file = $this->path($key);
        if ( ! @unlink($file) && is_file($file)) {
            throw new StoreException("Disk cache file \"{$file}\" could not be deleted.");
        }
    }


    public function clear(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*'.$this->extension) ?: [] as $file) {
            @unlink($file);
        }
    }


    public function name(): string
    {
        return 'disk';
    }


    /**
     * Delete expired entries and stale temporary files.
     */
    private function prune(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*'.$this->extension) ?: [] as $file) {

            $handle = @fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }

            $header = fgets($handle, 32);
            fclose($handle);

            if ($header !== false && $this->isExpired((int) $header)) {
                @unlink($file);
            }

        }

        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*.tmp') ?: [] as $file) {
            if (@filemtime($file) < time() - self::TEMP_MAX_AGE) {
                @unlink($file);
            }
        }
    }


    /**
     * @param  int  $expires  Unix time, 0 = never
     *
     * @return bool
     */
    private function isExpired(int $expires): bool
    {
        return $expires !== 0 && $expires <= time();
    }


    /**
     * Keys become file names: anything outside [A-Za-z0-9._-] is replaced, and a hash keeps two keys that
     * differ only in those characters (or very long keys) apart.
     *
     * @param  string  $key
     *
     * @return string
     */
    private function path(string $key): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $key);
        if ($name !== $key || strlen($name) > 150) {
            $name = substr($name, 0, 100).'-'.md5($key);
        }

        return $this->directory.DIRECTORY_SEPARATOR.$name.$this->extension;
    }

}
