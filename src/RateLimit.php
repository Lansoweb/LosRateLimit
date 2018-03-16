<?php

namespace LosMiddleware\RateLimit;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use LosMiddleware\RateLimit\Storage\StorageInterface;
use LosMiddleware\RateLimit\Exception\MissingParameterException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class RateLimit implements MiddlewareInterface
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

    /** @var ProblemDetailsResponseFactory */
    private $problemDetailsResponseFactory;

    /**
     * Constructor.
     *
     * @param \LosMiddleware\RateLimit\Storage\StorageInterface $storage
     * @param ProblemDetailsResponseFactory $problemDetailsResponseFactory
     * @param array $config
     */
    public function __construct(
        StorageInterface $storage,
        ProblemDetailsResponseFactory $problemDetailsResponseFactory,
        $config = []
    ) {
        $this->storage = $storage;
        $this->problemDetailsResponseFactory = $problemDetailsResponseFactory;
        $this->options = array_replace([
            'max_requests' => 100,
            'reset_time' => 3600,
            'ip_max_requests' => 100,
            'ip_reset_time' => 3600,
            'api_header' => 'X-Api-Key',
            'trust_forwarded' => false,
        ], $config);
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
            $response = (new RateLimitResponseFactory(function () : ResponseInterface {
                return new Response();
            }))->create($request, (int) $maxRequests, $resetIn);

            return $response;
        }

        $response = $handler->handle($request);
        $response = $response->withHeader(self::HEADER_REMAINING, (string) $remaining);
        $response = $response->withAddedHeader(self::HEADER_LIMIT, (string) $maxRequests);
        $response = $response->withAddedHeader(self::HEADER_RESET, (string) $resetIn);

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
        if (! empty($server['REMOTE_ADDR']) && filter_var($server['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $ips[] = $server['REMOTE_ADDR'];
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
                if (! empty($header)) {
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
}
