<?php

namespace LosMiddlewareTest\RateLimit;

use LosMiddleware\RateLimit\Exception\MissingRequirement;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use LosMiddleware\RateLimit\RateLimitOptions;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;

class RateLimitTest extends TestSetup
{
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

    public function testStoresUnhashedIps()
    {
        $clientIp = '192.168.1.1';
        $expectedCacheKey = '192-168-1-1';

        $request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withHeader('X-Forwarded-For', $clientIp);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse([]));
        $this->middleware->process($request, $handler);

        $this->assertArrayHasKey($expectedCacheKey, $this->cache);
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
}
