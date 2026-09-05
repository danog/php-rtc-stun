<?php

namespace Tests\Webrtc\STUN;

use Amp\Socket\InternetAddress;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;
use Webrtc\STUN\Enum\MessageClass;
use Webrtc\STUN\Enum\MessageMethod;
use Webrtc\STUN\IceConnectionProtocolInterface;
use Webrtc\STUN\Message\Message;
use Webrtc\STUN\Message\MessageInterface;
use Webrtc\STUN\ReceiverInterface;
use Webrtc\STUN\Stun;
use function Amp\Socket\bindUdpSocket;

#[CoversNothing]
final class SerializationTest extends TestCase
{
    public function testStunRebindsSocketAfterSerializeCycle(): void
    {
        $socket = bindUdpSocket('127.0.0.1:0');
        $stun = new Stun(new DummyReceiver(), $socket);
        $port = $stun->getLocalPort();
        $id = $stun->getId();

        $blob = serialize($stun);
        $stun->close();
        unset($stun);
        gc_collect_cycles();

        $restored = unserialize($blob);
        $this->assertInstanceOf(Stun::class, $restored);
        $this->assertSame($id, $restored->getId());
        $this->assertSame($port, $restored->getLocalPort());
        $restored->close();
    }

    public function testMessageSurvivesSerializeCycle(): void
    {
        $message = Message::new(MessageClass::REQUEST, MessageMethod::BINDING);
        $blob = serialize($message);
        unset($message);
        $restored = unserialize($blob);
        $this->assertInstanceOf(Message::class, $restored);
        $this->assertSame(MessageMethod::BINDING, $restored->getMessageMethod());
    }
}

final class DummyReceiver implements ReceiverInterface
{
    public function onDataReceived(string $data, int $componentId): void
    {
    }

    public function onRequestReceived(MessageInterface $message, InternetAddress $address, IceConnectionProtocolInterface $protocol, string $data): void
    {
    }

    public function onClose(): void
    {
    }

    public function onError(Throwable $e): void
    {
    }
}
