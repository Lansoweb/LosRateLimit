<?php
namespace LosMiddleware\RateLimit\Storage;

interface StorageInterface
{
    public function get($key, $default = 0);
    public function set($key, $value);
}