<?php

namespace LosMiddleware\RateLimit\Storage;

use Zend\Session\Container;

class ZendSessionStorage implements StorageInterface
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     *
     * @see \LosMiddleware\RateLimit\Storage\StorageInterface::get()
     */
    public function get($key, $default = 0)
    {
        if (!$this->container->offsetExists($key)) {
            return $default;
        }
        return $this->container->offsetGet($key, $default);
    }

    /**
     * {@inheritdoc}
     *
     * @see \LosMiddleware\RateLimit\Storage\StorageInterface::set()
     */
    public function set($key, $value)
    {
        $this->container->offsetSet($key, $value);
    }
}
