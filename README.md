# Rate Limit Middleware for PHP

[![Build Status](https://travis-ci.org/Lansoweb/LosRateLimit.svg?branch=master)](https://travis-ci.org/Lansoweb/LosRateLimit) [![Latest Stable Version](https://poser.pugx.org/los/los-rate-limit/v/stable.svg)](https://packagist.org/packages/los/los-rate-limit) [![Total Downloads](https://poser.pugx.org/los/los-rate-limit/downloads.svg)](https://packagist.org/packages/los/los-rate-limit) [![Coverage Status](https://coveralls.io/repos/Lansoweb/LosRateLimit/badge.svg?branch=master&service=github)](https://coveralls.io/github/Lansoweb/LosRateLimit?branch=master)

LosRateLimit is a php middleware to implement a rate limit.

First, the middleware will look for an X-Api-Key header to use as key. If not found, it will fallback to the remote IP.

Each one, has it's own limits (see configuration bellow).

Attention! This middleware does not validate the Api Key, you must add a middleware before this one to validate it.

## Requirements

* PHP >= 7.1
* Psr\HttpMessage

This middleware uses one of the pre-implemented storages: 
* Apc (default)
* Array
* Aura Session
* File
* Zend Session

But you can implement your own, like a DB storage. Just implement the StorageInterface.

## Installation

```bash
php composer.phar require los/los-rate-limit
```

### Configuration
```php
'los_rate_limit' => [
    'max_requests' => 100,
    'reset_time' => 3600,
    'ip_max_requests' => 100,
    'ip_reset_time' => 3600,
    'api_header' => 'X-Api-Key',
    'trust_forwarded' => false,
]
```

* `max_requests` How many requests are allowed before the reset time (using API Key)
* `reset_time` After how many seconds the counter will be reset (using API Key)
* `ip_max_requests` How many requests are allowed before the reset time (using remote IP Key)
* `ip_reset_time` After how many seconds the counter will be reset (using remote IP Key)
* `api_header` Header name to get the api key from.
* `trust_forwarded` If the X-Forwarded (and similar) headers and be trusted. If not, only $_SERVER['REMOTE_ADDR'] will be used.

The values above indicate that the user can trigger 100 requests per hour.

If you want to disable ip access (e.g. allowing just access via X-Api-Key), just set ip_max_requests to 0 (zero).

## Usage

Just add the middleware as one of the first in your application. 

### Zend Expressive

If you are using [expressive-skeleton](https://github.com/zendframework/zend-expressive-skeleton), 
you can copy `config/los-rate-limit.local.php.dist` to 
`config/autoload/los-rate-limit.local.php` and modify configuration as your needs.
