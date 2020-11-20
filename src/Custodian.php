<?php

declare(strict_types=1);

namespace RTCKit\React\Redlock;

use Clue\React\Redis\Client;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function bin2hex;
use function random_bytes;
use function React\Promise\resolve;

final class Custodian
{
    /** @var string Lock release Lua script */
    public const RELEASE_SCRIPT = <<<EOD
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
EOD;

    /** @var LoopInterface Event loop we're bound to */
    private $loop;

    /** @var Client ReactPHP Redis client */
    private $client;

    /**
     * Custodian constructor
     *
     * @param LoopInterface $loop Event loop we're bound to
     * @param Client $client ReactPHP Redis client
     */
    public function __construct(LoopInterface $loop, Client $client)
    {
        $this->loop = $loop;
        $this->client = $client;
    }

    /**
     * Attemps to acquire a lock; the returned promise resolves either to
     * a Lock object on success or NULL on failure.
     *
     * @param string $resource Redis key name
     * @param float $ttl Lock's time to live (in seconds)
     * @param ?string $token Unique identifier for lock in question
     *
     * @return PromiseInterface
     */
    public function acquire(string $resource, float $ttl, ?string $token = null): PromiseInterface
    {
        if (is_null($token)) {
            $token = Lock::generateToken();
        }

        /** @psalm-suppress InvalidScalarArgument */
        return $this->client->set($resource, $token, 'NX', 'PX', (int) round($ttl * 1000))
            ->then(function (?string $reply) use ($resource, $ttl, $token): ?Lock {
                if (is_null($reply) || ($reply !== 'OK')) {
                    return null;
                }

                return new Lock($resource, $ttl, $token);
            });
    }

    /**
     * Repeatedly attemps to acquire a lock.
     *
     * @param int $attempts Maximum spin/tries
     * @param float $interval Spin/try interval (in seconds)
     * @param string $resource Redis key name
     * @param float $ttl Lock's time to live (in seconds)
     * @param ?string $token Unique identifier for lock in question
     *
     * @return PromiseInterface
     */
    public function spin(int $attempts, float $interval, string $resource, float $ttl, ?string $token = null) {
        if (!$attempts) {
            return resolve(null);
        }

        $deferred = new Deferred();

        $this->acquire($resource, $ttl, $token)
            ->then(function (?Lock $lock) use ($deferred, $attempts, $interval, $resource, $ttl, $token) {
                if (!is_null($lock)) {
                    $deferred->resolve($lock);
                } else {
                    $this->loop->addTimer($interval, function () use ($deferred, $attempts, $interval, $resource, $ttl, $token) {
                        $deferred->resolve($this->spin(--$attempts, $interval, $resource, $ttl, $token));
                    });
                }
            });

        return $deferred->promise();
    }

    /**
     * Attemps to release a lock; the returned promise resolves to a
     * boolean status value.
     *
     * @param Lock $lock Lock object to be released
     *
     * @return PromiseInterface
     */
    public function release(Lock $lock): PromiseInterface
    {
        /** @psalm-suppress InvalidScalarArgument */
        return $this->client->eval(self::RELEASE_SCRIPT, 1, $lock->getResource(), $lock->getToken())
            ->then(function (?string $reply): bool {
                return $reply === '1';
            });
    }
}
