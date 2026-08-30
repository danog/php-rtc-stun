<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\STUN\Message;

use Amp\Socket\InternetAddress;
use Amp\Socket\InternetAddressVersion;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\STUN\Enum\MessageAttribute;

/**
 * Stateless helper to encode and decode WebRTC message attributes.
 *
 * Every method is a pure function of its arguments; no instance state is kept.
 */
final class MessageAttributeEncoder
{
    public const COOKIE = 0x2112A442;
    private const IPV4_PROTOCOL = 1;
    private const IPV6_PROTOCOL = 2;

    /**
     * Pack/unpack strategies. Each attribute maps to exactly one via self::CODECS.
     */
    private const CODEC_ADDRESS = 'address';
    private const CODEC_XOR_ADDRESS = 'xorAddress';
    private const CODEC_UNSIGNED = 'unsigned';
    private const CODEC_UNSIGNED_SHORT = 'unsignedShort';
    private const CODEC_UNSIGNED_64 = 'unsigned64';
    private const CODEC_ERROR_CODE = 'errorCode';
    private const CODEC_VALUE = 'value';
    private const CODEC_NULL = 'null';

    /**
     * Map of attribute type to the codec used to pack/unpack its value.
     *
     * @var array<int, string>
     */
    private const CODECS = [
        MessageAttribute::MAPPED_ADDRESS->value => self::CODEC_ADDRESS,
        MessageAttribute::SOURCE_ADDRESS->value => self::CODEC_ADDRESS,
        MessageAttribute::CHANGED_ADDRESS->value => self::CODEC_ADDRESS,
        MessageAttribute::RESPONSE_ORIGIN->value => self::CODEC_ADDRESS,
        MessageAttribute::OTHER_ADDRESS->value => self::CODEC_ADDRESS,

        MessageAttribute::XOR_PEER_ADDRESS->value => self::CODEC_XOR_ADDRESS,
        MessageAttribute::XOR_RELAYED_ADDRESS->value => self::CODEC_XOR_ADDRESS,
        MessageAttribute::XOR_MAPPED_ADDRESS->value => self::CODEC_XOR_ADDRESS,

        MessageAttribute::CHANGE_REQUEST->value => self::CODEC_UNSIGNED,
        MessageAttribute::LIFETIME->value => self::CODEC_UNSIGNED,
        MessageAttribute::REQUESTED_TRANSPORT->value => self::CODEC_UNSIGNED,
        MessageAttribute::PRIORITY->value => self::CODEC_UNSIGNED,
        MessageAttribute::FINGERPRINT->value => self::CODEC_UNSIGNED,

        MessageAttribute::CHANNEL_NUMBER->value => self::CODEC_UNSIGNED_SHORT,

        MessageAttribute::ICE_CONTROLLED->value => self::CODEC_UNSIGNED_64,
        MessageAttribute::ICE_CONTROLLING->value => self::CODEC_UNSIGNED_64,

        MessageAttribute::ERROR_CODE->value => self::CODEC_ERROR_CODE,

        MessageAttribute::USERNAME->value => self::CODEC_VALUE,
        MessageAttribute::MESSAGE_INTEGRITY->value => self::CODEC_VALUE,
        MessageAttribute::REALM->value => self::CODEC_VALUE,
        MessageAttribute::NONCE->value => self::CODEC_VALUE,
        MessageAttribute::SOFTWARE->value => self::CODEC_VALUE,

        MessageAttribute::USE_CANDIDATE->value => self::CODEC_NULL,
    ];

    /**
     * Encode an attribute by name.
     *
     * @param string $attrName The name of the attribute to encode.
     * @param int|string|array|InternetAddress|null $data The data to encode.
     * @param string $transactionId The transaction ID.
     * @return array{int, string|null} The encoded attribute type and value.
     */
    public static function encode(
        string $attrName,
        int|string|array|InternetAddress|null $data,
        string $transactionId
    ): array
    {
        $attribute = MessageAttribute::fromName($attrName);

        if ($attribute === null) {
            throw new InvalidArgumentException("Invalid attribute name: $attrName");
        }

        $packed = match (self::CODECS[$attribute->value]) {
            self::CODEC_ADDRESS => self::packAddress($data),
            self::CODEC_XOR_ADDRESS => self::packXorAddress($data, $transactionId),
            self::CODEC_UNSIGNED => self::packUnsigned($data),
            self::CODEC_UNSIGNED_SHORT => self::packUnsignedShort($data),
            self::CODEC_UNSIGNED_64 => self::packUnsigned64($data),
            self::CODEC_ERROR_CODE => self::packErrorCode($data),
            self::CODEC_VALUE => self::packRawValue($data),
            self::CODEC_NULL => null,
        };

        return [$attribute->value, $packed];
    }

    /**
     * Decode an attribute by type.
     *
     * @param int $attrType The type of the attribute to decode.
     * @param string $data The data to decode.
     * @param string $transactionId The transaction ID.
     * @return array{string, InternetAddress|array<array-key, mixed>|int|string|null} The decoded attribute name and value.
     */
    public static function decode(int $attrType, string $data, string $transactionId): array
    {
        $attribute = MessageAttribute::tryFrom($attrType);

        if ($attribute === null) {
            throw new InvalidArgumentException("Invalid attribute type: $attrType");
        }

        $unpacked = match (self::CODECS[$attribute->value]) {
            self::CODEC_ADDRESS => self::unpackAddress($data),
            self::CODEC_XOR_ADDRESS => self::unpackXorAddress($data, $transactionId),
            self::CODEC_UNSIGNED => self::unpackUnsigned($data),
            self::CODEC_UNSIGNED_SHORT => self::unpackUnsignedShort($data),
            self::CODEC_UNSIGNED_64 => self::unpackUnsigned64($data),
            self::CODEC_ERROR_CODE => self::unpackErrorCode($data),
            self::CODEC_VALUE => $data,
            self::CODEC_NULL => null,
        };

        return [$attribute->name, $unpacked];
    }

    /**
     * Perform the STUN XOR operation on an address.
     *
     * @param string $data The address data.
     * @param string $transactionId The transaction ID.
     * @return string The XORed address.
     */
    public static function xorAddress(string $data, string $transactionId): string
    {
        $xpad = pack('nN', self::COOKIE >> 16, self::COOKIE) . $transactionId;
        $xdata = substr($data, 0, 2);
        for ($i = 2, $len = strlen($data); $i < $len; $i++) {
            $xdata .= chr(ord($data[$i]) ^ ord($xpad[$i - 2]));
        }
        return $xdata;
    }

    /**
     * Pack an address.
     *
     * @param mixed $data The address, which must be an InternetAddress.
     * @return string The packed address.
     */
    public static function packAddress(mixed $data): string
    {
        if (!$data instanceof InternetAddress) {
            throw new InvalidArgumentException('STUN address attributes must be InternetAddress objects');
        }

        $protocol = $data->getVersion() === InternetAddressVersion::IPv4
            ? self::IPV4_PROTOCOL
            : self::IPV6_PROTOCOL;

        return pack('C2n', 0, $protocol, $data->getPort()) . $data->getAddressBytes();
    }

    /**
     * Validate and return a raw attribute value (a byte string) for CODEC_VALUE attributes.
     *
     * @param mixed $data The raw value.
     * @return string|null The validated raw value.
     */
    private static function packRawValue(mixed $data): ?string
    {
        if ($data !== null && !is_string($data)) {
            throw new InvalidArgumentException('Raw STUN attribute value must be a string or null');
        }
        return $data;
    }

    /**
     * Unpack an address.
     *
     * @param string $data The packed address data.
     * @return InternetAddress The unpacked address.
     * @throws InvalidArgumentException If the data is invalid.
     */
    public static function unpackAddress(string $data): InternetAddress
    {
        if (strlen($data) < 4) {
            throw new InvalidArgumentException("STUN address length is less than 4 bytes");
        }
        $header = unpack('Creserved/Cprotocol/nport', substr($data, 0, 4));
        if ($header === false) {
            throw new InvalidArgumentException("Failed to unpack STUN address header");
        }
        $protocol = (int) $header['protocol'];
        $port = (int) $header['port'];

        $address = substr($data, 4);

        if ($protocol === self::IPV4_PROTOCOL) {
            if (strlen($address) !== 4) {
                throw new InvalidArgumentException("STUN address has invalid length for IPv4");
            }
        } elseif ($protocol === self::IPV6_PROTOCOL) {
            if (strlen($address) !== 16) {
                throw new InvalidArgumentException("STUN address has invalid length for IPv6");
            }
        } else {
            throw new InvalidArgumentException("STUN address has unknown protocol");
        }

        $textAddress = inet_ntop($address);
        if ($textAddress === false) {
            throw new InvalidArgumentException("STUN address bytes are not a valid IP address");
        }
        assert($port >= 0 && $port <= 65535);
        return new InternetAddress($textAddress, $port);
    }

    /**
     * Pack an XORed address.
     *
     * @param mixed $data The address, which must be an InternetAddress.
     * @param string $transactionId The transaction ID.
     * @return string The packed XORed address.
     */
    public static function packXorAddress(mixed $data, string $transactionId): string
    {
        return self::xorAddress(self::packAddress($data), $transactionId);
    }

    /**
     * Unpack an XORed address.
     *
     * @param string $data The packed XORed address data.
     * @param string $transactionId The transaction ID.
     * @return InternetAddress The unpacked XORed address.
     * @throws InvalidArgumentException If the data is invalid.
     */
    public static function unpackXorAddress(string $data, string $transactionId): InternetAddress
    {
        return self::unpackAddress(self::xorAddress($data, $transactionId));
    }

    /**
     * Pack an error code.
     *
     * @param mixed $data The [code, reason] pair.
     * @return string The packed error code.
     */
    public static function packErrorCode(mixed $data): string
    {
        if (!is_array($data) || !isset($data[0], $data[1])) {
            throw new InvalidArgumentException('STUN error-code attribute must be a [code, reason] pair');
        }
        $code = (int) $data[0];
        $reason = (string) $data[1];
        return pack('nCC', 0, intdiv($code, 100), $code % 100) . $reason;
    }

    /**
     * Unpack an error code.
     *
     * @param string $data The packed error code data.
     * @return array{int, string} The unpacked error code and reason.
     * @throws InvalidArgumentException If the data is invalid.
     */
    public static function unpackErrorCode(string $data): array
    {
        if (strlen($data) < 4) {
            throw new InvalidArgumentException("STUN error code is less than 4 bytes");
        }
        $header = unpack('nreserved/CcodeHigh/CcodeLow', substr($data, 0, 4));
        if ($header === false) {
            throw new InvalidArgumentException("Failed to unpack STUN error code");
        }
        $codeHigh = (int) $header['codeHigh'];
        $codeLow = (int) $header['codeLow'];
        $reason = substr($data, 4);
        return [$codeHigh * 100 + $codeLow, $reason];
    }

    /**
     * Pack an unsigned integer.
     *
     * @param mixed $data The value to pack.
     * @return string The packed unsigned integer.
     */
    public static function packUnsigned(mixed $data): string
    {
        if (!is_int($data)) {
            throw new InvalidArgumentException('STUN unsigned attribute must be an integer');
        }
        return pack('N', $data);
    }

    /**
     * Unpack an unsigned integer.
     *
     * @param string $data The packed value.
     * @return int The unpacked unsigned integer.
     */
    public static function unpackUnsigned(string $data): int
    {
        $unpacked = unpack('Nvalue', $data);
        if ($unpacked === false) {
            throw new InvalidArgumentException("Failed to unpack unsigned integer");
        }
        return (int) $unpacked['value'];
    }

    /**
     * Pack an unsigned short integer.
     *
     * @param mixed $data The value to pack.
     * @return string The packed unsigned short integer.
     */
    public static function packUnsignedShort(mixed $data): string
    {
        if (!is_int($data)) {
            throw new InvalidArgumentException('STUN unsigned-short attribute must be an integer');
        }
        return pack('n', $data) . "\x00\x00";
    }

    /**
     * Unpack an unsigned short integer from the data.
     *
     * @param string $data The packed value.
     * @return int The unpacked unsigned short integer.
     */
    public static function unpackUnsignedShort(string $data): int
    {
        $unpacked = unpack('nvalue', substr($data, 0, 2));
        if ($unpacked === false) {
            throw new InvalidArgumentException("Failed to unpack unsigned short integer");
        }
        return (int) $unpacked['value'];
    }

    /**
     * Pack a signed 64-bit integer in network byte order.
     *
     * @param mixed $data The value to pack.
     * @return string The packed 64-bit integer.
     */
    public static function packUnsigned64(mixed $data): string
    {
        if (!is_int($data)) {
            throw new InvalidArgumentException('STUN unsigned-64 attribute must be an integer');
        }
        return pack('J', $data);
    }

    /**
     * Unpack a signed 64-bit integer in network byte order.
     *
     * @param string $data The packed value.
     * @return int The unpacked signed 64-bit integer.
     */
    public static function unpackUnsigned64(string $data): int
    {
        $unpacked = unpack('Jvalue', $data);
        if ($unpacked === false) {
            throw new InvalidArgumentException("Failed to unpack 64-bit integer");
        }
        return (int) $unpacked['value'];
    }
}
