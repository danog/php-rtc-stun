<?php

namespace Tests\Webrtc\STUN\Message;

use Amp\Socket\InternetAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
#[CoversClass(MessageIntegrity::class)]
#[UsesClass(Utils::class)]
#[UsesClass(\Webrtc\STUN\Enum\MessageAttribute::class)]
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
                'ICE_CONTROLLING' => 5943294521425135761,
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
                'XOR_MAPPED_ADDRESS' => new InternetAddress('80.200.136.90', 53054),
                'MAPPED_ADDRESS' => new InternetAddress('80.200.136.90', 53054),
                'RESPONSE_ORIGIN' => new InternetAddress('52.17.36.97', 3478),
                'OTHER_ADDRESS' => new InternetAddress('52.17.36.97', 3479),
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

    public function testDecodeSkipsUnknownAttributeType()
    {
        // An unrecognised (comprehension-optional) attribute type must be skipped, not rejected.
        $message = Message::decode($this->buildStunMessage($this->rawAttribute(0x9999, "test")));
        $this->assertSame([], $message->attributes()->all());
    }

    public function testDecodeRejectsMalformedKnownAttribute()
    {
        // Regression: a known attribute that fails to decode (here ERROR_CODE, 0x0009, with a
        // sub-4-byte value) was previously swallowed — its name blanked and, crucially, the
        // FINGERPRINT/MESSAGE-INTEGRITY verification keyed off that name skipped — so a malformed
        // integrity attribute could bypass the check and be accepted. It must now reject instead.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN error code is less than 4 bytes");
        Message::decode($this->buildStunMessage($this->rawAttribute(0x0009, "\0\0\0")));
    }

    private function buildStunMessage(string $attributes): string
    {
        // BINDING REQUEST header: type, body length, magic cookie, 12-byte transaction id.
        return pack("n", 0x0001) . pack("n", strlen($attributes))
            . pack("N", MessageAttributeEncoder::COOKIE)
            . "transaction!"
            . $attributes;
    }

    private function rawAttribute(int $type, string $value): string
    {
        $pad = (4 - strlen($value) % 4) % 4;
        return pack("n", $type) . pack("n", strlen($value)) . $value . str_repeat("\0", $pad);
    }

    public function testOnlyStunMessagesAreRecognized(): void
    {
        foreach (["binding_request.bin", "binding_request_ice_controlled.bin", "binding_response.bin"] as $fixture) {
            $this->assertTrue(Message::isStun($this->getMessage($fixture)), $fixture);
        }
        $stun = $this->getMessage("binding_request.bin");
        // RTP and RTCP (128-191), DTLS (20-63), TURN ChannelData (64-79), with a cookie in the right place too.
        foreach ([0x80, 0xC8, 0x16, 0x17, 0x40, 0x4F] as $firstByte) {
            $this->assertFalse(Message::isStun(\chr($firstByte).substr($stun, 1)), "first byte $firstByte");
        }
        $this->assertFalse(Message::isStun(substr($stun, 0, 4)."\0\0\0\0".substr($stun, 8)), "no magic cookie");
        $this->assertFalse(Message::isStun(substr($stun, 0, 19)), "shorter than a header");
        $this->assertFalse(Message::isStun(''));

        // A malformed STUN message is still recognized as one, and decoding it still throws.
        $malformed = $stun.'x';
        $this->assertTrue(Message::isStun($malformed));
        $this->expectException(InvalidArgumentException::class);
        Message::decode($malformed);
    }

    /** @return iterable<string, array{bool}> */
    public static function integrityBackends(): iterable
    {
        yield 'hash extension' => [false];
        if (\extension_loaded('madeline_crypto') && version_compare(\danog\MadelineProto\Native\Crypto::version(), '0.5.0', '>=')) {
            yield 'madeline_crypto' => [true];
        }
    }

    #[DataProvider('integrityBackends')]
    public function testIntegrityIsAnHmacSha1(bool $native): void
    {
        MessageIntegrity::$native = $native;
        try {
            // The length field covers the attribute.
            foreach ([0, 1, 64, 300] as $length) {
                $body = $length ? random_bytes($length) : '';
                $key = random_bytes(16);
                $data = "\x00\x01".pack('n', $length)."\x21\x12\xA4\x42".random_bytes(12).$body;
                $this->assertSame(
                    hash_hmac('sha1', substr($data, 0, 2).pack('n', $length + 24).substr($data, 4), $key, true),
                    MessageIntegrity::messageIntegrity($data, $key),
                );
            }
        } finally {
            MessageIntegrity::$native = null;
        }
    }

    private function getMessage($filename): false|string
    {
        return file_get_contents(__DIR__ . "/../fixture/" . $filename);
    }
}
