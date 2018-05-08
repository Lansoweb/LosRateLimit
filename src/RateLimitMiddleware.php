<?php

namespace LosMiddleware\RateLimit;

use LosMiddleware\RateLimit\Exception\RateLimitException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LosMiddleware\RateLimit\Storage\StorageInterface;
use LosMiddleware\RateLimit\Exception\MissingParameterException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class RateLimitMiddleware implements MiddlewareInterface
{
    const HEADER_LIMIT = 'X-RateLimit-Limit';
    const HEADER_RESET = 'X-RateLimit-Reset';
    const HEADER_REMAINING = 'X-RateLimit-Remaining';

    /** @var StorageInterface */
    private $storage;

    /** @var array */
    protected $options;

    /** @var ProblemDetailsResponseFactory */
    private $problemResponseFactory;

    /**
     * Constructor.
     *
     * @param \LosMiddleware\RateLimit\Storage\StorageInterface $storage
     * @param ProblemDetailsResponseFactory $problemResponseFactory
     * @param array $config
     */
    public function __construct(
        StorageInterface $storage,
        ProblemDetailsResponseFactory $problemResponseFactory,
        $config = []
    ) {
        $this->storage = $storage;
        $this->problemResponseFactory = $problemResponseFactory;
        $this->options = array_replace([
            'max_requests' => 100,
            'reset_time' => 3600,
            'ip_max_requests' => 100,
            'ip_reset_time' => 3600,
            'api_header' => 'X-Api-Key',
            'trust_forwarded' => false,
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
            'headers' => [
                'limit' => self::HEADER_LIMIT,
                'remaining' => self::HEADER_REMAINING,
                'reset' => self::HEADER_RESET,
            ],
            'keys' => [],
            'ips' => [],
        ], $config);

        if ($this->options['prefer_forwarded'] && ! $this->options['trust_forwarded']) {
            throw new \LogicException('You must also "trust_forwarded" headers to "prefer_forwarded" ones.');
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws MissingParameterException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $keyArray = $request->getHeader($this->options['api_header']);

        if (! empty($keyArray)) {
            $key = $keyArray[0];
            $maxRequests = $this->options['keys'][$key]['max_requests'] ?? $this->options['max_requests'];
            $resetTime = $this->options['keys'][$key]['reset_time'] ?? $this->options['reset_time'];
        } else {
            $key = $this->getClientIp($request);
            $maxRequests = $this->options['ips'][$key]['max_requests'] ?? $this->options['ip_max_requests'];
            $resetTime = $this->options['ips'][$key]['reset_time'] ?? $this->options['ip_reset_time'];
        }

        if (empty($key)) {
            throw new MissingParameterException("Missing client IP and {$this->options['api_header']} header");
        }

        $data = $this->storage->get($key);

        if (empty($data) || ! is_array($data)) {
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
            $response = $this->problemResponseFactory->createResponseFromThrowable(
                $request,
                RateLimitException::create($maxRequests)
            );
            $response = $response->withAddedHeader($this->options['headers']['limit'], (string) $maxRequests);
            return $response->withAddedHeader($this->options['headers']['reset'], (string) $resetIn);
        }

        $response = $handler->handle($request);
        $response = $response->withHeader($this->options['headers']['remaining'], (string) $remaining);
        $response = $response->withAddedHeader($this->options['headers']['limit'], (string) $maxRequests);
        $response = $response->withAddedHeader($this->options['headers']['reset'], (string) $resetIn);

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @return mixed|null
     */
    private function getClientIp(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();
        $ips = [];
        if (! empty($server['REMOTE_ADDR']) && $this->isIp($server['REMOTE_ADDR'])) {
            $ips[] = $server['REMOTE_ADDR'];
        }

        if ($this->options['trust_forwarded']) {
            // At this point, we either couldn't find a real IP or prefer_forwarded ones.
            foreach ($this->options['forwarded_headers_allowed'] as $name) {
                $header = $request->getHeaderLine($name);
                if (! empty($header)) {
                    /** @var string[] $ips Possible IPs, verbatim from the forwarded header */
                    $ips = array_map('trim', explode(',', $header));

                    if ($this->options['forwarded_ip_index'] === null) {
                        // Permit any IP in this header regardless of position, as long as it's a plausible format.
                        foreach ($ips as $ip) {
                            if ($this->isIp($ip)) {
                                return $ip;
                            }
                        }
                    } else {
                        // Permit only an IP at the configured index / position.
                        $ip = array_slice($ips, (int) $this->options['forwarded_ip_index'], 1)[0];
                        if ($this->isIp($ip)) {
                            return $ip;
                        } // else there may be other permitted header keys to check
                    }
                }
            }
        }

        if (count($ips) > 0) {
            return $ips[0];
        }

        return null;
    }

    /**
     * @param mixed $possibleIp
     * @return bool
     */
    private function isIp($possibleIp)
    {
        return (filter_var($possibleIp, FILTER_VALIDATE_IP) !== false);
    }
}
