<?php

declare(strict_types=1);

namespace LosMiddleware\RateLimit;

use LogicException;
use LosMiddleware\RateLimit\Exception\MissingRequirement;
use LosMiddleware\RateLimit\Exception\ReachedRateLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use const FILTER_VALIDATE_IP;
use function array_key_exists;
use function array_map;
use function array_slice;
use function count;
use function explode;
use function filter_var;
use function is_array;
use function str_replace;
use function time;

class RateLimitMiddleware implements MiddlewareInterface
{
    public const HEADER_LIMIT     = 'X-RateLimit-Limit';
    public const HEADER_RESET     = 'X-RateLimit-Reset';
    public const HEADER_REMAINING = 'X-RateLimit-Remaining';

    /** @var CacheInterface */
    private $storage;

    /** @var RateLimitOptions */
    protected $options;

    /** @var ProblemDetailsResponseFactory */
    private $problemResponseFactory;

    public function __construct(
        CacheInterface $storage,
        ProblemDetailsResponseFactory $problemResponseFactory,
        RateLimitOptions $options
    ) {
        $this->storage                = $storage;
        $this->problemResponseFactory = $problemResponseFactory;
        $this->options                = $options;

        if ($this->options['prefer_forwarded'] && ! $this->options['trust_forwarded']) {
            throw new LogicException('You must also "trust_forwarded" headers to "prefer_forwarded" ones.');
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $keyArray = $request->getHeader($this->options['api_header']);

        if (! empty($keyArray)) {
            $key         = $keyArray[0];
            $maxRequests = $this->options['keys'][$key]['max_requests'] ?? $this->options['max_requests'];
            $resetTime   = $this->options['keys'][$key]['reset_time'] ?? $this->options['reset_time'];

            if (empty($key)) {
                throw new MissingRequirement('Missing ' . $this->options['api_header'] . ' header');
            }
        } else {
            $key         = $this->getClientIp($request);
            $maxRequests = $this->options['ips'][$key]['max_requests'] ?? $this->options['ip_max_requests'];
            $resetTime   = $this->options['ips'][$key]['reset_time'] ?? $this->options['ip_reset_time'];

            if (empty($key)) {
                throw new MissingRequirement('Could not detect the client IP');
            }

            $key = str_replace('.', '-', $key);
        }

        $data = $this->storage->get($key);

        if (empty($data) || ! is_array($data)) {
            $data = [
                'remaining' => $maxRequests,
                'created' => 0,
            ];
        }

        $remaining = array_key_exists('remaining', $data) ? (int) $data['remaining'] : $maxRequests;
        $created   = array_key_exists('created', $data) ? (int) $data['created'] : 0;
        if ($created === 0) {
            $created = time();
        } else {
            $remaining -= 1;
        }

        $resetIn = $created + $resetTime - time();

        if ($resetIn <= 0) {
            $remaining = $maxRequests - 1;
            $created   = time();
            $resetIn   = $resetTime;
        }

        if ($remaining < 0) {
            $remaining = 0;
        }

        $data['remaining'] = $remaining;
        $data['created']   = $created;

        $this->storage->set($key, $data);

        if ($remaining === 0) {
            $response = $this->problemResponseFactory->createResponseFromThrowable(
                $request,
                ReachedRateLimit::create($maxRequests)
            );
        } else {
            $response = $handler->handle($request);
            $response = $response->withHeader($this->options['headers']['remaining'], (string) $remaining);
        }

        $response = $response->withAddedHeader($this->options['headers']['limit'], (string) $maxRequests);
        $response = $response->withAddedHeader($this->options['headers']['reset'], (string) $resetIn);

        return $response;
    }

    /**
     * @return mixed|null
     */
    private function getClientIp(ServerRequestInterface $request)
    {
        $server = $request->getServerParams();
        $ips    = [];
        if (! empty($server['REMOTE_ADDR']) && $this->isIp($server['REMOTE_ADDR'])) {
            $ips[] = $server['REMOTE_ADDR'];
        }

        if ($this->options['trust_forwarded']) {
            // At this point, we either couldn't find a real IP or prefer_forwarded ones.
            foreach ($this->options['forwarded_headers_allowed'] as $name) {
                $header = $request->getHeaderLine($name);
                if (empty($header)) {
                    continue;
                }

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

        if (count($ips) > 0) {
            return $ips[0];
        }

        return null;
    }

    /**
     * @param mixed $possibleIp
     */
    private function isIp($possibleIp) : bool
    {
        return filter_var($possibleIp, FILTER_VALIDATE_IP) !== false;
    }
}
