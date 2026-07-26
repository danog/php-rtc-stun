<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\STUN;

use Webrtc\STUN\Message\MessageInterface;

interface RequestInterface
{
    public function sendMessage(MessageInterface $message, ?string $address): void;

    /**
     * Send a request and wait for its response.
     *
     * @return array{MessageInterface, string|null} The response and where it came from.
     */
    public function request(MessageInterface $message, ?string $address, ?string $integrity_key, int $retransmissions = 0): array;
}