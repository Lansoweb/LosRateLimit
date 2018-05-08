<?php

namespace LosMiddleware\RateLimit;

use LosMiddleware\RateLimit\Storage\FileStorage;
use Psr\Container\ContainerInterface;
use Zend\ProblemDetails\ProblemDetailsResponseFactory;

class RateLimitMiddlewareFactory
{
    /**
     * @param ContainerInterface $container
     * @return RateLimitMiddleware
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');
        $rateConfig = $config['los_rate_limit'] ?? [];

        return new RateLimitMiddleware(
            new FileStorage(),
            $container->get(ProblemDetailsResponseFactory::class),
            $rateConfig
        );
    }
}
