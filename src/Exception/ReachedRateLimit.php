<?php

declare(strict_types=1);

namespace LosMiddleware\RateLimit\Exception;

use Exception;
use Zend\ProblemDetails\Exception\CommonProblemDetailsExceptionTrait;
use Zend\ProblemDetails\Exception\ProblemDetailsExceptionInterface;
use function sprintf;

class ReachedRateLimit extends Exception implements ProblemDetailsExceptionInterface
{
    use CommonProblemDetailsExceptionTrait;

    public static function create(int $maxRequests) : self
    {
        $message   = sprintf('You have exceeded your %d requests rate limit', $maxRequests);
        $e         = new self($message);
        $e->status = 429;
        $e->detail = $message;
        $e->type   = 'https://httpstatuses.com/429';
        $e->title  = 'Too Many Requests';

        return $e;
    }
}
