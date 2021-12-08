<?php

namespace LosMiddlewareTest\RateLimit;

use LosMiddleware\RateLimit\RateLimitMiddleware;
use LosMiddleware\RateLimit\RateLimitOptions;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequest;

class HashIpsTest extends TestSetup
{
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
            'hash_ips' => true,
        ]);

        $problemResponse = $this->getMockProblemResponse();
        $storage = $this->getMockStorage();
        $this->middleware = new RateLimitMiddleware($storage, $problemResponse, $options);
    }

    public function testHashIp()
    {
        $defaultSalt = 'Los%Rate';
        $clientIp = '192.168.1.1';

        $request = new ServerRequest(['REMOTE_ADDR' => '127.0.0.1']);
        $request = $request->withHeader('X-Forwarded-For', $clientIp);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new JsonResponse([]));
        $this->middleware->process($request, $handler);

        $this->assertArrayHasKey(\md5($defaultSalt . $clientIp), $this->cache);
    }
}
