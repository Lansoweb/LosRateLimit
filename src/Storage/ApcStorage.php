<?php

namespace LosMiddleware\RateLimit\Storage;

class ApcStorage implements StorageInterface
{
    public function __construct($clear = false)
    {
        if ($clear) {
            apc_delete('los-rate-limit.remaining');
            apc_delete('los-rate-limit.created');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \LosMiddleware\RateLimit\Storage\StorageInterface::get()
     */
    public function get($key, $default = 0)
    {
        if (!apc_exists('los-rate-limit.'.$key)) {
            return $default;
        }

        return apc_fetch('los-rate-limit.'.$key);
    }

    /**
     * {@inheritdoc}
     *
     * @see \LosMiddleware\RateLimit\Storage\StorageInterface::set()
     */
    public function set($key, $value)
    {
        apc_store('los-rate-limit.'.$key, $value);
    }
}
