<?php

declare(strict_types=1);

namespace Los\RateLimit;

use ArrayObject;
use ReturnTypeWillChange;

use function array_key_exists;

class RateLimitOptions extends ArrayObject
{
    // phpcs:ignore
    private array $defaultValues = [
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

    #[ReturnTypeWillChange]
    public function offsetGet(mixed $key): mixed
    {
        if (! $this->offsetExists($key) && array_key_exists($key, $this->defaultValues)) {
            return $this->defaultValues[$key];
        }

        return parent::offsetGet($key);
    }
}
