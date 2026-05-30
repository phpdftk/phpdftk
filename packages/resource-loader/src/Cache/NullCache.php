<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Cache;

use Phpdftk\ResourceLoader\FetchResult;

/**
 * No-op cache backend. Every `get()` returns null (cache miss) and
 * every `set()` discards the result.
 *
 * Default when callers don't supply a cache explicitly — keeps the
 * loader stateless and side-effect-free. Use a real backend
 * (`FileCache`, `MemoryCache`) when fetching the same URL twice in
 * one render is plausible.
 */
final class NullCache implements CacheInterface
{
    public function get(string $key): ?FetchResult
    {
        unset($key);
        return null;
    }

    public function set(string $key, FetchResult $result): void
    {
        unset($key, $result);
    }
}
