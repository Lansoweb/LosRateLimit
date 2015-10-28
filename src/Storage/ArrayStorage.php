<?php
namespace LosMiddleware\Storage;

use LosMiddleware\RateLimit\Storage\StorageInterface;

class ArrayStorage implements StorageInterface
{
    private $parameters = [];

    public function read($key)
    {
        return $this->parameters[$key];
    }

    public function write($key, $value, $expire = 0)
    {
        $this->parameters[$key] = $value;
    }
}

