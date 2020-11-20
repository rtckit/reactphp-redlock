<?php

declare(strict_types=1);

namespace RTCKit\React\Redlock;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

/**
 * Custodian Tests
 */
class CustodianTest extends TestCase
{
    private $loop;
    private $client;

    /**
     * @before
     */
    public function setUpFactory()
    {
        $this->loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $this->client = $this->getMockBuilder('Clue\React\Redis\Client')->getMock();
    }

    public function testConstructor()
    {
        $custodian = new Custodian($this->loop, $this->client);

        $this->assertNotNull($custodian);
        $this->assertInstanceOf(Custodian::class, $custodian);
    }

    public function testSuccessfulAcquire()
    {
        $this->client->expects($this->once())
            ->method('__call')
            ->with('set', ['resource', 'r4nd0m', 'NX', 'PX', 60000])
            ->willReturn(resolve('OK'));

        $custodian = new Custodian($this->loop, $this->client);

        $promise = $custodian->acquire('resource', 60, 'r4nd0m');

        $this->assertNotNull($promise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(function (?Lock $lock) {
            $this->assertNotNull($lock);
            $this->assertInstanceOf(Lock::class, $lock);
            $this->assertEquals('resource', $lock->getResource());
            $this->assertEquals(60, $lock->getTTL());
            $this->assertEquals('r4nd0m', $lock->getToken());
        });
    }

    public function testFailedAcquire()
    {
        $this->client->expects($this->once())
            ->method('__call')
            ->with('set', ['2fail', 'r4nd0m', 'NX', 'PX', 60000])
            ->willReturn(resolve(null));

        $custodian = new Custodian($this->loop, $this->client);

        $promise = $custodian->acquire('2fail', 60, 'r4nd0m');

        $this->assertNotNull($promise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(function (?Lock $lock) {
            $this->assertNull($lock);
        });
    }

    public function testAcquireOptionalToken()
    {
        $this->client->expects($this->once())
            ->method('__call')
            ->willReturn(resolve('OK'));

        $custodian = new Custodian($this->loop, $this->client);

        $promise = $custodian->acquire('resource', 60);

        $this->assertNotNull($promise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(function (?Lock $lock) {
            $this->assertNotNull($lock);
            $this->assertInstanceOf(Lock::class, $lock);
            $this->assertEquals('resource', $lock->getResource());
            $this->assertEquals(60, $lock->getTTL());
            $this->assertIsString($lock->getToken());
        });
    }

    public function testSuccessfulSpin()
    {
        $loop = Factory::create();

        $this->client->expects($this->exactly(5))
            ->method('__call')
            ->with('set', ['resource', 'r4nd0m', 'NX', 'PX', 60000])
            ->willReturnOnConsecutiveCalls(
                resolve(null),
                resolve(null),
                resolve(null),
                resolve(null),
                resolve('OK')
            );

        $custodian = new Custodian($loop, $this->client);

        $promise = $custodian->spin(5, 0.000001, 'resource', 60, 'r4nd0m');

        $this->assertNotNull($promise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(function (?Lock $lock) use ($loop) {
            $this->assertNotNull($lock);
            $this->assertInstanceOf(Lock::class, $lock);
            $this->assertEquals('resource', $lock->getResource());
            $this->assertEquals(60, $lock->getTTL());
            $this->assertEquals('r4nd0m', $lock->getToken());

            $loop->stop();
        });

        $loop->run();
    }

    public function testFailedSpin()
    {
        $loop = Factory::create();

        $this->client->expects($this->exactly(5))
            ->method('__call')
            ->with('set', ['resource', 'r4nd0m', 'NX', 'PX', 60000])
            ->willReturnOnConsecutiveCalls(
                resolve(null),
                resolve(null),
                resolve(null),
                resolve(null),
                resolve(null),
            );

        $custodian = new Custodian($loop, $this->client);

        $promise = $custodian->spin(5, 0.000001, 'resource', 60, 'r4nd0m');

        $this->assertNotNull($promise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(function (?Lock $lock) use ($loop) {
            $this->assertNull($lock);

            $loop->stop();
        });

        $loop->run();
    }

    public function testSuccessfulRelease()
    {
        $this->client->expects($this->once())
            ->method('__call')
            ->with('eval', [Custodian::RELEASE_SCRIPT, 1, 'release', 'r4nd0m'])
            ->willReturn(resolve('1'));

        $lock = new Lock('release', 60, 'r4nd0m');
        $custodian = new Custodian($this->loop, $this->client);

        $promise = $custodian->release($lock);

        $this->assertNotNull($promise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(function (bool $status) {
            $this->assertTrue($status);
        });
    }

    public function testFailedRelease()
    {
        $this->client->expects($this->once())
            ->method('__call')
            ->with('eval', [Custodian::RELEASE_SCRIPT, 1, 'release', 'r4nd0m'])
            ->willReturn(resolve('0'));

        $lock = new Lock('release', 60, 'r4nd0m');
        $custodian = new Custodian($this->loop, $this->client);

        $promise = $custodian->release($lock);

        $this->assertNotNull($promise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->then(function (bool $status) {
            $this->assertFalse($status);
        });
    }
}
