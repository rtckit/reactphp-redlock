# Distributed locks with Redis and ReactPHP

Asynchronous [Redlock](https://redis.io/topics/distlock) algorithm implementation for PHP

[![Build Status](https://travis-ci.com/rtckit/reactphp-redlock.svg?branch=main)](https://travis-ci.com/rtckit/reactphp-redlock)
[![Latest Stable Version](https://poser.pugx.org/rtckit/react-redlock/v/stable.png)](https://packagist.org/packages/rtckit/rtckit/react-redlock)
[![Test Coverage](https://api.codeclimate.com/v1/badges/aff5ee8e8ef3b51689c2/test_coverage)](https://codeclimate.com/github/rtckit/reactphp-redlock/test_coverage)
[![Maintainability](https://api.codeclimate.com/v1/badges/aff5ee8e8ef3b51689c2/maintainability)](https://codeclimate.com/github/rtckit/reactphp-redlock/maintainability)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

## Quickstart

Once [installed](#installation), you can incorporate Redlock in your projects by instantiating its _Custodian_; this entity is responsible for lock orchestration and it requires access to your process's event loop as well as to a Redis client object instance, e.g.

```php
/* Instantiate prerequisites */
$loop = \React\EventLoop\Factory::create();
$factory = new \Clue\React\Redis\Factory($loop);
$client = $factory->createLazyClient('127.0.0.1');

/* Instantiate our lock custodian */
$custodian = new \RTCKit\React\Redlock\Custodian($loop, $client);
```

#### Acquiring locks

For use cases where a binary outcome is desirable, the `acquire()` method works best, e.g.:

```php
/**
 * @param string $resource Redis key name
 * @param float $ttl Lock's time to live (in seconds)
 * @param ?string $token Unique identifier for lock in question
 * @return PromiseInterface
 */
$custodian->acquire('MyResource', 60, 'r4nd0m_token')
    ->then(function (?Lock $lock) {
        if (is_null($lock)) {
            // Ooops, lock could not be acquired for MyResource
        } else {
            // Awesome, MyResource is locked for a minute
            // ...

            // Be nice and release the lock when done
            $custodian->release($lock);
        }
    });
```

#### Spinlocks

The `spin()` method is designed for situations where a process should keep trying acquiring a lock, e.g.

```php
/**
 * @param int $attempts Maximum spin/tries
 * @param float $interval Spin/try interval (in seconds)
 * @param string $resource Redis key name
 * @param float $ttl Lock's time to live (in seconds)
 * @param ?string $token Unique identifier for lock in question
 * @return PromiseInterface
 */
$custodian->spin(100, 0.5, 'HotResource', 10, 'r4nd0m_token')
    ->then(function (?Lock $lock): void {
        if (is_null($lock)) {
            // Wow, after 100 tries (with a gap of 0.5 seconds) I've
            // given up acquiring a lock on HotResource
        } else {
            // Awesome, HotResource is locked for 10 seconds
            // ...

            // Again, be nice and release the lock when done
            $custodian->release($lock);
        }
    })
```

Lastly, the provided [examples](examples) are a good starting point.

## Requirements

Redlock is compatible with PHP 7.1+ and has no external library and extension dependencies.

## Installation

You can add the library as project dependency using [Composer](https://getcomposer.org/):

```sh
composer require rtckit/react-redlock
```

If you only need the library during development, for instance when used in your test suite, then you should add it as a development-only dependency:

```sh
composer require --dev rtckit/react-redlock
```

## Tests

To run the test suite, clone this repository and then install dependencies via Composer:

```sh
composer install
```

Then, go to the project root and run:

```bash
php -d memory_limit=-1 ./vendor/bin/phpunit
```

### Static Analysis

In order to ensure high code quality, Redlock uses [PHPStan](https://github.com/phpstan/phpstan) and [Psalm](https://github.com/vimeo/psalm):

```sh
php -d memory_limit=-1 ./vendor/bin/phpstan analyse -n -vvv --ansi --level=max src
php -d memory_limit=-1 ./vendor/bin/psalm --show-info=true
```

## License

MIT, see [LICENSE file](LICENSE).

### Acknowledgments

* [antirez](http://antirez.com/news/77) - Original blog post
* [ReactPHP Project](https://reactphp.org/)
* [clue/reactphp-redis](https://github.com/clue/reactphp-redis) - Async Redis client implementation

### Contributing

Bug reports (and small patches) can be submitted via the [issue tracker](https://github.com/rtckit/reactphp-redlock/issues). Forking the repository and submitting a Pull Request is preferred for substantial patches.
