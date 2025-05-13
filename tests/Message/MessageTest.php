<?php

namespace Tests\Webrtc\STUN\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\STUN\Enum\MessageClass;
use Webrtc\STUN\Enum\MessageMethod;
use Webrtc\STUN\Message\Message;
use Webrtc\STUN\Message\MessageAttributeCollection;
use Webrtc\STUN\Message\MessageAttributeEncoder;
use Webrtc\STUN\Message\MessageIntegrity;
use Webrtc\STUN\Utils;

#[UsesClass(MessageAttributeCollection::class)]
#[UsesClass(MessageAttributeEncoder::class)]
#[UsesClass(MessageIntegrity::class)]
#[UsesClass(Utils::class)]
#[CoversClass(Message::class)]
class MessageTest extends TestCase
{
    public function testBindingRequest()
    {
        $data = $this->getMessage("binding_request.bin");

        $message = Message::decode($data);
        $this->assertEquals(MessageMethod::BINDING, $message->getMessageMethod());
        $this->assertEquals(MessageClass::REQUEST, $message->getMessageClass());
        $this->assertEquals("Nvfx3lU7FUBF", $message->getTransactionId());
        $this->assertInstanceOf(MessageAttributeCollection::class, $message->attributes());

        $this->assertEquals($data, $message->encode());
        $this->assertEquals(
            "ID: 4e766678336c553746554246 - Class: REQUEST - Method: BINDING - Attributes: No Attributes",
            $message->humanReadable()
        );
    }

    public function testBindingRequestIceControlled()
    {
        $data = $this->getMessage("binding_request_ice_controlled.bin");

        $message = Message::decode($data);
        $this->assertEquals(MessageMethod::BINDING, $message->getMessageMethod());
        $this->assertEquals(MessageClass::REQUEST, $message->getMessageClass());
        $this->assertEquals("wxaNbAdXjwG3", $message->getTransactionId());
        $this->assertEquals(
            [
                'USERNAME' => 'AYeZ:sw7YvCSbcVex3bhi',
                'PRIORITY' => 1685987071,
                'SOFTWARE' => 'FreeSWITCH (-37-987c9b9 64bit)',
                'ICE_CONTROLLED' => 5491930053772927353,
                'MESSAGE_INTEGRITY' => hex2bin("1963108a4f764015a66b3fea0b1883dfde1436c8"),
                'FINGERPRINT' => 3230414530,
            ],
            $message->attributes()->all()
        );

        $this->assertEquals($data, $message->encode());
    }

    public function testBindingRequestIceControlledBadFingerprint()
    {
        $data = substr($this->getMessage("binding_request_ice_controlled.bin"), 0, -1) . "z";

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN message fingerprint does not match");
        Message::decode($data);
    }

    public function testBindingRequestIceControlledBadIntegrity()
    {
        $data = $this->getMessage("binding_request_ice_controlled.bin");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN message integrity does not match");
        Message::decode($data, "bogus-key");
    }

    public function testBindingRequestIceControlling()
    {
        $data = $this->getMessage("binding_request_ice_controlling.bin");

        $message = Message::decode($data);
        $this->assertEquals(MessageMethod::BINDING, $message->getMessageMethod());
        $this->assertEquals(MessageClass::REQUEST, $message->getMessageClass());
        $this->assertEquals("JEwwUxjLWaa2", $message->getTransactionId());
        $this->assertEquals(
            [
                'USERNAME' => 'sw7YvCSbcVex3bhi:AYeZ',
                'ICE_CONTROLLING' => '5943294521425135761',
                'USE_CANDIDATE' => null,
                'PRIORITY' => 1853759231,
                'MESSAGE_INTEGRITY' => hex2bin("c87b58eccbacdbc075d497ad0c965a82937ab587"),
                'FINGERPRINT' => 1347006354,
            ],
            $message->attributes()->all()
        );
    }

    public function testBindingResponse()
    {
        $data = $this->getMessage("binding_response.bin");

        $message = Message::decode($data);
        $this->assertEquals(MessageMethod::BINDING, $message->getMessageMethod());
        $this->assertEquals(MessageClass::RESPONSE, $message->getMessageClass());
        $this->assertEquals("Nvfx3lU7FUBF", $message->getTransactionId());
        $this->assertEquals(
            [
                'XOR_MAPPED_ADDRESS' => ["80.200.136.90", 53054],
                'MAPPED_ADDRESS' => ["80.200.136.90", 53054],
                'RESPONSE_ORIGIN' => ["52.17.36.97", 3478],
                'OTHER_ADDRESS' => ["52.17.36.97", 3479],
                'SOFTWARE' => "Citrix-3.2.4.5 'Marshal West'",
            ],
            $message->attributes()->all()
        );

        $this->assertEquals($data, $message->encode());
    }

    public function testMessageBodyLengthMismatch()
    {
        $data = $this->getMessage("binding_response.bin") . "123";
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN message length does not match");
        Message::decode($data);
    }

    public function testMessageShorterThanHeader()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN message length is less than 20 bytes");
        Message::decode("123");
    }

    public function testMessageWithUnknownMethod()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN message type does not match");
        Message::decode(str_repeat("\0", 20));
    }

    private function getMessage($filename): false|string
    {
        return file_get_contents(__DIR__ . "/../fixture/" . $filename);
    }
}
