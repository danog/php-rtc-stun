# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- Use `Amp\Socket\InternetAddress` for all socket endpoint parameters, return values, callbacks, transactions, and address-valued STUN attributes.
- Pass address objects directly to Amp sockets instead of formatting and re-parsing strings.

## [1.0.0] - 2025-05-13

### Added
- First release
