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

use Amp\Socket\BindContext;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceUdpSocket;
use Amp\Socket\UdpSocket;
use Throwable;
use Webrtc\Exception\InvalidArgumentException;
use Webrtc\Mixin\SerializableState;
use function Amp\async;
use function Amp\Socket\bindUdpSocket;

/**
 * User Datagram Protocol
 *
 * Handles the UDP communication for WebRTC.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc768 User Datagram Protocol
 */
abstract class Datagram extends BaseProtocol
{
    /** The default remote address for communication. */
    protected ?InternetAddress $remoteAddress = null;

    /** Whether the receive loop should keep delivering datagrams. */
    private bool $paused = false;

    /**
     * Datagram constructor.
     *
     * @param UdpSocket $socket The socket to use for UDP communication
     */
    public function __construct(protected UdpSocket $socket)
    {
        $this->listen();
    }

    /**
     * Bind context shared by every UDP socket a connection opens.
     *
     * SO_REUSEPORT (mapped to SO_REUSEADDR on Windows) lets a deserialized connection rebind
     * to the same local port while the original socket still holds it — the exact situation a
     * serialize/unserialize cycle produces. Without it macOS and Windows reject the second bind
     * with "Address already in use"; Linux happens to tolerate it, which is why the gap only
     * showed on those platforms. Both the original and the rebind must set it for the kernel to
     * allow the shared port, so every bind in the package routes through here.
     */
    protected static function udpBindContext(): BindContext
    {
        return (new BindContext())->withReusePort();
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $address = $this->socket->getAddress();

        return SerializableState::export($this, [
            'socket' => ['_udp' => [$address->getAddress(), $address->getPort()]],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $bindHost = null;
        $bindPort = null;
        foreach ($data as $key => $value) {
            if (!is_array($value) || !isset($value['_udp']) || !is_array($value['_udp'])) {
                continue;
            }
            $udp = $value['_udp'];
            if (!isset($udp[0], $udp[1])) {
                continue;
            }
            $port = (int) $udp[1];
            if ($port < 0 || $port > 65535) {
                continue;
            }
            $bindHost = (string) $udp[0];
            $bindPort = $port;
            unset($data[$key]);
        }
        SerializableState::import($this, $data);
        if ($bindHost !== null && $bindPort !== null) {
            $this->socket = bindUdpSocket(new InternetAddress($bindHost, $bindPort), self::udpBindContext());
            $this->listen();
        }
    }

    /**
     * Deliver incoming datagrams to onReceived() until the socket closes.
     *
     * Reading runs in its own fiber rather than through an event emitter: the socket hands
     * over one datagram per receive() call, so the loop is the natural shape and errors
     * propagate to onError() instead of an unobserved rejection.
     */
    protected function listen(): void
    {
        // The receive loop must not strongly reference $this, or the fiber the event loop keeps
        // alive to run it would pin this connection forever — an unset() followed by
        // gc_collect_cycles() could never reclaim it, and its bound port would leak. It holds the
        // socket (so it stays parked in receive()) and only a weak reference back to the owner, so
        // dropping the last real reference lets the connection be collected; __destruct() then
        // closes the socket, which unblocks receive() and lets this fiber unwind.
        $weak = \WeakReference::create($this);
        $socket = $this->socket;
        async(static function () use ($weak, $socket): void {
            try {
                while (true) {
                    $received = $socket->receive();
                    if ($received === null) {
                        // receive() === null covers two very different events. A genuine close nulls
                        // the underlying resource (isClosed() === true) and ends the loop. On Windows,
                        // though, a UDP send that draws an ICMP port-unreachable — a dead STUN/TURN
                        // server, or a peer whose port is not open yet — makes the socket's *next* recv
                        // fail once with WSAECONNRESET; amphp reports that one-shot error as
                        // receive() === null and cancels its read watcher, even though the socket is
                        // still bound and usable. Ending the loop there would tear down the only host
                        // candidate mid-negotiation (missing srflx candidates, "Binding check failed").
                        // Instead re-wrap the still-open resource in a fresh socket, which arms a new
                        // read watcher, and keep reading. POSIX never reaches this branch: it surfaces
                        // ICMP errors only on connected sockets, and these are unconnected, so recv
                        // returns null there only on a real close.
                        if ($socket->isClosed() || !$socket instanceof ResourceUdpSocket) {
                            break;
                        }
                        $resource = $socket->getResource();
                        if (!\is_resource($resource)) {
                            break;
                        }
                        $socket = new ResourceUdpSocket($resource);
                        $self = $weak->get();
                        if ($self === null) {
                            $socket->close();

                            return;
                        }
                        $self->socket = $socket;
                        unset($self);
                        continue;
                    }

                    $self = $weak->get();
                    if ($self === null) {
                        $socket->close();

                        return;
                    }
                    [$address, $data] = $received;

                    if ($self->paused) {
                        continue;
                    }
                    // Dispatch in its own fiber. Handling a datagram can block — answering a
                    // binding request may itself start a transaction and wait for its reply —
                    // and doing that inline stops this loop from reading, so the very replies
                    // being waited on never arrive and every transaction times out.
                    async(static fn () => $weak->get()?->onReceived($data, $address))->ignore();
                    unset($self);
                }

                $weak->get()?->onClose();
            } catch (Throwable $e) {
                $weak->get()?->onError($e);
            }
        });
    }

    /**
     * Release the bound socket when the connection is garbage-collected.
     *
     * The receive loop parks a fiber inside the socket's receive() call; closing the socket here
     * is what unblocks it so the fiber can unwind once the last reference to this connection is
     * gone. Closing a UDP socket sends nothing on the wire, so a peer sees only silence — exactly
     * the "the process went away" situation a serialize/unserialize cycle is meant to survive.
     */
    public function __destruct()
    {
        // socket is always set here: the constructor binds it, a successful unserialize rebinds
        // it, and __destruct never runs on an object whose __unserialize threw.
        $this->socket->close();
    }

    /**
     * Send data through the UDP socket
     *
     * @param string $data The data to send
     * @param InternetAddress|null $remoteAddress The target address (uses default remote address if null)
     * @return void
     */
    #[\Override]
    public function send(string $data, ?InternetAddress $remoteAddress = null): void
    {
        $remoteAddress ??= $this->remoteAddress;
        if ($remoteAddress === null) {
            throw new InvalidArgumentException('A remote address must be provided');
        }

        $this->socket->send($remoteAddress, $data);
    }

    /**
     * Close the UDP socket immediately
     *
     * @return void
     */
    #[\Override]
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
    #[\Override]
    public function end(): void
    {
        $this->close();
    }

    /**
     * Resume delivering received datagrams
     *
     * @return void
     */
    #[\Override]
    public function resume(): void
    {
        $this->paused = false;
    }

    /**
     * Stop delivering received datagrams
     *
     * @return void
     */
    #[\Override]
    public function pause(): void
    {
        $this->paused = true;
    }

    /**
     * Get the local socket address
     *
     * @return InternetAddress The local socket address
     */
    #[\Override]
    public function getLocalAddress(): InternetAddress
    {
        return $this->socket->getAddress();
    }

    /**
     * Get the local host address
     *
     * @return string The local host IP address
     */
    #[\Override]
    public function getLocalHost(): string
    {
        return $this->getLocalAddress()->getAddress();
    }

    /**
     * Get the local port number
     *
     * @return int The local port number
     */
    #[\Override]
    public function getLocalPort(): int
    {
        return $this->getLocalAddress()->getPort();
    }

    /**
     * Get the remote address
     *
     * @return InternetAddress|null The remote address or null if not connected
     */
    #[\Override]
    public function getRemoteAddress(): ?InternetAddress
    {
        return $this->remoteAddress ?? null;
    }

    /**
     * Handle received messages (abstract method to be implemented by concrete classes)
     *
     * @param string $data The received data
     * @param InternetAddress $peerAddress The address of the peer that sent the data
     * @return void
     */
    protected abstract function onReceived(string $data, InternetAddress $peerAddress): void;

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

}
