<?php

namespace LosMiddlewareTest\RateLimit;

use Laminas\Diactoros\Response\JsonResponse;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use LosMiddleware\RateLimit\RateLimitOptions;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class TestSetup extends TestCase
{
    /** @var array */
    protected $cache = [];

    /** @var RateLimitMiddleware */
    protected $middleware;

    protected function setUp(): void
    {
        $options = new RateLimitOptions([
            'max_requests' => 2,
            'reset_time' => 10,
            'ip_max_requests' => 2,
            'ip_reset_time' => 10,
            'api_header' => 'X-Api-Key',
            'trust_forwarded' => true,
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
        ]);

        $problemResponse = $this->getMockProblemResponse();
        $storage = $this->getMockStorage();
        $this->middleware = new RateLimitMiddleware(
            $storage,
            $problemResponse,
            $options
        );
    }

    /**
     * @param null|mixed $default
     *
     * @return null|mixed
     */
    public function getCache(string $key, $default = null)
    {
        return $this->cache[$key] ?? $default;
    }

    /**
     * @param mixed $value
     */
    public function setCache(string $key, $value): void
    {
        $this->cache[$key] = $value;
    }

    protected function getMockProblemResponse()
    {
        $problemResponse = $this->createMock(
            ProblemDetailsResponseFactory::class
        );
        $problemResponse->method('createResponseFromThrowable')->willReturn(
            new JsonResponse([], 429)
        );

        return $problemResponse;
    }

    protected function getMockStorage()
    {
        $storage = $this->createMock(CacheInterface::class);
        $storage->method('get')->will(
            $this->returnCallback([$this, 'getCache'])
        );
        $storage->method('set')->will(
            $this->returnCallback([$this, 'setCache'])
        );

        return $storage;
    }
}
