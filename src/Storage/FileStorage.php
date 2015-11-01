<?php

namespace LosMiddleware\RateLimit\Storage;

class FileStorage implements StorageInterface
{
    private $fileName;

    public function __construct($fileName = 'data/los-rate-limit.db', $clear = false)
    {
        if ($clear && file_exists($fileName)) {
            unlink($fileName);
        }

        $this->fileName = $fileName;
    }

    private function getFile()
    {
        if (!file_exists($this->fileName)) {
            file_put_contents($this->fileName, '[]');
        }

        return json_decode(file_get_contents($this->fileName), true);
    }

    /**
     * {@inheritdoc}
     *
     * @see \LosMiddleware\RateLimit\Storage\StorageInterface::get()
     */
    public function get($key, $default = 0)
    {
        $file = $this->getFile();
        foreach ($file as $localKey => $value) {
            if ($localKey == $key) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     *
     * @see \LosMiddleware\RateLimit\Storage\StorageInterface::set()
     */
    public function set($key, $value)
    {
        $file = $this->getFile();
        foreach ($file as $localKey => $localValue) {
            if ($localKey == $key) {
                $file[$localKey] = $value;
                file_put_contents($this->fileName, json_encode($file));

                return;
            }
        }
        $file[$key] = $value;
        file_put_contents($this->fileName, json_encode($file));
    }
}
