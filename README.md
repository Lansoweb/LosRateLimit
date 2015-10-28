# Rate Limit Middleware for PHP

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
