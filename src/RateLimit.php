<?php
namespace LosMiddleware\RateLimit;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LosMiddleware\RateLimit\Storage\StorageInterface;

class RateLimit
{
    const HEADER_LIMIT     = 'X-Rate-Limit-Limit';
    const HEADER_RESET     = 'X-Rate-Limit-Reset';
    const HEADER_REMAINING = 'X-Rate-Limit-Remaining';

    private $storage;
    private $maxRequests;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        return $next($request, $response);
    }
}