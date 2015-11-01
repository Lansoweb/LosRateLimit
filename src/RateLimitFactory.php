<?php

namespace LosMiddleware\RateLimit;

use Interop\Container\ContainerInterface;
use LosMiddleware\RateLimit\Storage\ApcStorage;

class RateLimitFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');
        $rateConfig = array_key_exists('los_rate_limit', $config) ? $config['los_rate_limit'] : [];

        return new RateLimit(new ApcStorage(), $rateConfig);
    }
}
