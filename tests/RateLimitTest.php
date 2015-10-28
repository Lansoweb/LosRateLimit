<?php

namespace LosMiddlewareTest\RateLimit;

use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use LosMiddleware\RateLimit\RateLimit;
use Zend\Session\Container;
use LosMiddleware\RateLimit\Storage\AuraSessionStorage;
use Aura\Session\SessionFactory;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\Config;
use LosMiddleware\RateLimit\RateLimitFactory;
use LosMiddleware\RateLimit\RateLimitInterface;
use LosMiddleware\RateLimit\Storage\ArrayStorage;
use LosMiddleware\RateLimit\Storage\ApcStorage;

class RateLimitTest extends \PHPUnit_Framework_TestCase
{
    protected $middleware;

    protected function setUp()
    {
        $container = new ServiceManager(new Config([]));
        $container->setService('config', [
            'los_rate_limit' => [
                'max_requests' => 2,
                'reset_time' => 10,
            ],
        ]);
        $factory = new RateLimitFactory();
        $this->middleware = $factory($container);
    }

    public function testAddHeaders()
    {
        $request = new ServerRequest();
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimitInterface::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitInterface::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitInterface::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(2, $result->getHeader(RateLimitInterface::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitInterface::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimitInterface::HEADER_LIMIT)[0]);
    }

    public function testDecreaseRemaining()
    {
        $request = new ServerRequest();
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertEquals(1, $result->getHeader(RateLimitInterface::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitInterface::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimitInterface::HEADER_LIMIT)[0]);
    }

    public function testGenerate429()
    {
        $request = new ServerRequest();
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertArrayNotHasKey(RateLimitInterface::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayNotHasKey(RateLimitInterface::HEADER_LIMIT, $result->getHeaders());
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitInterface::HEADER_RESET)[0]);
        $this->assertSame(429, $result->getStatusCode());
    }

    public function testReset()
    {
        $container = new Container('LosRateLimit');
        $container->offsetSet('remaining', 0);
        $container->offsetSet('created', strtotime('-20 second'));

        $request = new ServerRequest();
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertArrayHasKey(RateLimitInterface::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitInterface::HEADER_LIMIT, $result->getHeaders());
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitInterface::HEADER_RESET)[0]);
    }

    public function testUseAuraSession()
    {
        @session_start();

        $sessionFactory = new SessionFactory();
        $session = $sessionFactory->newInstance([]);

        $this->middleware = new RateLimit(
            new AuraSessionStorage(
                $session->getSegment('LosRateLimit')),
            [
                'max_requests' => 2,
                'reset_time' => 10,
            ]);

        $request = new ServerRequest();
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimitInterface::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitInterface::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitInterface::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(2, $result->getHeader(RateLimitInterface::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitInterface::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimitInterface::HEADER_LIMIT)[0]);
    }

    public function testUseArrayStorage()
    {
        $this->middleware = new RateLimit(
            new ArrayStorage(),
            [
                'max_requests' => 2,
                'reset_time' => 10,
            ]);

        $request = new ServerRequest();
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimitInterface::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitInterface::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitInterface::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(1, $result->getHeader(RateLimitInterface::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitInterface::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimitInterface::HEADER_LIMIT)[0]);
    }

    public function testUseApcStorage()
    {
        if (!getenv('TESTS_APC_ENABLED')) {
            $this->markTestSkipped('Enable TESTS_APC_ENABLED to run this test');
        }

        $this->middleware = new RateLimit(
            new ApcStorage(true),
            [
                'max_requests' => 2,
                'reset_time' => 10,
            ]);

        $request = new ServerRequest();
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimitInterface::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitInterface::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimitInterface::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(1, $result->getHeader(RateLimitInterface::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimitInterface::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimitInterface::HEADER_LIMIT)[0]);
    }

}
