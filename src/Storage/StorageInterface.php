<?php
namespace LosMiddleware\RateLimit\Storage;

interface StorageInterface
{
    public function read($key);
    public function write($key, $value, $expire = 0);
}
