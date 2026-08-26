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
 * Class to handle encoding and decoding of WebRTC message attributes.
 *
 */
class MessageAttributeEncoder
{
    public const COOKIE = 0x2112A442;
    private const IPV4_PROTOCOL = 1;
    private const IPV6_PROTOCOL = 2;

    private const ATTRIBUTES = [
        [MessageAttribute::MAPPED_ADDRESS, 'packAddress', 'unpackAddress'],
        [MessageAttribute::CHANGE_REQUEST, 'packUnsigned', 'unpackUnsigned'],
        [MessageAttribute::SOURCE_ADDRESS, 'packAddress', 'unpackAddress'],
        [MessageAttribute::CHANGED_ADDRESS, 'packAddress', 'unpackAddress'],
        [MessageAttribute::USERNAME, 'returnValue', 'returnValue'],
        [MessageAttribute::MESSAGE_INTEGRITY, 'returnValue', 'returnValue'],
        [MessageAttribute::ERROR_CODE, 'packErrorCode', 'unpackErrorCode'],
        [MessageAttribute::CHANNEL_NUMBER, 'packUnsignedShort', 'unpackUnsignedShort'],
        [MessageAttribute::LIFETIME, 'packUnsigned', 'unpackUnsigned'],
        [MessageAttribute::XOR_PEER_ADDRESS, 'packXorAddress', 'unpackXorAddress'],
        [MessageAttribute::REALM, 'returnValue', 'returnValue'],
        [MessageAttribute::NONCE, 'returnValue', 'returnValue'],
        [MessageAttribute::XOR_RELAYED_ADDRESS, 'packXorAddress', 'unpackXorAddress'],
        [MessageAttribute::REQUESTED_TRANSPORT, 'packUnsigned', 'unpackUnsigned'],
        [MessageAttribute::XOR_MAPPED_ADDRESS, 'packXorAddress', 'unpackXorAddress'],
        [MessageAttribute::PRIORITY, 'packUnsigned', 'unpackUnsigned'],
        [MessageAttribute::USE_CANDIDATE, 'returnNull', 'returnNull'],
        [MessageAttribute::SOFTWARE, 'returnValue', 'returnValue'],
        [MessageAttribute::FINGERPRINT, 'packUnsigned', 'unpackUnsigned'],
        [MessageAttribute::ICE_CONTROLLED, 'packUnsigned64', 'unpackUnsigned64'],
        [MessageAttribute::ICE_CONTROLLING, 'packUnsigned64', 'unpackUnsigned64'],
        [MessageAttribute::RESPONSE_ORIGIN, 'packAddress', 'unpackAddress'],
        [MessageAttribute::OTHER_ADDRESS, 'packAddress', 'unpackAddress']
    ];

    private array $attributesByType = [];
    private array $attributesByName = [];

    /**
     * MessageAttributeEncoder constructor.
     *
     * @param int|string|array|InternetAddress|null $data The message data.
     * @param string $transactionId The transaction ID.
     */
    public function __construct(
        private readonly int|string|array|InternetAddress|null $data,
        private readonly string $transactionId
    )
    {
        foreach (self::ATTRIBUTES as $attr) {
            $this->attributesByType[$attr[0]->value] = $attr;
            $this->attributesByName[$attr[0]->name] = $attr;
        }
    }

    /**
     * Get attributes by type.
     *
     * @param int $type The attribute type.
     * @return array|null The attribute array or null if not found.
     */
    public function getAttributesByType(int $type): ?array
    {
        return $this->attributesByType[$type] ?? null;
    }

    /**
     * Get attributes by name.
     *
     * @param string $name The attribute name.
     * @return array|null The attribute array or null if not found.
     */
    public function getAttributesByName(string $name): ?array
    {
        return $this->attributesByName[$name] ?? null;
    }

    /**
     * Return the value of the attribute as is.
     *
     * @return string The attribute value.
     */
    public function returnValue(): string
    {
        return $this->data;
    }

    /**
     * Return null for the attribute value.
     *
     * @return null|string
     */
    public function returnNull(): ?string
    {
        return null;
    }

    /**
     * Perform XOR operation on the address.
     *
     * @param string|null $data The address data.
     * @return string The XORed address.
     */
    public function xorAddress(?string $data = null): string
    {
        $data = $data ?? $this->data;
        $xpad = pack('nN', self::COOKIE >> 16, self::COOKIE) . $this->transactionId;
        $xdata = substr($data, 0, 2);
        for ($i = 2, $len = strlen($data); $i < $len; $i++) {
            $xdata .= chr(ord($data[$i]) ^ ord($xpad[$i - 2]));
        }
        return $xdata;
    }

    /**
     * Pack the address.
     *
     * @return string The packed address.
     */
    public function packAddress(): string
    {
        if (!$this->data instanceof InternetAddress) {
            throw new InvalidArgumentException('STUN address attributes must be InternetAddress objects');
        }

        $protocol = $this->data->getVersion() === InternetAddressVersion::IPv4
            ? self::IPV4_PROTOCOL
            : self::IPV6_PROTOCOL;

        return pack('C2n', 0, $protocol, $this->data->getPort()) . $this->data->getAddressBytes();
    }

    /**
     * Unpack the address.
     *
     * @param string|null $data The packed address data.
     * @return InternetAddress The unpacked address.
     * @throws InvalidArgumentException If the data is invalid.
     */
    public function unpackAddress(?string $data = null): InternetAddress
    {
        $data = $data ?? $this->data;

        if (strlen($data) < 4) {
            throw new InvalidArgumentException("STUN address length is less than 4 bytes");
        }
        [, $protocol, $port] = array_values(unpack('Creserved/Cprotocol/nport', substr($data, 0, 4)));

        $address = substr($data, 4);

        if ($protocol === self::IPV4_PROTOCOL) {
            if (strlen($address) !== 4) {
                throw new InvalidArgumentException("STUN address has invalid length for IPv4");
            }
            return new InternetAddress(inet_ntop($address), $port);
        } elseif ($protocol === self::IPV6_PROTOCOL) {
            if (strlen($address) !== 16) {
                throw new InvalidArgumentException("STUN address has invalid length for IPv6");
            }
            return new InternetAddress(inet_ntop($address), $port);
        } else {
            throw new InvalidArgumentException("STUN address has unknown protocol");
        }
    }

    /**
     * Pack the XORed address.
     *
     * @return string The packed XORed address.
     */
    public function packXorAddress(): string
    {
        return $this->xorAddress($this->packAddress());
    }

    /**
     * Unpack the XORed address.
     *
     * @return InternetAddress The unpacked XORed address.
     * @throws InvalidArgumentException If the data is invalid.
     */
    public function unpackXorAddress(): InternetAddress
    {
        return $this->unpackAddress($this->xorAddress());
    }

    /**
     * Pack the error code.
     *
     * @return string The packed error code.
     */
    public function packErrorCode(): string
    {
        return pack('nCC', 0, intdiv($this->data[0], 100), $this->data[0] % 100) . $this->data[1];
    }

    /**
     * Unpack the error code.
     *
     * @return array The unpacked error code and reason.
     * @throws InvalidArgumentException If the data is invalid.
     */
    public function unpackErrorCode(): array
    {

        if (strlen($this->data) < 4) {
            throw new InvalidArgumentException("STUN error code is less than 4 bytes");
        }
        [, $codeHigh, $codeLow] = array_values(unpack('nreserved/CcodeHigh/CcodeLow', substr($this->data, 0, 4)));
        $reason = substr($this->data, 4);
        return [$codeHigh * 100 + $codeLow, $reason];
    }

    /**
     * Pack an unsigned integer.
     *
     * @return string The packed unsigned integer.
     */
    public function packUnsigned(): string
    {
        return pack('N', $this->data);
    }

    /**
     * Unpack an unsigned integer.
     *
     * @return int The unpacked unsigned integer.
     */
    public function unpackUnsigned(): int
    {
        return unpack('N', $this->data)[1];
    }

    /**
     * Pack an unsigned short integer.
     *
     * @return string The packed unsigned short integer.
     */
    public function packUnsignedShort(): string
    {
        return pack('n', $this->data) . "\x00\x00";
    }

    /**
     * Unpack an unsigned short integer from the data.
     *
     * @return int The unpacked unsigned short integer.
     */
    public function unpackUnsignedShort(): int
    {
        return unpack('n', substr($this->data, 0, 2))[0];
    }

    /**
     * Pack a signed 64-bit integer in network byte order.
     *
     * @return string The packed 64-bit unsigned integer.
     */
    public function packUnsigned64(): string
    {
        return pack('J', $this->data);
    }

    /**
     * Unpack a signed 64-bit integer in network byte order.
     *
     * @return int The unpacked signed 64-bit integer.
     */
    public function unpackUnsigned64(): int
    {
        return unpack('J', $this->data)[1];
    }

    /**
     * Encode an attribute by name.
     *
     * @param string $attrName The name of the attribute to encode.
     * @param int|string|array|InternetAddress|null $data The data to encode.
     * @param string $transactionId The transaction ID.
     * @return array The encoded attribute type and value.
     */
    public static function encode(
        string $attrName,
        int|string|array|InternetAddress|null $data,
        string $transactionId
    ): array
    {
        return self::getEncode($attrName, $data, $transactionId);
    }

    /**
     * Decode an attribute by type.
     *
     * @param string $attrType The type of the attribute to decode.
     * @param string $data The data to decode.
     * @param string $transactionId The transaction ID.
     * @return array The decoded attribute type and value.
     */
    public static function decode(string $attrType, string $data, string $transactionId): array
    {
        return self::getEncode($attrType, $data, $transactionId, true);
    }

    /**
     * Get the encoded or decoded attribute.
     *
     * @param string $attr The attribute to encode or decode.
     * @param int|string|array|InternetAddress|null $data The data to encode or decode.
     * @param string $transactionId The transaction ID.
     * @param bool $decode Flag to indicate decoding.
     * @return array The attribute type and encoded or decoded value.
     */
    private static function getEncode(
        string $attr,
        int|string|array|InternetAddress|null $data,
        string $transactionId,
        bool $decode = false
    ): array
    {
        $encoder = new static($data, $transactionId);
        $attr = $encoder->{$decode ? 'getAttributesByType' : 'getAttributesByName'}($attr);

        if (!$attr) {
            throw new InvalidArgumentException("Invalid attribute name: $attr");
        }

        $attrType = $attr[0]->{$decode ? 'name' : 'value'};
        $funcName = $attr[$decode ? 2 : 1];

        return [$attrType, call_user_func([$encoder, $funcName])];
    }
}
