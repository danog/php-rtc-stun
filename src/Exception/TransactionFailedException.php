<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\STUN\Exception;

use Webrtc\STUN\Enum\MessageAttribute;
use Webrtc\STUN\Message\MessageInterface;

/**
 * Exception class for failed STUN transactions.
 */
final class TransactionFailedException extends TransactionException
{
    public function __construct(MessageInterface $message)
    {
        $this->stunMessage = $message;
        parent::__construct($this->getMessageText());
    }

    private function getMessageText(): string
    {
        $out = "STUN transaction failed";
        $stunMessage = $this->stunMessage;
        if ($stunMessage !== null && $stunMessage->attributes()->has(MessageAttribute::ERROR_CODE)) {
            $errorCode = $stunMessage->attributes()->get(MessageAttribute::ERROR_CODE);
            if (is_array($errorCode)) {
                $out .= sprintf(" (%s - %s)", (string) ($errorCode[0] ?? ""), (string) ($errorCode[1] ?? ""));
            }
        }
        return $out;
    }
}