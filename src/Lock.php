<?php

declare(strict_types=1);

namespace RTCKit\React\Redlock;

final class Lock
{
    /** @var string Redis key name */
    private $resource;

    /** @var float Lock's time to live (in seconds) */
    private $ttl;

    /** @var string Unique identifier for lock in question */
    private $token;

    /**
     * Lock constructor
     *
     * @param string $resource Redis key name
     * @param string $token Unique identifier for lock in question
     */
    public function __construct(string $resource, float $ttl, string $token)
    {
        $this->resource = $resource;
        $this->ttl = $ttl;
        $this->token = $token;
    }

    /**
     * Lock resource getter; this is the effective Redis key name.
     *
     * @return string Redis key name
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * Lock TTL getter, it refers to the value used to configure the Redis key
     * and it's not dynamic in nature.
     *
     * @return float Lock's time to live (in seconds)
     */
    public function getTTL(): float
    {
        return $this->ttl;
    }

    /**
     * Lock token getter
     *
     * @return string Unique identifier for lock in question
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Random token generator
     *
     * @return string Produced token
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
