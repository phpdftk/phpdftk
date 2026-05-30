<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Exception;

/**
 * Thrown when a fetch fails for a reason other than SSRF policy —
 * timeout, network error, non-2xx HTTP response, content-length cap
 * exceeded, redirect chain too long, etc.
 *
 * Phase 4F.1 — the HTTP fetcher distinguishes the failure modes via
 * exception subclasses; this is the umbrella type.
 */
class FetchFailedException extends \RuntimeException {}
