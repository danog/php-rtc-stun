<?php

namespace Tests\Webrtc\STUN;

use Amp\Socket\InternetAddress;
use Amp\Socket\UdpSocket;
use Webrtc\Exception\RuntimeException;
use Webrtc\STUN\Datagram;
use function Amp\Socket\bindUdpSocket;

class EchoServer extends Datagram
{
    public function __construct(UdpSocket $socket)
    {
        parent::__construct($socket);
    }

    protected function onReceived(string $data, InternetAddress $peerAddress): void
    {
        $this->send($data, $peerAddress);
    }

    protected function onError(\Throwable $e): void
    {
    }

    protected function onClose(): void
    {
    }

    public static function create(?InternetAddress $address = null): self
    {
        $address ??= new InternetAddress('127.0.0.1', 0);

        try {
            $socket = bindUdpSocket($address);
            return new static($socket);
        } catch (\Throwable $e) {
            throw new RuntimeException(sprintf("Could not bind to %s - %s", $address, $e->getMessage()), $e->getCode(), $e);
        }
    }
}
