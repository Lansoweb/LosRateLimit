<?php

namespace LosMiddlewareTest\RateLimit;

use LosMiddleware\RateLimit\Exception\MissingRequirement;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use LosMiddleware\RateLimit\RateLimitOptions;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\ServerRequest;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class RateLimitTest extends TestCase
{
    /** @var array */
    private $cache = [];
    /** @var RateLimitMiddleware */
    private $middleware;

    protected function setUp() : void
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

        $problemResponse = $this->createMock(ProblemDetailsResponseFactory::class);
        $problemResponse->method('createResponseFromThrowable')->willReturn(new JsonResponse([], 429));

        $storage = $this->createMock(CacheInterface::class);
        $storage->method('get')->will($this->returnCallback([$this, 'getCache']));
        $storage->method('set')->will($this->returnCallback([$this, 'setCache']));

        $this->middleware = new RateLimitMiddleware($storage, $problemResponse, $options);
    }

    public function testNeedIpOuApiKey()
    {
        $request = new ServerRequest();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse([]));

        $this->expectException(MissingRequirement::class);
        $this->middleware->process($request, $handler);
    }

    public function testAddHeadersForApiKey()
    {
        $request = new ServerRequest();
        $request = $request->withHeader('X-Api-Key', '123');
        $response = new JsonResponse([]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse([]));
        $result = $this->middleware->process($request, $handler);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimitMiddleware::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitMiddleware::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitMiddleware::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(2, $result->getHeader(RateLimitMiddleware::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitMiddleware::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimitMiddleware::HEADER_LIMIT)[0]);
    }

    public function testAddHeadersForIp()
    {
        $request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withHeader('X-Forwarded-For', '192.168.1.1');
        $response = new JsonResponse([]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse([]));
        $result = $this->middleware->process($request, $handler);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimitMiddleware::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitMiddleware::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitMiddleware::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(2, $result->getHeader(RateLimitMiddleware::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitMiddleware::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimitMiddleware::HEADER_LIMIT)[0]);
    }

    public function testDecreaseRemaining()
    {
        $request = new ServerRequest();
        $request = $request->withHeader('X-Api-Key', '123');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse([]));
        $this->middleware->process($request, $handler);
        $result = $this->middleware->process($request, $handler);

        $this->assertEquals(1, $result->getHeader(RateLimitMiddleware::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitMiddleware::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimitMiddleware::HEADER_LIMIT)[0]);
    }

    public function testGenerate429()
    {
        $request = new ServerRequest();
        $request = $request->withHeader('X-Api-Key', '123');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse([]));

        $this->middleware->process($request, $handler);
        $this->middleware->process($request, $handler);
        $this->middleware->process($request, $handler);
        $result = $this->middleware->process($request, $handler);

        $this->assertArrayNotHasKey(RateLimitMiddleware::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitMiddleware::HEADER_LIMIT, $result->getHeaders());
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitMiddleware::HEADER_RESET)[0]);
        $this->assertSame(429, $result->getStatusCode());
    }

    public function testReset()
    {
//        $container = new Container('LosRateLimit');
//        $container->offsetSet('remaining', 0);
//        $container->offsetSet('created', strtotime('-20 second'));

        $request = new ServerRequest();
        $request = $request->withHeader('X-Api-Key', '123');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse([]));
        $this->middleware->process($request, $handler);
        $this->middleware->process($request, $handler);

        $this->cache['123']['created'] = strtotime('-20 second');
        $result = $this->middleware->process($request, $handler);

        $this->assertArrayHasKey(RateLimitMiddleware::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitMiddleware::HEADER_LIMIT, $result->getHeaders());
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitMiddleware::HEADER_RESET)[0]);
        $this->assertSame(200, $result->getStatusCode());
    }

    /**
     * @param null|mixed $default
     * @return null|mixed
     */
    public function getCache(string $key, $default = null)
    {
        return $this->cache[$key] ?? $default;
    }

    /**
     * @param mixed $value
     */
    public function setCache(string $key, $value) : void
    {
        $this->cache[$key] = $value;
    }
}
