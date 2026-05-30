<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader;

/**
 * Per-fetch policy: timeout, redirect chain length, max payload size,
 * extra headers, override user-agent.
 *
 * Immutable; instantiate once and pass to {@see ResourceLoader::fetch}.
 * Sensible defaults are tuned for the most common case (embedding a
 * remote image in a PDF) — bump `maxContentLengthBytes` if you
 * expect large fonts or hi-res images.
 */
final readonly class FetchOptions
{
    /**
     * @param int                     $timeoutSeconds          Hard
     *                                                          ceiling
     *                                                          on the
     *                                                          whole
     *                                                          fetch
     *                                                          (connect
     *                                                          + read).
     * @param int                     $maxRedirects            Stop
     *                                                          following
     *                                                          redirects
     *                                                          past
     *                                                          this
     *                                                          depth.
     * @param int                     $maxContentLengthBytes   Reject
     *                                                          responses
     *                                                          declaring
     *                                                          or
     *                                                          streaming
     *                                                          more
     *                                                          than
     *                                                          this
     *                                                          number
     *                                                          of bytes.
     *                                                          50 MB
     *                                                          default
     *                                                          covers
     *                                                          large
     *                                                          PNG /
     *                                                          font
     *                                                          files
     *                                                          without
     *                                                          exposing
     *                                                          the
     *                                                          host
     *                                                          to
     *                                                          memory
     *                                                          exhaustion.
     * @param string                  $userAgent               Sent as
     *                                                          `User-Agent:`.
     * @param array<string, string>   $headers                 Extra
     *                                                          request
     *                                                          headers
     *                                                          (e.g.
     *                                                          `Accept`,
     *                                                          `Accept-Language`).
     *                                                          Authorization
     *                                                          headers
     *                                                          should
     *                                                          NOT
     *                                                          be set
     *                                                          here —
     *                                                          the
     *                                                          loader
     *                                                          strips
     *                                                          them
     *                                                          across
     *                                                          redirects
     *                                                          per
     *                                                          RFC 9110
     *                                                          §15.4.
     */
    public function __construct(
        public int $timeoutSeconds = 10,
        public int $maxRedirects = 5,
        public int $maxContentLengthBytes = 50_000_000,
        public string $userAgent = 'phpdftk-resource-loader/1.0',
        public array $headers = [],
    ) {}
}
