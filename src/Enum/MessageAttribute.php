<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\STUN\Enum;

/**
 * Enum representing different message attributes for WebRTC protocols.
 *
 * @link https://datatracker.ietf.org/doc/html/rfc5780#section-7
 * */
enum MessageAttribute: int
{
    case MAPPED_ADDRESS = 0x0001;
    case CHANGE_REQUEST = 0x0003;
    case SOURCE_ADDRESS = 0x0004;
    case CHANGED_ADDRESS = 0x0005;
    case USERNAME = 0x0006;
    case MESSAGE_INTEGRITY = 0x0008;
    case ERROR_CODE = 0x0009;
    case CHANNEL_NUMBER = 0x000C;
    case LIFETIME = 0x000D;
    case XOR_PEER_ADDRESS = 0x0012;
    case REALM = 0x0014;
    case NONCE = 0x0015;
    case XOR_RELAYED_ADDRESS = 0x0016;
    case REQUESTED_TRANSPORT = 0x0019;
    case XOR_MAPPED_ADDRESS = 0x0020;
    case PRIORITY = 0x0024;
    case USE_CANDIDATE = 0x0025;
    case SOFTWARE = 0x8022;
    case FINGERPRINT = 0x8028;
    case ICE_CONTROLLED = 0x8029;
    case ICE_CONTROLLING = 0x802A;
    case RESPONSE_ORIGIN = 0x802B;
    case OTHER_ADDRESS = 0x802C;

    /**
     * Resolve an attribute from its case name.
     *
     * @param string $name The attribute case name.
     * @return self|null The matching attribute, or null if the name is unknown.
     */
    public static function fromName(string $name): ?self
    {
        return match ($name) {
            'MAPPED_ADDRESS' => self::MAPPED_ADDRESS,
            'CHANGE_REQUEST' => self::CHANGE_REQUEST,
            'SOURCE_ADDRESS' => self::SOURCE_ADDRESS,
            'CHANGED_ADDRESS' => self::CHANGED_ADDRESS,
            'USERNAME' => self::USERNAME,
            'MESSAGE_INTEGRITY' => self::MESSAGE_INTEGRITY,
            'ERROR_CODE' => self::ERROR_CODE,
            'CHANNEL_NUMBER' => self::CHANNEL_NUMBER,
            'LIFETIME' => self::LIFETIME,
            'XOR_PEER_ADDRESS' => self::XOR_PEER_ADDRESS,
            'REALM' => self::REALM,
            'NONCE' => self::NONCE,
            'XOR_RELAYED_ADDRESS' => self::XOR_RELAYED_ADDRESS,
            'REQUESTED_TRANSPORT' => self::REQUESTED_TRANSPORT,
            'XOR_MAPPED_ADDRESS' => self::XOR_MAPPED_ADDRESS,
            'PRIORITY' => self::PRIORITY,
            'USE_CANDIDATE' => self::USE_CANDIDATE,
            'SOFTWARE' => self::SOFTWARE,
            'FINGERPRINT' => self::FINGERPRINT,
            'ICE_CONTROLLED' => self::ICE_CONTROLLED,
            'ICE_CONTROLLING' => self::ICE_CONTROLLING,
            'RESPONSE_ORIGIN' => self::RESPONSE_ORIGIN,
            'OTHER_ADDRESS' => self::OTHER_ADDRESS,
            default => null,
        };
    }
}