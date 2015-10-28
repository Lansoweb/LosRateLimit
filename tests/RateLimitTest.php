<?php
namespace LosMiddlewareTest\RateLimit;

use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use LosMiddleware\RateLimit\RateLimit;
use LosMiddleware\RateLimit\Storage\ZendSessionStorage;
use Zend\Session\Storage\ArrayStorage;
use Zend\Session\Container;
use Zend\Session\SessionManager;
use LosMiddleware\RateLimit\Storage\AuraSessionStorage;
use Aura\Session\SessionFactory;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\Config;
use LosMiddleware\RateLimit\RateLimitFactory;

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
            ]
        ]);
        $factory = new RateLimitFactory();
        $this->middleware = $factory($container);
    }

    public function testAddHeaders()
    {
        $request = new ServerRequest();
        $response = new Response();

        $outFunction = function ($request, $response)  {
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
        $response = new Response();

        $outFunction = function ($request, $response)  {
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
        $response = new Response();

        $outFunction = function ($request, $response)  {
            return $response;
        };

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
        $response = new Response();

        $outFunction = function ($request, $response)  {
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
        $response = new Response();

        $outFunction = function ($request, $response)  {
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
}
