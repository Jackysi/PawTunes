<?php

namespace lib\Cache;

interface BackendInterface
{

    public function isAvailable(): bool;


    /**
     * @param  string  $key
     *
     * @return mixed|false
     */
    public function get(string $key);


    /**
     * @param  string  $key
     * @param  mixed  $data
     * @param  int  $ttl
     *
     * @return bool
     */
    public function set(string $key, $data, int $ttl): bool;


    /**
     * Store the value only when the key does not exist yet. Must be atomic so that of several
     * concurrent callers exactly one gets true (used for locks). false without an exception means
     * the key already exists. The TTL must be above zero: the backend expires the key on its own.
     *
     * @param  string  $key
     * @param  mixed  $data
     * @param  int  $ttl
     *
     * @return bool
     */
    public function add(string $key, $data, int $ttl): bool;


    public function delete(string $key): bool;


    /**
     * @return mixed
     */
    public function direct();


    public function mode(): string;


    public function failureReason(): ?string;

}
