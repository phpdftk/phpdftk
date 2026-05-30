<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Transport;

use Phpdftk\ResourceLoader\Exception\FetchFailedException;

/**
 * One-shot HTTP transport — given a URL, headers, and a timeout,
 * return one {@see RawResponse} or throw.
 *
 * The transport does NOT follow redirects, enforce content-length,
 * or re-check SSRF against the resolved IP — those are the
 * fetcher's job. Splitting the concerns lets the fetcher's
 * orchestration be tested against a fake transport without needing
 * a live HTTP server.
 *
 * Implementations:
 *
 *   - {@see CurlTransport}  — curl-based production transport.
 *   - tests/`FakeTransport` — in-test mock that returns canned
 *                             responses; used by HttpFetcherTest
 *                             so the redirect / cap / SSRF re-check
 *                             logic can be exercised without
 *                             network access.
 */
interface TransportInterface
{
    /**
     * Send a single HTTP request and return the response.
     *
     * @param string                $url            Absolute URL.
     * @param array<string, string> $headers        Header name (canonical case) → value.
     * @param int                   $timeoutSeconds Total request budget.
     * @param int                   $maxBodyBytes   Abort if the
     *                                              response body
     *                                              exceeds this
     *                                              (transport-level
     *                                              defence; the
     *                                              fetcher also
     *                                              enforces).
     * @throws FetchFailedException on connect / DNS / timeout / non-
     *                              parseable-response failure.
     */
    public function send(
        string $url,
        array $headers,
        int $timeoutSeconds,
        int $maxBodyBytes,
    ): RawResponse;
}
