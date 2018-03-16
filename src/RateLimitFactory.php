<?php

namespace LosMiddleware\RateLimit;

use LosMiddleware\RateLimit\Storage\ApcStorage;
use Psr\Container\ContainerInterface;

class RateLimitFactory
{
    /**
     * @param ContainerInterface $container
     * @return RateLimit
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');
        $rateConfig = $config['los_rate_limit'] ?? [];

        return new RateLimit(new ApcStorage(), $rateConfig);
    }
}
