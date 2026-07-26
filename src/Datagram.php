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
use Amp\Socket\Socket;
use Amp\Socket\UdpSocket;
use Throwable;
use function Amp\async;

/**
 * User Datagram Protocol
 *
 * Handles the UDP communication for WebRTC.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc768 User Datagram Protocol
 */
abstract class Datagram extends BaseProtocol
{
    /** @var string The remote address for communication */
    protected string $remoteAddress;

    /** @var string The local host address */
    private string $localHost;

    /** @var int The local port number */
    private int $localPort;

    /** Whether the receive loop should keep delivering datagrams. */
    private bool $paused = false;

    /**
     * Datagram constructor.
     *
     * @param UdpSocket $socket The socket to use for UDP communication
     */
    public function __construct(protected UdpSocket $socket)
    {
        $this->parseLocalAddress();
        $this->listen();
    }

    /**
     * Deliver incoming datagrams to onReceived() until the socket closes.
     *
     * Reading runs in its own fiber rather than through an event emitter: the socket hands
     * over one datagram per receive() call, so the loop is the natural shape and errors
     * propagate to onError() instead of an unobserved rejection.
     */
    private function listen(): void
    {
        async(function (): void {
            try {
                while (($received = $this->socket->receive()) !== null) {
                    [$address, $data] = $received;

                    if ($this->paused) {
                        continue;
                    }

                    $this->onReceived($data, (string) $address);
                }

                $this->onClose();
            } catch (Throwable $e) {
                $this->onError($e);
            }
        });
    }

    /**
     * Send data through the UDP socket
     *
     * @param string $data The data to send
     * @param string|null $remoteAddress The target address (uses default remote address if null)
     * @return void
     */
    public function send(string $data, ?string $remoteAddress = null): void
    {
        $this->socket->send(
            InternetAddress::fromString($this->stripScheme($remoteAddress ?? $this->remoteAddress)),
            $data
        );
    }

    /**
     * Close the UDP socket immediately
     *
     * @return void
     */
    public function close(): void
    {
        $this->socket->close();
    }

    /**
     * Close the UDP socket.
     *
     * A datagram socket has no write buffer to drain, so this is the same as close(); it is
     * kept because callers written against the previous stream-shaped API still use it.
     *
     * @return void
     */
    public function end(): void
    {
        $this->close();
    }

    /**
     * Resume delivering received datagrams
     *
     * @return void
     */
    public function resume(): void
    {
        $this->paused = false;
    }

    /**
     * Stop delivering received datagrams
     *
     * @return void
     */
    public function pause(): void
    {
        $this->paused = true;
    }

    /**
     * Get the local socket address
     *
     * @return string The local address in "host:port" format
     */
    public function getLocalAddress(): string
    {
        return (string) $this->socket->getAddress();
    }

    /**
     * Get the local host address
     *
     * @return string The local host IP address
     */
    public function getLocalHost(): string
    {
        return $this->localHost;
    }

    /**
     * Get the local port number
     *
     * @return int The local port number
     */
    public function getLocalPort(): int
    {
        return $this->localPort;
    }

    /**
     * Get the remote address
     *
     * @return string|null The remote address in "host:port" format or null if not connected
     */
    public function getRemoteAddress(): ?string
    {
        return $this->remoteAddress ?? null;
    }

    /**
     * Handle received messages (abstract method to be implemented by concrete classes)
     *
     * @param string $data The received data
     * @param string $peerAddress The address of the peer that sent the data
     * @return void
     */
    protected abstract function onReceived(string $data, string $peerAddress): void;

    /**
     * Handle socket errors (abstract method to be implemented by concrete classes)
     *
     * @param Throwable $e The thrown exception
     * @return void
     */
    protected abstract function onError(Throwable $e): void;

    /**
     * Handle socket close events (abstract method to be implemented by concrete classes)
     *
     * @return void
     */
    protected abstract function onClose(): void;

    /**
     * Parse and store the local address components (host and port)
     *
     * @return void
     */
    private function parseLocalAddress(): void
    {
        $address = $this->socket->getAddress();

        $this->localHost = $address->getAddress();
        $this->localPort = $address->getPort();
    }

    /**
     * Addresses are passed around as plain "host:port" here, but callers sometimes carry the
     * scheme along, and InternetAddress will not parse it.
     */
    private function stripScheme(string $address): string
    {
        $separator = strpos($address, '://');

        return $separator === false ? $address : substr($address, $separator + 3);
    }
}
