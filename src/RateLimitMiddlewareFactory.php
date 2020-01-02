<?php

declare(strict_types=1);

namespace LosMiddleware\RateLimit;

use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;

class RateLimitMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : RateLimitMiddleware
    {
        $config     = $container->get('config');
        $rateConfig = $config['los_rate_limit'] ?? [];

        return new RateLimitMiddleware(
            $container->get(CacheInterface::class),
            $container->get(ProblemDetailsResponseFactory::class),
            new RateLimitOptions($rateConfig)
        );
    }
}
