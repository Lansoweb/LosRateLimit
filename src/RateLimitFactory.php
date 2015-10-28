<?php
namespace LosMiddleware\RateLimit;

use Zend\Session\Container;
use LosMiddleware\RateLimit\Storage\ZendSessionStorage;

class RateLimitFactory
{
    public function __invoke($container)
    {
        $config = $container->get('config');
        $rateConfig = array_key_exists('los_rate_limit', $config) ? $config['los_rate_limit'] : [];
        return new RateLimit(new ZendSessionStorage(new Container('LosRateLimit')), $rateConfig);
    }
}