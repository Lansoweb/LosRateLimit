<?php

namespace LosMiddleware\RateLimit\Storage;

class ApcStorage implements StorageInterface
{
    private $prefix = 'los-rate-limit.';

    public function __construct($clear = false)
    {
        if ($clear) {
            apc_delete($this->prefix.'remaining');
            apc_delete($this->prefix.'created');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \LosMiddleware\RateLimit\Storage\StorageInterface::get()
     */
    public function get($key, $default = 0)
    {
        if (! apc_exists($this->prefix.$key)) {
            return $default;
        }

        return apc_fetch($this->prefix.$key);
    }

    /**
     * {@inheritdoc}
     *
     * @see \LosMiddleware\RateLimit\Storage\StorageInterface::set()
     */
    public function set($key, $value)
    {
        apc_store($this->prefix.$key, $value);
    }
}
