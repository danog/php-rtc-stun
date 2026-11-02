<?php

declare(strict_types=1);

namespace Webrtc\STUN;

use Amp\Socket\InternetAddress;
use Fiber;
use SplQueue;
use Throwable;

/**
 * Hands datagrams to {@see Datagram::onReceived()}, in order, in fibers kept for the next datagrams, instead of
 * a new fiber (with its future) for each one.
 *
 * A handler can suspend (waiting for a STUN transaction, a full queue): the receive loop must keep reading
 * meanwhile, and the next datagrams can't wait for it, as one of them may be what it waits for. So the receive
 * loop resumes the fiber directly: if the handler suspends, control comes back to the receive loop right away,
 * as when the fiber finishes the datagram, and the next datagram, finding every fiber suspended in a handler,
 * starts another one. When a fiber has nothing left to handle, it waits for the next datagram, unless another
 * fiber already waits: then it ends.
 *
 * @internal
 */
final class DatagramDispatcher
{
    /** @var SplQueue<array{string, InternetAddress}> */
    private SplQueue $queue;
    /** The fiber waiting for datagrams (with Fiber::suspend()), if any. */
    private ?Fiber $idle = null;
    /** How many fibers there are. */
    private int $fibers = 0;
    /** How many fibers are in a handler: outside of these fibers, they're suspended there. */
    private int $handling = 0;
    private bool $stopped = false;

    /** @param \Closure(string, InternetAddress): void $handler Calls {@see Datagram::onReceived()}. */
    public function __construct(private readonly \Closure $handler)
    {
        $this->queue = new SplQueue;
    }

    /**
     * Handle a datagram, after the ones before it: returns when it's handled, or when its handler suspends.
     *
     * Not to be called from a handler.
     */
    public function dispatch(string $data, InternetAddress $address): void
    {
        $this->queue->enqueue([$data, $address]);
        if ($this->idle !== null) {
            $idle = $this->idle;
            $this->idle = null;
            $idle->resume(true);
        } elseif ($this->fibers === $this->handling) {
            // No fiber, or all of them suspended in a handler.
            $this->fibers++;
            (new Fiber($this->run(...)))->start();
        }
        // Else, a fiber suspended in a handler was resumed, and will handle the queue when done.
    }

    /**
     * Stop waiting for datagrams: the socket was closed.
     */
    public function stop(): void
    {
        $this->stopped = true;
        if ($this->idle !== null) {
            $idle = $this->idle;
            $this->idle = null;
            $idle->resume(false);
        }
    }

    private function run(): void
    {
        try {
            do {
                while (!$this->queue->isEmpty()) {
                    [$data, $address] = $this->queue->dequeue();
                    $this->handling++;
                    try {
                        ($this->handler)($data, $address);
                    } catch (Throwable) {
                        // As with a fiber for each datagram, whose errors were ignored: one malformed
                        // datagram doesn't stop the others.
                    } finally {
                        $this->handling--;
                    }
                }
                if ($this->idle !== null || $this->stopped) {
                    // Another fiber waits already.
                    return;
                }
                $this->idle = Fiber::getCurrent();
            } while (Fiber::suspend() === true);
        } finally {
            $this->fibers--;
        }
    }
}
