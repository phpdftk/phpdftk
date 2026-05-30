<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader;

/**
 * Successful fetch result. Immutable.
 *
 * `bytes` is the raw response body. `mimeType` is the sniffed type
 * (`Content-Type` is treated as a hint, not authoritative — we sniff
 * the bytes the same way browsers do for `<image>`, `<img>`, `<font>`,
 * `<style>`). `originalUrl` is what the caller asked for; `finalUrl`
 * is the post-redirect URL the body actually came from.
 */
final readonly class FetchResult
{
    /**
     * @param string $bytes        Raw response body.
     * @param string $mimeType     Sniffed MIME (e.g. `image/png`).
     * @param string $originalUrl  URL passed to `fetch()`.
     * @param string $finalUrl     URL after redirect chain.
     * @param bool   $cacheHit     True if the body came from cache.
     * @param int    $statusCode   HTTP status of the final response.
     */
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public string $originalUrl,
        public string $finalUrl,
        public bool $cacheHit,
        public int $statusCode,
    ) {}
}
