<?php

namespace lib\Cache;

trait ParsesCacheHost
{

    /**
     * @param  string  $host
     *
     * @return array{0:string,1:int}
     */
    protected function parseCacheHost(string $host): array
    {
        if (preg_match('/^(.*):(\d+)$/', $host, $matches)) {
            return [$matches[1], (int) $matches[2]];
        }

        return [$host, 0];
    }

}
