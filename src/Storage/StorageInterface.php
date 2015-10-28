<?php
namespace LosRateLimit\Storage;

interface StorageInterface
{
    public function read($key);
    public function write($key, $value, $expire = 0);
}
