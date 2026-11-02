<?php

namespace Tests\Webrtc\STUN;

use Amp\DeferredFuture;
use Amp\Socket\InternetAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webrtc\STUN\DatagramDispatcher;

use function Amp\delay;

#[CoversClass(DatagramDispatcher::class)]
final class DatagramDispatcherTest extends TestCase
{
    public function testDatagramsAreHandledInOrderByTheSameFiber(): void
    {
        $handled = [];
        $fibers = [];
        $dispatcher = new DatagramDispatcher(static function (string $data) use (&$handled, &$fibers): void {
            $handled[] = $data;
            $fibers[spl_object_id(\Fiber::getCurrent())] = \Fiber::getCurrent();
        });
        $address = new InternetAddress('127.0.0.1', 1);
        for ($i = 0; $i < 100; $i++) {
            $dispatcher->dispatch("$i", $address);
        }
        // Handled synchronously, in one fiber.
        $this->assertSame(array_map(strval(...), range(0, 99)), $handled);
        $this->assertCount(1, $fibers);
        $dispatcher->stop();
        $this->assertTrue(reset($fibers)->isTerminated());
    }

    /**
     * A handler waiting for what a later datagram brings: that one is handled meanwhile (by another fiber), as
     * when a STUN transaction waits for its response.
     */
    public function testASuspendedHandlerDoesNotBlockTheNextDatagrams(): void
    {
        $response = new DeferredFuture;
        $handled = [];
        $dispatcher = new DatagramDispatcher(static function (string $data) use (&$handled, $response): void {
            if ($data === 'request') {
                $handled[] = 'request: '.$response->getFuture()->await();
                return;
            }
            if ($data === 'response') {
                $response->complete('answered');
            }
            if ($data === 'invalid') {
                throw new \RuntimeException('Invalid datagram');
            }
            $handled[] = $data;
        });
        $address = new InternetAddress('127.0.0.1', 1);
        $dispatcher->dispatch('request', $address);
        $dispatcher->dispatch('media', $address);
        $dispatcher->dispatch('invalid', $address);
        $dispatcher->dispatch('response', $address);
        $this->assertSame(['media', 'response'], $handled);
        delay(0);
        $this->assertSame(['media', 'response', 'request: answered'], $handled);
        // The fiber that was suspended ended: the other one waits for the next datagrams.
        $dispatcher->dispatch('more', $address);
        $this->assertSame(['media', 'response', 'request: answered', 'more'], $handled);
        $dispatcher->stop();
    }
}
