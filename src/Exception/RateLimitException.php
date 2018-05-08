<?php

namespace LosMiddleware\RateLimit\Exception;

use Exception;
use Zend\ProblemDetails\Exception\CommonProblemDetailsExceptionTrait;
use Zend\ProblemDetails\Exception\ProblemDetailsExceptionInterface;

class RateLimitException extends Exception implements ProblemDetailsExceptionInterface
{
    use CommonProblemDetailsExceptionTrait;

    public static function create($maxRequests)
    {
        $message = sprintf('You have exceeded your %d requests rate limit', $maxRequests);
        $e = new self($message);
        $e->status = 429;
        $e->detail = $message;
        $e->type = 'https://httpstatuses.com/429';
        $e->title = 'Too Many Requests';
        return $e;
    }
}
