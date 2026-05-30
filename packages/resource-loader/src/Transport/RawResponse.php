<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Transport;

/**
 * The transport layer's return value — one HTTP response with no
 * orchestration applied. The {@see \Phpdftk\ResourceLoader\HttpFetcher}
 * walks a chain of these (one per redirect hop), applies the
 * content-length cap, strips Authorization across host changes, and
 * sniffs the final body's MIME type.
 *
 * `headers` is normalised: keys are lowercased; values are kept as-
 * is so callers can re-emit the exact server bytes if needed.
 */
final readonly class RawResponse
{
    /**
     * @param int $statusCode    HTTP status (200, 301, 404, …).
     * @param array<string, string> $headers Lowercase header name → first value.
     * @param string $body       Response body bytes. Empty for HEAD
     *                            responses or for redirect hops where
     *                            the transport elected not to read
     *                            the body.
     * @param string $finalUrl   The URL the response came from after
     *                            any transport-level redirect
     *                            following. The fetcher handles
     *                            redirects itself for SSRF re-check,
     *                            so transports SHOULD set this equal
     *                            to the requested URL and let the
     *                            fetcher follow.
     */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
        public string $finalUrl,
    ) {}
}
