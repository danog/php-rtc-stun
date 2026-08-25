# STUN Library for PHP

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A PHP library for STUN (Session Traversal Utilities for NAT) protocol, enabling NAT discovery and reflexive address retrieval for ICE connectivity in WebRTC applications.

## About this fork

This is the `danog/php-rtc-stun` fork used by MadelineProto. It targets PHP 8.2+ and replaces ReactPHP with Amp v3 UDP sockets, blocking fiber APIs, and Revolt retransmission timers. Receive handlers run in separate fibers to avoid deadlocks, and IPv6 peer addresses are normalized for ICE validation.

The forked stack keeps the upstream `quasarstream/*` dependency constraints for compatibility. Each `danog/php-rtc-*` package replaces its upstream counterpart, so consumers select the complete maintained stack by requiring the corresponding danog packages together.

##  Features

- Encode and decode STUN messages
- Support for Binding requests and responses
- Implements STUN message integrity and fingerprinting
- Compatible with ICE and TURN components

## Requirements

- PHP ≥ 8.2

## Documentation

This package is part of the PHP WebRTC library. For complete documentation, examples, and API reference, visit:

[PHP WebRTC Documentation](https://www.quasarstream.com/php-webrtc)

## Credits

### Authors

- **Amin Yazdanpanah**  
  - Website: [aminyazdanpanah.com](https://www.aminyazdanpanah.com)
  - Email: [github@aminyazdanpanah.com](mailto:github@aminyazdanpanah.com)

- **Sana Moniri**  
  - GtiHub: [sanamoniri](https://github.com/sanamoniri)

## Reporting Issues

Found a bug? Please report it on our [issues](https://github.com/php-webrtc/stun/issues).

## License

BSD 3-Clause License. See [LICENSE](LICENSE) for details.

## References

- [RFC 5389 – Session Traversal Utilities for NAT (STUN)](https://datatracker.ietf.org/doc/html/rfc5389)
