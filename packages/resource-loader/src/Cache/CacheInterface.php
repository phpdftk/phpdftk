<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Cache;

use Phpdftk\ResourceLoader\FetchResult;

/**
 * Persistent cache for `FetchResult`s, keyed by canonicalised URL.
 *
 * Implementations:
 *
 *   - {@see NullCache}  — no caching, every fetch hits the network
 *   - `FileCache`       — on-disk cache with TTL (Phase 4F.2)
 *   - `MemoryCache`     — per-request in-memory cache (4F.2)
 *
 * The cache contract is intentionally narrow — get / set — so the
 * loader stays implementation-agnostic. Cache invalidation, TTL,
 * eviction policy, and key canonicalisation are all the backend's
 * responsibility.
 */
interface CacheInterface
{
    /**
     * Return the cached result for `$key` if any, or null. The
     * loader treats null as a cache miss and proceeds to fetch.
     */
    public function get(string $key): ?FetchResult;

    /**
     * Store a result. Implementations may compress, hash, or
     * otherwise transform the body — the contract is "given this
     * key in the future, `get()` returns an equivalent result" but
     * the bytes returned need not be identical to those stored.
     */
    public function set(string $key, FetchResult $result): void;
}
