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
    'prefer_forwarded' => false,
    'forwarded_headers_allowed' => [
        'Client-Ip',
        'Forwarded',
        'Forwarded-For',
        'X-Cluster-Client-Ip',
        'X-Forwarded',
        'X-Forwarded-For',
    ],
    'forwarded_ip_index' => null,
    'headers' => [
        'limit' => 'X-RateLimit-Limit',
        'remaining' => 'X-RateLimit-Remaining',
        'reset' => 'X-RateLimit-Reset',
    ],
    'keys' => [
        'b9155515728fa0f69d9770f7877cb50a' => [
            'max_requests' => 100,
            'reset_time' => 3600,
        ],
    ],
    'ips' => [
        '127.0.0.1' => [
            'max_requests' => 100,
            'reset_time' => 3600,
        ],
    ],
]
```

* `max_requests` How many requests are allowed before the reset time (using API Key)
* `reset_time` After how many seconds the counter will be reset (using API Key)
* `ip_max_requests` How many requests are allowed before the reset time (using remote IP Key)
* `ip_reset_time` After how many seconds the counter will be reset (using remote IP Key)
* `api_header` Header name to get the api key from.
* `trust_forwarded` If the X-Forwarded (and similar) headers and be trusted. If not, only $_SERVER['REMOTE_ADDR'] will be used.
* `prefer_forwarded` Whether forwarded headers should be used in preference to the remote address, e.g. if all requests are forwarded through a routing component or reverse proxy which adds these headers predictably. This is a bad idea unless your app can **only** be reached this way.
* `forwarded_headers_allowed` An array of strings which are headers you trust to contain source IP addresses.
* `forwarded_ip_index` If null (default), the first plausible IP in an XFF header (reading left to right) is used. If numeric, only a specific index of IP is used. Use `-2` to get the penultimate IP from the list, which could make sense if the header always ends `...<client_ip>, <router_ip>`. Or use `0` to use only the first IP (stopping if it's not valid). Like `prefer_forwarded`, this only makes sense if your app's always reached through a predictable hop that controls the header - remember these are easily spoofed on the initial request.
* `keys` Specify different max_requests/reset_time per api key
* `ips` Specify different max_requests/reset_time per IP

The values above indicate that the user can trigger 100 requests per hour.

If you want to disable ip access (e.g. allowing just access via X-Api-Key), just set ip_max_requests to 0 (zero).

## Usage

Just add the middleware as one of the first in your application.

### Zend Expressive

If you are using [expressive-skeleton](https://github.com/zendframework/zend-expressive-skeleton),
you can copy `config/los-rate-limit.local.php.dist` to
`config/autoload/los-rate-limit.local.php` and modify configuration as your needs.
