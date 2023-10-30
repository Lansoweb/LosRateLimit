<?php

declare(strict_types=1);

namespace Los\RateLimit\Exception;

use Exception;
use Mezzio\ProblemDetails\Exception\CommonProblemDetailsExceptionTrait;
use Mezzio\ProblemDetails\Exception\ProblemDetailsExceptionInterface;

use function sprintf;

class ReachedRateLimit extends Exception implements ProblemDetailsExceptionInterface
{
    use CommonProblemDetailsExceptionTrait;

    public static function create(int $maxRequests): self
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
