<?php

declare(strict_types=1);

namespace RTCKit\React\Redlock\Examples;

error_reporting(-1);

require(__DIR__ . '/../vendor/autoload.php');

use Clue\React\Redis\Factory as RedisFactory;
use React\EventLoop\Factory as LoopFactory;
use React\Promise\PromiseInterface;
use RTCKit\React\Redlock\Custodian;
use RTCKit\React\Redlock\Lock;
use Throwable;
use function React\Promise\resolve;

$host = getenv('REDLOCK_EXAMPLE_HOST');

if (!$host) {
    $host = '127.0.0.1';
}

/* Instantiate prerequisites */
$loop = LoopFactory::create();
$factory = new RedisFactory($loop);
$client = $factory->createLazyClient($host);

/* Instantiate our lock custodian */
$custodian = new Custodian($loop, $client);

$loop->addTimer(0.001, function () use ($custodian, $loop): void {
    $custodian->acquire('01-basic', 1)
        ->then(function (?Lock $lock) use ($custodian): PromiseInterface {
            if (is_null($lock)) {
                echo "Failed to acquire lock!" . PHP_EOL;

                return resolve(false);
            } else {
                echo "Successfully acquired lock!" . PHP_EOL;
                echo "> Resource: " . $lock->getResource() . PHP_EOL;
                echo "> TTL: " . $lock->getTTL() . PHP_EOL;
                echo "> Token: " . $lock->getToken() . PHP_EOL;

                echo "About to release lock ..." . PHP_EOL;

                return $custodian->release($lock);
            }
        })
        ->then(function (bool $success): void {
            if ($success) {
                echo "Successfully released lock!" . PHP_EOL;
            } else {
                echo "Failed to release lock!" . PHP_EOL;
            }
        })
        ->otherwise(function (Throwable $t): void {
            echo "Something bad happened:" . PHP_EOL . " > " . $t->getMessage() . PHP_EOL;
        })
        ->always(function() use ($loop): void {
            $loop->stop();

            echo "Bye!" . PHP_EOL;
        });
});

$loop->run();
