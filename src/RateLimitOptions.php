<?php

declare(strict_types=1);

namespace LosMiddleware\RateLimit;

use ArrayObject;
use function array_key_exists;

class RateLimitOptions extends ArrayObject
{
    /** @var array */
    private $defaultValues = [
        'max_requests' => 100,
        'reset_time' => 3600,
        'ip_max_requests' => 100,
        'ip_reset_time' => 3600,
        'api_header' => 'X-Api-Key',
        'trust_forwarded' => false,
        'prefer_forwarded' => false,
        'forwarded_headers_allowed' => [
            'Client-Ip',
            'Forwarded',
            'Forwarded-For',
            'X-Cluster-Client-Ip',
            'X-Forwarded',
            'X-Forwarded-For',
        ],
        'forwarded_ip_index' => null,
        'headers' => [
            'limit' => RateLimitMiddleware::HEADER_LIMIT,
            'remaining' => RateLimitMiddleware::HEADER_REMAINING,
            'reset' => RateLimitMiddleware::HEADER_RESET,
        ],
        'keys' => [],
        'ips' => [],
        'hash_ips' => false,
        'hash_salt' => 'Los%Rate',
    ];

    /**
     * @param string $index
     *
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($index)
    {
        if (! $this->offsetExists($index) && array_key_exists($index, $this->defaultValues)) {
            return $this->defaultValues[$index];
        }

        return parent::offsetGet($index);
    }
}
