<?php

declare(strict_types=1);

namespace Los\RateLimit;

use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

class RateLimitMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): RateLimitMiddleware
    {
        $config     = $container->get('config');
        $rateConfig = $config['los']['rate-limit'] ?? $config['los_rate_limit'] ?? [];

        return new RateLimitMiddleware(
            $container->get(CacheInterface::class),
            $container->get(ProblemDetailsResponseFactory::class),
            new RateLimitOptions($rateConfig),
        );
    }
}
