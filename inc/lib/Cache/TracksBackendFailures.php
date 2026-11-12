<?php

namespace lib\Cache;

trait TracksBackendFailures
{

    /**
     * @var string|null
     */
    protected ?string $failureReason = null;


    protected function fail(string $reason): bool
    {
        $this->failureReason = $reason;

        return false;
    }


    protected function clearFailure(): void
    {
        $this->failureReason = null;
    }


    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

}
