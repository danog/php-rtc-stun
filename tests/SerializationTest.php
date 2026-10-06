<?php

namespace Tests\Webrtc\STUN;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WeakReference;
use Webrtc\STUN\Enum\MessageClass;
use Webrtc\STUN\Enum\MessageMethod;
use Webrtc\STUN\IceCandidateInterface;
use Webrtc\STUN\Message\Message;
use Webrtc\STUN\Stun;
use function Amp\delay;

#[CoversNothing]
final class SerializationTest extends TestCase
{
    public function testStunResumesDataFlowAfterSerializeCycle(): void
    {
        $echo = EchoServer::create();
        $echoAddress = $echo->getLocalAddress();

        $receiver = new Receiver();
        $stun = Stun::create($receiver, new \Amp\Socket\InternetAddress('127.0.0.1', 0));
        $stun->setCandidate(new FakeCandidate());
        $port = $stun->getLocalPort();
        $id = $stun->getId();

        // Confirm the live socket works before the cycle.
        $stun->send('before', $echoAddress);
        delay(.1);
        $this->assertSame(['before'], $receiver->getData());

        // serialize => destroy (unset + gc, no close) => unserialize. The receiver owns the protocol (which
        // doesn't own it back), so they're serialized together, as in a real object graph.
        $blob = serialize([$stun, $receiver]);
        $weak = WeakReference::create($stun);
        unset($stun, $receiver);
        while (gc_collect_cycles()) {
        }

        // The object and its bound port must be gone: nothing may pin a connection whose last
        // reference was dropped, or it could never be reclaimed by the event loop.
        $this->assertNull($weak->get(), 'Stun was pinned and not garbage-collected after unset');

        [$restored, $restoredReceiver] = unserialize($blob);
        $this->assertInstanceOf(Stun::class, $restored);
        $this->assertSame($id, $restored->getId());
        $this->assertSame($port, $restored->getLocalPort(), 'restored connection did not rebind its port');

        // Activity resumes: the restored socket sends and receives again, and its restored
        // receiver records the echo.
        $restored->send('after', $echoAddress);
        delay(.1);
        $this->assertInstanceOf(Receiver::class, $restoredReceiver);
        $this->assertSame($restoredReceiver, (new ReflectionProperty(Stun::class, 'receiver'))->getValue($restored)?->get());
        // The receiver's pre-cycle state round-tripped ('before'), and the resumed socket
        // delivered the new echo ('after').
        $this->assertSame(['before', 'after'], $restoredReceiver->getData());

        $restored->close();
        $echo->close();
    }

    public function testAPendingRequestDoesNotPinTheReceiver(): void
    {
        $receiver = new Receiver();
        $stun = Stun::create($receiver, new \Amp\Socket\InternetAddress('127.0.0.1', 0));
        $stun->setCandidate(new FakeCandidate());
        // Nothing answers there: the request is pending, holding the protocol, while the receiver is dropped.
        $request = \Amp\async(fn () => $stun->request(
            Message::new(MessageClass::REQUEST, MessageMethod::BINDING),
            new \Amp\Socket\InternetAddress('127.0.0.1', 9),
            null,
            1,
        ));
        $request->ignore();
        delay(.1);
        $this->assertFalse($request->isComplete());

        $weak = WeakReference::create($receiver);
        unset($receiver);
        while (gc_collect_cycles()) {
        }
        $this->assertNull($weak->get(), 'The receiver was pinned by the pending request');

        $stun->close();
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

final class FakeCandidate implements IceCandidateInterface
{
    public function getComponentId(): int
    {
        return 1;
    }

    public function getHost(): string
    {
        return '127.0.0.1';
    }

    public function getPort(): int
    {
        return 9;
    }
}
