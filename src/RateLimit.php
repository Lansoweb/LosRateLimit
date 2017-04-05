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

    /**
     * Storage class.
     *
     * @var \LosMiddleware\RateLimit\Storage\StorageInterface
     */
    private $storage;

    /**
     * @var array
     */
    private $options;

    /**
     * Constructor.
     *
     * @param \LosMiddleware\RateLimit\Storage\StorageInterface $storage
     * @param array                                             $config
     */
    public function __construct(StorageInterface $storage, $config)
    {
        $this->storage = $storage;
        $this->options = array_replace([
            'max_requests' => 100,
            'reset_time' => 3600,
            'ip_max_requests' => 100,
            'ip_reset_time' => 3600,
            'api_header' => 'X-Api-Key',
            'trust_forwarded' => false,
            'prefer_forwarded' => false,
        ], $config);

        if ($this->options['prefer_forwarded'] && !$this->options['trust_forwarded']) {
            throw new \LogicException('You must also "trust_forwarded" headers to "prefer_forwarded" ones.');
        }
    }

    private function getClientIp(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();
        if (!empty($server['REMOTE_ADDR']) && filter_var($server['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $realIp = $server['REMOTE_ADDR'];

            if (!$this->options['prefer_forwarded']) {
                // We will never use the forwarded headers if we found a real one, except when this flag's set.
                return $realIp;
            }
        }

        if ($this->options['trust_forwarded']) {
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
                        if (filter_var($ip, FILTER_VALIDATE_IP)) {
                            // If we got this far, we always favour the first forwarded match. And either we're
                            // preferring forwarded IPs or didn't have a real one.
                            return $ip;
                        }
                    }
                }
            }
        }

        if (isset($realIp)) {
            // We waited in order to 'prefer_forwarded', but only a direct IP was set.
            return $realIp;
        }

        return null;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        $keyArray = $request->getHeader($this->options['api_header']);

        if (!empty($keyArray)) {
            $key = $keyArray[0];
            $maxRequests = $this->options['max_requests'];
            $resetTime = $this->options['reset_time'];
        } else {
            $key = $this->getClientIp($request);
            $maxRequests = $this->options['ip_max_requests'];
            $resetTime = $this->options['ip_reset_time'];
        }

        if (empty($key)) {
            throw new MissingParameterException("Missing client IP and {$this->options['api_header']} header");
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

        return $response;
    }
}
