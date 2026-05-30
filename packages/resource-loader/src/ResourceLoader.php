<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader;

use Phpdftk\ResourceLoader\Cache\CacheInterface;
use Phpdftk\ResourceLoader\Cache\NullCache;

/**
 * URL → bytes resolver with SSRF guard, caching, and bounded
 * retry / redirect handling.
 *
 * Wiring stack:
 *
 *   ResourceLoader  →  cache lookup
 *                  →  HttpFetcher
 *                       →  SsrfGuard (pre-flight + per redirect hop)
 *                       →  TransportInterface (curl by default)
 *                       →  MimeSniffer
 *                  →  cache store
 */
final class ResourceLoader
{
    private readonly HttpFetcher $fetcher;

    public function __construct(
        private readonly CacheInterface $cache = new NullCache(),
        private readonly SsrfGuard $ssrfGuard = new SsrfGuard(),
        private readonly FetchOptions $defaultOptions = new FetchOptions(),
        ?HttpFetcher $fetcher = null,
    ) {
        $this->fetcher = $fetcher ?? new HttpFetcher(ssrfGuard: $this->ssrfGuard);
    }

    /**
     * Fetch the resource at `$url`. Throws
     * {@see Exception\SsrfBlockedException} when the URL violates
     * the SSRF policy, {@see Exception\FetchFailedException} for
     * everything else.
     *
     * Cache lookup is keyed by the requested URL (not the final
     * post-redirect URL) — same URL means same result for the
     * caller, regardless of how the server chose to redirect.
     */
    public function fetch(string $url, ?FetchOptions $options = null): FetchResult
    {
        $resolvedOptions = $options ?? $this->defaultOptions;

        $this->ssrfGuard->assertSafe($url);

        $cached = $this->cache->get($url);
        if ($cached !== null) {
            return new FetchResult(
                bytes: $cached->bytes,
                mimeType: $cached->mimeType,
                originalUrl: $cached->originalUrl,
                finalUrl: $cached->finalUrl,
                cacheHit: true,
                statusCode: $cached->statusCode,
            );
        }

        $result = $this->fetcher->fetch($url, $resolvedOptions);
        $this->cache->set($url, $result);
        return $result;
    }

    public function cache(): CacheInterface
    {
        return $this->cache;
    }

    public function ssrfGuard(): SsrfGuard
    {
        return $this->ssrfGuard;
    }

    public function defaultOptions(): FetchOptions
    {
        return $this->defaultOptions;
    }

    public function fetcher(): HttpFetcher
    {
        return $this->fetcher;
    }
}
