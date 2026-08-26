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

interface TransactionInterface
{
    public function responseReceived(MessageInterface $message, ?InternetAddress $address): void;

    /**
     * Send the request and wait for its response.
     *
     * @return array{MessageInterface, InternetAddress|null} The response and where it came from.
     */
    public function execute(): array;
}
