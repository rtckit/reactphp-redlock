<?php

declare(strict_types=1);

namespace RTCKit\React\Redlock;

use PHPUnit\Framework\TestCase;

/**
 * Lock Tests
 */
class LockTest extends TestCase
{
    public function testLock()
    {
        $token = uniqid();
        $lock = new Lock('res', 60, $token);

        $this->assertNotNull($lock);
        $this->assertInstanceOf(Lock::class, $lock);
        $this->assertIsString($lock->getResource());
        $this->assertEquals('res', $lock->getResource());
        $this->assertIsFloat($lock->getTTL());
        $this->assertEquals(60, $lock->getTTL());
        $this->assertIsString($lock->getToken());
        $this->assertEquals($token, $lock->getToken());
    }

    public function testTokenGenerator()
    {
        $this->assertIsString(Lock::generateToken());
    }
}
