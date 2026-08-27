<?php

namespace Tests\Webrtc\STUN;

use Amp\Socket\InternetAddress;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use Webrtc\ICE\RTCIceCandidate;
use Webrtc\STUN\Exception\TransactionException;
use Webrtc\STUN\Exception\TransactionFailedException;
use Webrtc\STUN\Message\Message;
use Webrtc\STUN\Message\MessageAttributeCollection;
use Webrtc\STUN\Message\MessageAttributeEncoder;
use Webrtc\STUN\Message\MessageIntegrity;
use Webrtc\STUN\Stun;
use PHPUnit\Framework\TestCase;
use Webrtc\STUN\Transaction;
use Webrtc\STUN\Utils;
use function Amp\delay;

#[UsesClass(Message::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(TransactionException::class)]
#[UsesClass(TransactionFailedException::class)]
#[UsesClass(MessageAttributeCollection::class)]
#[UsesClass(MessageAttributeEncoder::class)]
#[UsesClass(MessageIntegrity::class)]
#[UsesClass(Utils::class)]
#[UsesClass(\Webrtc\SCTP\RTCSctpTransport::class)]
#[UsesClass(\Webrtc\SCTP\SctpTimer::class)]
#[UsesTrait(\Webrtc\SCTP\Trait\DataChannel::class)]
#[CoversClass(Stun::class)]
class StunTest extends TestCase
{
    public function testStun() {
        $echoServer = EchoServer::create();
        $receiver = new Receiver();
        $candidateMock = Mockery::mock(RTCIceCandidate::class);
        $candidateMock->shouldReceive('getComponentId')->once()->andReturn(1);

        $stun = Stun::create($receiver, new InternetAddress('127.0.0.1', 0));
        $stun->setCandidate($candidateMock);
        $stun->send("Hello world!", $echoServer->getLocalAddress());

        delay(.1);
        $this->assertEquals("Hello world!", $receiver->getData()[0]);

        $stun->close();
        $echoServer->close();
    }
}
