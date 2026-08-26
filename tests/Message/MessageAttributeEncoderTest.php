<?php

namespace Tests\Webrtc\STUN\Message;

use Amp\Socket\InternetAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\STUN\Message\MessageAttributeEncoder;

#[CoversClass(MessageAttributeEncoder::class)]
class MessageAttributeEncoderTest extends TestCase
{

    public function testUnpackErrorCode()
    {
        $data = hex2bin("00000457526f6c6520436f6e666c696374");
        list($code, $reason) = MessageAttributeEncoder::unpackErrorCode($data);
        $this->assertEquals(487, $code);
        $this->assertEquals("Role Conflict", $reason);
    }

    public function testUnpackErrorCodeTooShort()
    {
        $data = hex2bin("000004");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN error code is less than 4 bytes");
        MessageAttributeEncoder::unpackErrorCode($data);
    }

    public function testUnpackXorAddressIpv4()
    {
        $transactionId = hex2bin("b7e7a701bc34d686fa87dfae");
        $address = MessageAttributeEncoder::unpackXorAddress(hex2bin("0001a147e112a643"), $transactionId);
        $this->assertEquals(new InternetAddress('192.0.2.1', 32853), $address);
    }

    public function testUnpackXorAddressIpv4Truncated()
    {
        $transactionId = hex2bin("b7e7a701bc34d686fa87dfae");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN address has invalid length for IPv4");
        MessageAttributeEncoder::unpackXorAddress(hex2bin("0001a147e112a6"), $transactionId);
    }

    public function testUnpackXorAddressIpv6()
    {
        $transactionId = hex2bin("b7e7a701bc34d686fa87dfae");
        $address = MessageAttributeEncoder::unpackXorAddress(hex2bin("0002a1470113a9faa5d3f179bc25f4b5bed2b9d9"), $transactionId);
        $this->assertEquals(new InternetAddress('2001:db8:1234:5678:11:2233:4455:6677', 32853), $address);
    }

    public function testUnpackXorAddressIpv6Truncated()
    {
        $transactionId = hex2bin("b7e7a701bc34d686fa87dfae");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN address has invalid length for IPv6");
        MessageAttributeEncoder::unpackXorAddress(hex2bin("0002a1470113a9faa5d3f179bc25f4b5bed2b9"), $transactionId);
    }

    public function testUnpackXorAddressTooShort()
    {
        $transactionId = hex2bin("b7e7a701bc34d686fa87dfae");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN address length is less than 4 bytes");
        MessageAttributeEncoder::unpackXorAddress(hex2bin("0001"), $transactionId);
    }

    public function testUnpackXorAddressUnknownProtocol()
    {
        $transactionId = hex2bin("b7e7a701bc34d686fa87dfae");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("STUN address has unknown protocol");
        MessageAttributeEncoder::unpackXorAddress(hex2bin("0003a147e112a643"), $transactionId);
    }

    public function testPackErrorCode()
    {
        $data = MessageAttributeEncoder::packErrorCode([487, "Role Conflict"]);
        $this->assertEquals(hex2bin("00000457526f6c6520436f6e666c696374"), $data);
    }

    public function testPackXorAddressIpv4()
    {
        $transactionId = hex2bin("b7e7a701bc34d686fa87dfae");
        $data = MessageAttributeEncoder::packXorAddress(new InternetAddress('192.0.2.1', 32853), $transactionId);
        $this->assertEquals(hex2bin("0001a147e112a643"), $data);
    }

    public function testPackXorAddressIpv6()
    {
        $transactionId = hex2bin("b7e7a701bc34d686fa87dfae");
        $data = MessageAttributeEncoder::packXorAddress(new InternetAddress('2001:db8:1234:5678:11:2233:4455:6677', 32853), $transactionId);
        $this->assertEquals(hex2bin("0002a1470113a9faa5d3f179bc25f4b5bed2b9d9"), $data);
    }

    public function testPackXorAddressUnknownProtocol()
    {
        $transactionId = hex2bin("b7e7a701bc34d686fa87dfae");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('STUN address attributes must be InternetAddress objects');
        MessageAttributeEncoder::packXorAddress(['192.0.2.1', 32853], $transactionId);
    }

    public function testPackAndUnpackSigned64(): void
    {
        $values = [
            0 => '0000000000000000',
            1 => '0000000000000001',
            -1 => 'ffffffffffffffff',
            PHP_INT_MAX => '7fffffffffffffff',
            PHP_INT_MIN => '8000000000000000',
        ];

        foreach ($values as $value => $hex) {
            $packed = MessageAttributeEncoder::packUnsigned64($value);
            $this->assertSame($hex, bin2hex($packed));
            $this->assertSame($value, MessageAttributeEncoder::unpackUnsigned64($packed));
        }
    }

}
