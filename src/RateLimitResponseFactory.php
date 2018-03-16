<?php
namespace LosMiddleware\RateLimit;

use Psr\Http\Message\ServerRequestInterface;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class RateLimitResponseFactory extends ProblemDetailsResponseFactory
{
    const STATUS = 429;
    const TITLE = 'https://httpstatuses.com/429';
    const TYPE = 'You have exceeded the rate limit.';

    public function create(
        ServerRequestInterface $request,
        $maxRequests,
        $resetIn
    ) {
        $response = self::createResponse(
            $request,
            self::STATUS,
            sprintf('You have exceeded your %d requests rate limit', $maxRequests),
            self::TITLE,
            self::TYPE,
            [
                'rate_limit' => $maxRequests,
                'rate_limit_reset' => date('c', $resetIn),
            ]
        );
        return $response->withHeader(RateLimit::HEADER_RESET, (string) $resetIn);
    }
}
