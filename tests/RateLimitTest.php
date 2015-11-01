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
use LosMiddleware\RateLimit\Storage\ArrayStorage;
use LosMiddleware\RateLimit\Storage\ApcStorage;
use LosMiddleware\RateLimit\Exception\MissingParameterException;
use LosMiddleware\RateLimit\Storage\ZendSessionStorage;

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
                'ip_max_requests' => 2,
                'ip_reset_time' => 10,
                'api_header' => 'X-Api-Key',
                'trust_forwarded' => true,
            ],
        ]);
        //$factory = new RateLimitFactory();
        //$this->middleware = $factory($container);
        $config = $container->get('config');
        $rateConfig = array_key_exists('los_rate_limit', $config) ? $config['los_rate_limit'] : [];
        $this->middleware = new RateLimit(new ArrayStorage(), $rateConfig);
    }

    public function testFactory()
    {
        $factory = new RateLimitFactory();
        $container = new ServiceManager(new Config([]));
        $container->setService('config', []);
        $this->assertInstanceOf(RateLimit::class, $factory($container));
    }

    public function testNeedIpOuApiKey()
    {
        $request = new ServerRequest();
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $this->setExpectedException(MissingParameterException::class);
        call_user_func($this->middleware, $request, $response, $outFunction);
    }

    public function testAddHeadersForApiKey()
    {
        $request = new ServerRequest();
        $request = $request->withHeader('X-Api-Key', '123');
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimit::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimit::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_LIMIT)[0]);
    }

    public function testAddHeadersForIp()
    {
        $request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withHeader('X-Forwarded-For', '192.168.1.1');
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimit::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimit::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_LIMIT)[0]);
    }

    public function testDecreaseRemaining()
    {
        $request = new ServerRequest();
        $request = $request->withHeader('X-Api-Key', '123');
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertEquals(1, $result->getHeader(RateLimit::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimit::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_LIMIT)[0]);
    }

    public function testGenerate429()
    {
        $request = new ServerRequest();
        $request = $request->withHeader('X-Api-Key', '123');
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertArrayNotHasKey(RateLimit::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayNotHasKey(RateLimit::HEADER_LIMIT, $result->getHeaders());
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimit::HEADER_RESET)[0]);
        $this->assertSame(429, $result->getStatusCode());
    }

    public function testReset()
    {
        $container = new Container('LosRateLimit');
        $container->offsetSet('remaining', 0);
        $container->offsetSet('created', strtotime('-20 second'));

        $request = new ServerRequest();
        $request = $request->withHeader('X-Api-Key', '123');
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertArrayHasKey(RateLimit::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_LIMIT, $result->getHeaders());
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimit::HEADER_RESET)[0]);
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
        $request = $request->withHeader('X-Api-Key', '123');
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimit::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimit::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_LIMIT)[0]);
    }

    public function testUseZendSession()
    {
        @session_start();

        $this->middleware = new RateLimit(
            new ZendSessionStorage(new Container('LosRateLimit')),
            [
                'max_requests' => 2,
                'reset_time' => 10,
            ]);

        $request = new ServerRequest();
        $request = $request->withHeader('X-Api-Key', '123');
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimit::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(1, $result->getHeader(RateLimit::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimit::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_LIMIT)[0]);
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
        $request = $request->withHeader('X-Api-Key', '123');
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimit::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(1, $result->getHeader(RateLimit::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimit::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_LIMIT)[0]);
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
        $request = $request->withHeader('X-Api-Key', '123');
        $response = new Response();

        $outFunction = function ($request, $response) {
            return $response;
        };

        $result = call_user_func($this->middleware, $request, $response, $outFunction);
        $result = call_user_func($this->middleware, $request, $response, $outFunction);

        $this->assertNotSame($response, $result);
        $this->assertArrayHasKey(RateLimit::HEADER_REMAINING, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_LIMIT, $result->getHeaders());
        $this->assertArrayHasKey(RateLimit::HEADER_RESET, $result->getHeaders());

        $this->assertEquals(1, $result->getHeader(RateLimit::HEADER_REMAINING)[0]);
        $this->assertLessThanOrEqual(10, $result->getHeader(RateLimit::HEADER_RESET)[0]);
        $this->assertEquals(2, $result->getHeader(RateLimit::HEADER_LIMIT)[0]);
    }
}
