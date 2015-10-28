<?php

namespace LosMiddleware\RateLimit;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LosMiddleware\RateLimit\Storage\StorageInterface;

class RateLimit implements RateLimitInterface
{

    private $storage;
    private $maxRequests = 100;
    private $resetTime = 3600;

    public function __construct(StorageInterface $storage, $config)
    {
        $this->storage = $storage;
        if (!empty($config)) {
            if (array_key_exists('max_requests', $config)) {
                $this->maxRequests = $config['max_requests'];
            }
            if (array_key_exists('reset_time', $config)) {
                $this->resetTime = $config['reset_time'];
            }
        }
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $remaining = $this->storage->get('remaining', $this->maxRequests);
        $created = $this->storage->get('created');

        if ($created == 0) {
            $created = time();
        } else {
            //Keeps phpunit happy ...
            $remaining = $remaining - 1;
        }

        $resetIn = ($created + $this->resetTime) - time();

        if ($resetIn <= 0) {
            $remaining = $this->maxRequests - 1;
            $created = time();
            $resetIn = $this->resetTime;
        }

        if ($remaining <= 0) {
            $response = $response->withHeader(RateLimitInterface::HEADER_RESET, (string) $resetIn);

            return $response->withStatus(429);
        }

        $this->storage->set('remaining', $remaining);
        $this->storage->set('created', $created);

        $response = $next($request, $response);
        $response = $response->withHeader(RateLimitInterface::HEADER_REMAINING, (string) $remaining);
        $response = $response->withAddedHeader(RateLimitInterface::HEADER_LIMIT, (string) $this->maxRequests);
        $response = $response->withAddedHeader(RateLimitInterface::HEADER_RESET, (string) $resetIn);

        return $next($request, $response);
    }
}
