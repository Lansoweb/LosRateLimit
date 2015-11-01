<?php

namespace LosMiddleware\RateLimit;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LosMiddleware\RateLimit\Storage\StorageInterface;
use LosMiddleware\RateLimit\Exception\MissingParameterException;

class RateLimit
{
    const HEADER_LIMIT = 'X-Rate-Limit-Limit';
    const HEADER_RESET = 'X-Rate-Limit-Reset';
    const HEADER_REMAINING = 'X-Rate-Limit-Remaining';

    private $storage;
    private $maxRequests = 100;
    private $resetTime = 3600;
    private $ipMaxRequests = 10;
    private $ipResetTime = 3600;
    private $apiHeader = 'X-Api-Key';
    private $trustForwarded = false;

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
            if (array_key_exists('ip_max_requests', $config)) {
                $this->ipMaxRequests = $config['ip_max_requests'];
            }
            if (array_key_exists('ip_reset_time', $config)) {
                $this->ipResetTime = $config['ip_reset_time'];
            }
            if (array_key_exists('api_header', $config)) {
                $this->apiHeader = $config['api_header'];
            }
            if (array_key_exists('trust_forwarded', $config)) {
                $this->trustForwarded = $config['trust_forwarded'];
            }
        }
    }

    private function getClientIp(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();
        $ips = [];
        if (!empty($server['REMOTE_ADDR']) && filter_var($server['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $ips[] = $server['REMOTE_ADDR'];
        }

        if ($this->trustForwarded) {
            $headers = [
                'Client-Ip',
                'Forwarded',
                'Forwarded-For',
                'X-Cluster-Client-Ip',
                'X-Forwarded',
                'X-Forwarded-For',
            ];

            foreach ($headers as $name) {
                $header = $request->getHeaderLine($name);
                if (!empty($header)) {
                    foreach (array_map('trim', explode(',', $header)) as $ip) {
                        if ((array_search($ip, $ips) === false) && filter_var($ip, FILTER_VALIDATE_IP)) {
                            $ips[] = $ip;
                        }
                    }
                }
            }
        }

        return isset($ips[0]) ? $ips[0] : null;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $keyArray = $request->getHeader($this->apiHeader);

        if (!empty($keyArray)) {
            $key = $keyArray[0];
            $maxRequests = $this->maxRequests;
            $resetTime = $this->resetTime;
        } else {
            $key = $this->getClientIp($request);
            $maxRequests = $this->ipMaxRequests;
            $resetTime = $this->ipResetTime;
        }

        if (empty($key)) {
            throw new MissingParameterException("Missing client IP and {$this->apiHeader} header");
        }

        $data = $this->storage->get($key);

        if (empty($data) || !is_array($data)) {
            $data = [
                'remaining' => $maxRequests,
                'created' => 0,
            ];
        }

        $remaining = array_key_exists('remaining', $data) ? (int) $data['remaining'] : $maxRequests;
        $created = array_key_exists('created', $data) ? (int) $data['created'] : 0;
        if ($created == 0) {
            $created = time();
        } else {
            --$remaining;
        }

        if ($remaining < 0) {
            $remaining = 0;
        }

        $resetIn = ($created + $resetTime) - time();

        // @codeCoverageIgnoreStart
        if ($resetIn <= 0) {
            $remaining = $maxRequests - 1;
            $created = time();
            $resetIn = $resetTime;
        }
        // @codeCoverageIgnoreEnd

        $data['remaining'] = $remaining;
        $data['created'] = $created;

        $this->storage->set($key, $data);

        if ($remaining <= 0) {
            $response = $response->withHeader(self::HEADER_RESET, (string) $resetIn);

            return $response->withStatus(429);
        }

        $response = $next($request, $response);
        $response = $response->withHeader(self::HEADER_REMAINING, (string) $remaining);
        $response = $response->withAddedHeader(self::HEADER_LIMIT, (string) $maxRequests);
        $response = $response->withAddedHeader(self::HEADER_RESET, (string) $resetIn);

        return $next($request, $response);
    }
}
