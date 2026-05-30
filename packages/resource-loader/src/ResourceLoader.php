<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader;

use Phpdftk\ResourceLoader\Cache\CacheInterface;
use Phpdftk\ResourceLoader\Cache\NullCache;

/**
 * URL → bytes resolver with SSRF guard, caching, and bounded
 * retry / redirect handling.
 *
 * Phase 4F scaffold. The HTTP fetcher itself, MIME sniffing, the
 * file-backed cache, and the redirect handling all land in
 * sub-phases 4F.1–4F.4. The package shape and the public types are
 * stable from this scaffold so the html-to-pdf + svg-to-pdf
 * call sites can be written against them now.
 *
 * Wiring (Phase 4F.5):
 *
 *   svg-to-pdf  — Translator::paintImage hands `http(s)://` and
 *                 external filesystem hrefs to the loader instead
 *                 of dropping them silently.
 *   html-to-pdf — `<img>`, `<picture>`, `<video poster>`, `<iframe
 *                 src>`, `<object data>` all route through the
 *                 loader. `@font-face url()` and `@import url()`
 *                 do too.
 */
final class ResourceLoader
{
    public function __construct(
        private readonly CacheInterface $cache = new NullCache(),
        private readonly SsrfGuard $ssrfGuard = new SsrfGuard(),
        private readonly FetchOptions $defaultOptions = new FetchOptions(),
    ) {}

    /**
     * Fetch the resource at `$url`. Throws
     * {@see Exception\SsrfBlockedException} when the URL violates
     * the SSRF policy, {@see Exception\FetchFailedException} for
     * everything else.
     *
     * Phase 4F.1 implements the actual HTTP path. The SSRF gate is
     * already live — the scaffold runs the guard so call sites get
     * the immediate safety benefit even before 4F.1 lands.
     */
    public function fetch(string $url, ?FetchOptions $options = null): FetchResult
    {
        $this->ssrfGuard->assertSafe($url);
        unset($options);
        throw new \RuntimeException('4F.1 not yet implemented');
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
}
