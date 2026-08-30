<?php

namespace Tests\Webrtc\STUN;

use Amp\Socket\InternetAddress;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Webrtc\AVCodec\Context\Context;
use Webrtc\AVCodec\Frame\AudioFrame;
use Webrtc\AVCodec\Frame\Frame;
use Webrtc\RTP\Receiver\DecoderQueue;
use Webrtc\STUN\BaseProtocol;
use Webrtc\STUN\Enum\MessageAttribute;
use Webrtc\STUN\Enum\MessageClass;
use Webrtc\STUN\Enum\MessageMethod;
use Webrtc\STUN\Exception\TransactionFailedException;
use Webrtc\STUN\Exception\TransactionTimeoutException;
use Webrtc\STUN\Message\Message;
use Webrtc\STUN\Message\MessageAttributeCollection;
use Webrtc\STUN\Stun;
use Webrtc\STUN\Transaction;
use PHPUnit\Framework\TestCase;

#[UsesClass(TransactionTimeoutException::class)]
#[UsesClass(Message::class)]
#[UsesClass(MessageAttributeCollection::class)]
#[UsesClass(TransactionFailedException::class)]
#[UsesClass(BaseProtocol::class)]
#[UsesClass(Stun::class)]
#[CoversClass(Transaction::class)]
class TransactionTest extends TestCase
{
    private Stun $stun;
    protected function setUp(): void
    {
        parent::setUp();
        $this->stun = Mockery::mock(Stun::class);
        $this->stun->shouldReceive('sendMessage');
        $this->stun->shouldReceive('removeTransaction');
    }

    public function testTransactionTimeout()
    {
        $message = Message::new(MessageClass::REQUEST, MessageMethod::BINDING);
        $transaction = new Transaction($message, new InternetAddress('127.0.0.1', 2365), $this->stun, 0);

        $this->expectException(TransactionTimeoutException::class);
        $transaction->execute();
    }

    public function testTransactionReceive()
    {
        $message = Message::new(MessageClass::RESPONSE, MessageMethod::BINDING);
        $address = new InternetAddress('127.0.0.1', 2365);
        $transaction = new Transaction($message, $address, $this->stun);

        $transaction->responseReceived($message, $address);
        $response = $transaction->getDeferred()->getFuture()->await();
        $this->assertEquals($message, $response[0]);
        $this->assertSame($address, $response[1]);
    }

    public function testTransactionFailed()
    {
        $attr = [MessageAttribute::ERROR_CODE->name => [487, "Role Conflict"]];
        $response = Message::new(MessageClass::RESPONSE,MessageMethod::BINDING, $attr);

        $e = new TransactionFailedException($response);
        $this->assertEquals("STUN transaction failed (487 - Role Conflict)", $e->getMessage());
    }

    public function testTransactionExceptionTimeout()
    {
        $e = new TransactionTimeoutException();
        $this->assertEquals("STUN transaction timed out", $e->getMessage());
    }
}
