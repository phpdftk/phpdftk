<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Exception;

/**
 * Thrown by {@see \Phpdftk\ResourceLoader\SsrfGuard::assertSafe} when
 * a URL violates the SSRF policy — unsafe scheme, empty host, host
 * resolves to a literal private / loopback / link-local / multicast /
 * reserved IP, or is otherwise outside the configured allowlist.
 *
 * Callers may want to surface the message verbatim in error
 * responses since it documents the exact policy violation.
 */
final class SsrfBlockedException extends \RuntimeException {}
