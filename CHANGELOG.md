# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 3.0.0 - 2019-07-15

### Added

- Support for psr/simple-cache as storage

### Changed

- Updated dependencies, specially support for zend-diactoros 1.8 and 2.0

### Deprecated

- Nothing.

### Removed

- Internal cache implementation

### Fixed

- Nothing.

## 2.1.0 - 2018-05-08

### Added

- `keys` Specify different max_requests/reset_time per api key.
- `ips` Specify different max_requests/reset_time per IP.

### Changed

- Renamed RateLimit to RateLimitMiddleware.
- Changing visibility of `options` property to allow extends

### Deprecated

- Nothing.

### Removed

- `RateLimitResponseFactory` and generating using `zend-problem-detail`.

### Fixed

- Nothing.
