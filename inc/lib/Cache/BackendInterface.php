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


    public function delete(string $key): bool;


    /**
     * @return mixed
     */
    public function direct();


    public function mode(): string;


    public function failureReason(): ?string;

}
