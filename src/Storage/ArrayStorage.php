<?php

namespace LosMiddleware\RateLimit\Storage;

class ArrayStorage implements StorageInterface
{
    private $container = [];

    public function __construct($container = [])
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
        if (!array_key_exists($key, $this->container)) {
            return $default;
        }

        return $this->container[$key];
    }

    /**
     * {@inheritdoc}
     *
     * @see \LosMiddleware\RateLimit\Storage\StorageInterface::set()
     */
    public function set($key, $value)
    {
        $this->container[$key] = $value;
    }
}
