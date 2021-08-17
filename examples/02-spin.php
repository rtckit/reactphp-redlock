<?php

declare(strict_types=1);

namespace RTCKit\React\Redlock\Examples;

error_reporting(-1);

require(__DIR__ . '/../vendor/autoload.php');

use Clue\React\Redis\Factory as RedisFactory;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use RTCKit\React\Redlock\Custodian;
use RTCKit\React\Redlock\Lock;
use Throwable;

$host = getenv('REDLOCK_EXAMPLE_HOST');

if (!$host) {
    $host = '127.0.0.1';
}

/* Instantiate prerequisites */
$factory = new RedisFactory(Loop::get());
$client = $factory->createLazyClient($host);

/* Instantiate our lock custodian */
$custodian = new Custodian($client);

/* First routine to acquire the lock */
Loop::addTimer(0.001, function () use ($custodian): void {
    /* Lock '02-spin' for 5 seconds */
    $custodian->acquire('02-spin', 5)
        ->then(function (?Lock $lock): void {
            if (is_null($lock)) {
                die("[first] Failed to aquire lock!");
            } else {
                echo "[first] Successfully acquired lock!" . PHP_EOL;
            }
        })
        ->otherwise(function (Throwable $t): void {
            echo "[first] Something bad happened:" . PHP_EOL . " > " . $t->getMessage() . PHP_EOL;
        });
});

/* Hopeful routine, attempting to eventually acquire the lock */
Loop::addTimer(1, function ($timer) use ($custodian): void {
    /* Attempt to lock '02-spin', trying up to 7 times with a one second pause between retries.
       Once secured, hold the lock for 10 seconds. */
    $custodian->spin(7, 1, '02-spin', 10)
        ->then(function (?Lock $lock): void {
            if (is_null($lock)) {
                echo "[hopeful] Failed to aquire lock!" . PHP_EOL;
            } else {
                echo "[hopeful] Successfully acquired lock!" . PHP_EOL;
            }
        })
        ->otherwise(function (Throwable $t): void {
            echo "[hopeful] Something bad happened:" . PHP_EOL . " > " . $t->getMessage() . PHP_EOL;
        })
        ->always(function(): void {
            Loop::stop();

            echo "Bye!" . PHP_EOL;
        });
});
