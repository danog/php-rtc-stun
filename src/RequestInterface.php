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

use Amp\Socket\InternetAddress;
use Webrtc\STUN\Message\MessageInterface;

interface RequestInterface
{
    public function sendMessage(MessageInterface $message, ?InternetAddress $address): void;

    /**
     * Send a request and wait for its response.
     *
     * @return array{MessageInterface, InternetAddress|null} The response and where it came from.
     */
    public function request(MessageInterface $message, ?InternetAddress $address, ?string $integrity_key, int $retransmissions = 0): array;
}
