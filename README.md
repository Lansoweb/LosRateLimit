# Rate Limit Middleware for PHP

[![Build Status](https://travis-ci.org/Lansoweb/LosRateLimit.svg?branch=master)](https://travis-ci.org/Lansoweb/LosRateLimit) [![Latest Stable Version](https://poser.pugx.org/los/los-rate-limit/v/stable.svg)](https://packagist.org/packages/los/los-rate-limit) [![Total Downloads](https://poser.pugx.org/los/los-rate-limit/downloads.svg)](https://packagist.org/packages/los/los-rate-limit) [![Coverage Status](https://coveralls.io/repos/Lansoweb/LosRateLimit/badge.svg)](https://coveralls.io/r/Lansoweb/LosRateLimit)

LosRateLimit is a php middleware to implement a rate limit.

## Requirements

* PHP >= 5.5
* Psr\HttpMessage

For Session, you can choose between:
* Zend Session (default)
* Aura Session

## Installation
### Using composer (recommended)

```bash
php composer.phar require los/los-rate-limit
```

### Configuration
```php
'los_rate_limit' => [
    'max_requests' => 100,
    'reset_time' => 3600,
]
```

* `max_requests` How many requests are allowed before the reset time
* `reset_time` After how many seconds the counter will be reset

The values above indicate that the user can trigger 100 requests per hour.
