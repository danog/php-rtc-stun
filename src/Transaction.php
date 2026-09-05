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

use Amp\DeferredFuture;
use Amp\Socket\InternetAddress;
use Revolt\EventLoop;
use Webrtc\STUN\Enum\MessageClass;
use Webrtc\STUN\Exception\TransactionFailedException;
use Webrtc\STUN\Exception\TransactionTimeoutException;
use Webrtc\STUN\Message\MessageInterface;

/**
 * Class representing a STUN transaction.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc5389#section-7.2 Sending the Request or Indication
 */
final class Transaction implements TransactionInterface
{
    private const RETRY_MAX = 5;
    private const RETRY_RTO = 0.5;
    private ?InternetAddress $address;

    /** @var DeferredFuture<array{MessageInterface, InternetAddress|null}> */
    private DeferredFuture $deferred;
    private MessageInterface $message;
    private float $timeoutDelay;
    private IceConnectionProtocolInterface $transport;
    private int $tries = 0;
    private int $triesMax;
    private bool $isResolvedOrReject = false;

    /** Handle of the pending retransmission timer, so it can be cancelled once we are done. */
    private ?string $timer = null;

    /**
     * Transaction constructor.
     *
     * @param MessageInterface $message The STUN request message.
     * @param InternetAddress|null $address The destination address.
     * @param IceConnectionProtocolInterface $transport The protocol object.
     * @param int|null $retransmissions Number of retransmissions, default is RETRY_MAX.
     */
    public function __construct(MessageInterface $message, ?InternetAddress $address, IceConnectionProtocolInterface $transport, ?int $retransmissions = null)
    {
        $this->address = $address;
        /** @var DeferredFuture<array{MessageInterface, InternetAddress|null}> */
        $this->deferred = new DeferredFuture();
        $this->message = $message;
        $this->timeoutDelay = self::RETRY_RTO;
        $this->transport = $transport;
        $this->triesMax = 1 + ($retransmissions ?? self::RETRY_MAX);
    }

    /**
     * Handle the received response.
     *
     * @param MessageInterface $message The response message.
     * @param InternetAddress|null $address The source address.
     */
    #[\Override]
    public function responseReceived(MessageInterface $message, ?InternetAddress $address): void
    {
        if ($this->isResolvedOrReject) {
            return;
        }

        $this->settle();
        $this->transport->removeTransaction($message->getTransactionId());

        if ($message->getMessageClass() === MessageClass::RESPONSE) {
            $this->deferred->complete([$message, $address]);
        } else {
            $this->deferred->error(new TransactionFailedException($message));
        }
    }

    /**
     * Run the transaction and wait for its response.
     *
     * @return array{MessageInterface, InternetAddress|null} The response and where it came from.
     * @throws TransactionFailedException If the peer answered with an error response.
     * @throws TransactionTimeoutException If no answer arrived before the retries ran out.
     */
    #[\Override]
    public function execute(): array
    {
        $this->trySend();

        return $this->deferred->getFuture()->await();
    }

    /**
     * Retry the transaction.
     */
    private function trySend(): void
    {
        if ($this->tries >= $this->triesMax) {
            $this->settle();
            $this->deferred->error(new TransactionTimeoutException);

            return;
        }

        try {
            $this->transport->sendMessage($this->message, $this->address);
        } catch (\Throwable $e) {
            // A retransmission runs from a timer callback, outside the caller's try/catch, so
            // a send that fails here (typically because the socket was closed while the
            // transaction was still in flight) would otherwise escape as an uncaught event-loop
            // exception. The transaction can no longer complete, so settle it as a timeout and
            // let the awaiting caller handle it like any other unanswered request.
            $this->settle();
            $this->transport->removeTransaction($this->message->getTransactionId());
            $this->deferred->error(new TransactionTimeoutException(previous: $e));

            return;
        }

        $this->timer = EventLoop::delay($this->timeoutDelay, function (): void {
            $this->timer = null;

            if (!$this->isResolvedOrReject) {
                $this->trySend();
            }
        });

        $this->timeoutDelay *= 2.0;
        $this->tries += 1;
    }

    /**
     * Mark the transaction finished and stop any pending retransmission.
     *
     * A timer left armed keeps the event loop alive and would retransmit a request that has
     * already been answered.
     */
    private function settle(): void
    {
        $this->isResolvedOrReject = true;

        if ($this->timer !== null) {
            EventLoop::cancel($this->timer);
            $this->timer = null;
        }
    }

    /**
     * @return DeferredFuture
     */
    public function getDeferred(): DeferredFuture
    {
        return $this->deferred;
    }
}
