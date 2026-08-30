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
use Stringable;
use Random\RandomException;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\STUN\Enum\MessageAttribute;
use Webrtc\STUN\Enum\MessageClass;
use Webrtc\STUN\Enum\MessageMethod;
use Webrtc\STUN\Utils;

/**
 * Class Message
 * Represents a WebRTC message with attributes.
 *
 * @link https://datatracker.ietf.org/doc/html/rfc5389#page-10
 */
final class Message implements MessageInterface
{
    private string $transactionId;
    private MessageAttributeCollection $_attributes;

    /**
     * Message constructor.
     *
     * @param MessageClass $messageClass
     * @param MessageMethod $messageMethod
     * @throws RandomException
     */
    public function __construct(private readonly MessageClass $messageClass, private readonly MessageMethod $messageMethod)
    {
        $this->transactionId = random_bytes(12);
        $this->_attributes = new MessageAttributeCollection;
    }

    /**
     * Get the message attributes.
     *
     * @return MessageAttributeCollection
     */
    #[\Override]
    public function attributes(): MessageAttributeCollection
    {
        return $this->_attributes;
    }

    /**
     * Get the transaction ID.
     *
     * @return string
     */
    #[\Override]
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * @return MessageClass
     */
    #[\Override]
    public function getMessageClass(): MessageClass
    {
        return $this->messageClass;
    }

    /**
     * @return MessageMethod
     */
    #[\Override]
    public function getMessageMethod(): MessageMethod
    {
        return $this->messageMethod;
    }

    /**
     * Set the transaction ID.
     *
     * @param string $transactionId
     */
    #[\Override]
    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    /**
     * Add message integrity attributes.
     *
     * @param string $key The key for HMAC.
     */
    #[\Override]
    public function addMessageIntegrity(string $key): void
    {
        $this->_attributes->add(MessageAttribute::MESSAGE_INTEGRITY, MessageIntegrity::messageIntegrity($this->encode(), $key));
        $this->_attributes->add(MessageAttribute::FINGERPRINT, MessageIntegrity::messageFingerprint($this->encode()));
    }

    /**
     * Encode the message to bytes.
     *
     * @return string
     */
    #[\Override]
    public function encode(): string
    {
        $encodedAttributes = $this->getEncodedAttributes();
        return pack(
                'nnNa12',
                $this->messageMethod->value | $this->messageClass->value,
                strlen($encodedAttributes),
                MessageAttributeEncoder::COOKIE,
                $this->transactionId
            ) . $encodedAttributes;
    }

    /**
     * Encode the attributes message to bytes
     *
     * @return string
     */
    private function getEncodedAttributes(): string
    {
        $data = '';
        foreach ($this->_attributes as $attrName => $attrValue) {
            [$attrType, $packedData] = MessageAttributeEncoder::encode($attrName, $attrValue, $this->transactionId);
            $packedBytes = $packedData === null ? '' : $packedData;
            $attrLen = strlen($packedBytes);
            $padLen = Utils::paddingLength($attrLen);
            $data .= pack('nn', $attrType, $attrLen) . $packedBytes . str_repeat("\0", $padLen);
        }

        return $data;
    }

    /**
     * Decode a message from bytes.
     *
     * @param string $data The message data.
     * @param ?string $integrityKey Optional integrity key for HMAC validation.
     * @return static
     * @throws InvalidArgumentException If the message is invalid.
     * @throws RandomException
     */
    #[\Override]
    public static function decode(string $data, ?string $integrityKey = null): static
    {
        if (strlen($data) < MessageIntegrity::HEADER_LENGTH) {
            throw new InvalidArgumentException("STUN message length is less than 20 bytes");
        }

        $header = unpack('nmessageType/nlength/Ncookie/a12transactionId', substr($data, 0, MessageIntegrity::HEADER_LENGTH));
        if ($header === false) {
            throw new InvalidArgumentException("Failed to unpack STUN message header");
        }
        $messageType = (int) $header['messageType'];
        $length = (int) $header['length'];
        $transactionId = (string) $header['transactionId'];

        if (strlen($data) !== MessageIntegrity::HEADER_LENGTH + $length) {
            throw new InvalidArgumentException("STUN message length does not match");
        }

        if (!($class = MessageClass::tryFrom($messageType & 0x0110) )|| !($method = MessageMethod::tryFrom($messageType & 0x3EEF))) {
            throw new InvalidArgumentException("STUN message type does not match");
        }

        $message = new static($class, $method);
        $message->setTransactionId($transactionId);
        $message->attributes()->merge(self::getDecodedAttributes($data, $transactionId, $integrityKey));

        return $message;
    }

    /**
     * Decode the attribute message from bytes
     *
     * @param string $data
     * @param string $transactionId
     * @param string|null $integrityKey
     * @return array<string, InternetAddress|array<array-key, mixed>|int|string|null>
     */
    private static function getDecodedAttributes(string $data, string $transactionId, ?string $integrityKey): array
    {
        $attributes = [];
        $pos = MessageIntegrity::HEADER_LENGTH;

        while ($pos <= strlen($data) - 4) {

            $attrHeader = unpack('nattrType/nattrLen', substr($data, $pos, 4));
            if ($attrHeader === false) {
                throw new InvalidArgumentException("Failed to unpack STUN attribute header");
            }
            $attrType = (int) $attrHeader['attrType'];
            $attrLen = (int) $attrHeader['attrLen'];

            $value = substr($data, $pos + 4, $attrLen);
            $padLen = Utils::paddingLength($attrLen);

            try {
                [$attrName, $unpackedData] = MessageAttributeEncoder::decode($attrType, $value, $transactionId);
                $attributes[$attrName] = $unpackedData;
            } catch (InvalidArgumentException $e) {
                $attrName = '';
            }

            if ($attrName === MessageAttribute::FINGERPRINT->name && $attributes[$attrName] !== MessageIntegrity::messageFingerprint(substr($data, 0, $pos))) {
                throw new InvalidArgumentException("STUN message fingerprint does not match");
            }

            if ($attrName === MessageAttribute::MESSAGE_INTEGRITY->name && $integrityKey !== null
                && $attributes[$attrName] !== MessageIntegrity::messageIntegrity(substr($data, 0, $pos), $integrityKey)) {
                throw new InvalidArgumentException("STUN message integrity does not match");
            }

            $pos += 4 + $attrLen + $padLen;
        }

        return $attributes;
    }

    /**
     * Create a new message.
     *
     * @param MessageClass $messageClass The message class.
     * @param MessageMethod $messageMethod The message method.
     * @param array<string, InternetAddress|array<array-key, mixed>|int|string|null> $attributes The message attributes.
     * @param string|null $integrityKey
     * @return Message The Message object.
     * @throws RandomException
     */
    #[\Override]
    public static function new(MessageClass $messageClass, MessageMethod $messageMethod, array $attributes = [], ?string $integrityKey = null): MessageInterface
    {
        $message = new static($messageClass, $messageMethod);
        $message->attributes()->merge($attributes);
        if ($integrityKey !== null && $integrityKey !== '') {
            $message->addMessageIntegrity($integrityKey);
        }

        return $message;
    }

    /**
     * @return string
     */
    #[\Override]
    public function __toString(): string
    {
        return $this->encode();
    }

    /**
     * Convert a Message object to a human-readable message
     *
     * @return string
     */
    #[\Override]
    public function humanReadable(): string
    {
        $info = [
            "ID: " . bin2hex($this->transactionId),
            "Class: {$this->messageClass->name}",
            "Method: {$this->messageMethod->name}",
            "Attributes: " . $this->getAttributesInfo()
        ];

        return implode(" - ", $info);
    }

    /**
     * Convert attributes to human-readable
     *
     * @return string
     */
    private function getAttributesInfo(): string
    {
        if(count($this->attributes()) === 0) {
            return "No Attributes";
        }

        $attr = '[';

        foreach ($this->attributes()->all() as $attrName => $attrVal) {
            if (is_array($attrVal)) {
                $rendered = "(" . implode(", ", array_map(
                    static fn(mixed $v): string => is_scalar($v) ? (string) $v : get_debug_type($v),
                    $attrVal
                )) . ")";
            } elseif (is_scalar($attrVal) || $attrVal instanceof Stringable) {
                $rendered = (string) $attrVal;
            } else {
                $rendered = get_debug_type($attrVal);
            }
            if ($attrName === "MESSAGE_INTEGRITY") {
                $rendered = bin2hex($rendered);
            }
            $attr .= $attrName . ": " . $rendered . ", ";
        }

        return substr($attr, 0, -2) . "]";
    }
}
