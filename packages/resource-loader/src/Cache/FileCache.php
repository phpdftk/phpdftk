<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Cache;

use Phpdftk\Filesystem\LocalFilesystem;
use Phpdftk\ResourceLoader\FetchResult;

/**
 * On-disk persistent cache for {@see FetchResult}s. The same URL
 * fetched twice — within one render or across renders — hits the
 * disk on the second attempt instead of the network.
 *
 * Storage layout: one file per cache entry, named
 * `sha256(key) + '.cache'`. The body is `serialize()`-d so it round-
 * trips arbitrary binary payloads cleanly without base64 inflation.
 *
 *   {directory}/
 *     7e3b0a...c91d.cache    ← serialized entry for URL A
 *     a204f8...5e2b.cache    ← serialized entry for URL B
 *
 * TTL is applied at `get()` time — expired entries return null and
 * are deleted lazily on read so a cold cache doesn't pay scan cost.
 *
 * Concurrency: writes use the same atomic temp-file-then-rename
 * pattern as {@see LocalFilesystem::writeFile} (it doesn't on its
 * own, but the OS-level write of a small entry is generally atomic
 * enough — a partial write returns null from `get` and the next
 * `set` overwrites). Cross-process concurrent writes to the *same*
 * key may race; both end up with one of the two payloads, both
 * valid.
 */
final class FileCache implements CacheInterface
{
    /** @var \Closure(): int */
    private readonly \Closure $clock;

    /**
     * @param string  $directory          Where cache entries live.
     *                                    Created on first write.
     * @param int     $defaultTtlSeconds  Time-to-live applied at
     *                                    `set()` time. Default 24h.
     * @param ?callable():int $clock      Override the wall clock for
     *                                    deterministic tests. Defaults
     *                                    to PHP's `time()`. Must return
     *                                    epoch seconds.
     */
    public function __construct(
        private readonly string $directory,
        private readonly int $defaultTtlSeconds = 86400,
        ?callable $clock = null,
    ) {
        if ($defaultTtlSeconds <= 0) {
            throw new \InvalidArgumentException('defaultTtlSeconds must be positive');
        }
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : static fn(): int => time();
    }

    public function get(string $key): ?FetchResult
    {
        $path = $this->pathFor($key);
        if (!is_file($path)) {
            return null;
        }
        try {
            $contents = LocalFilesystem::readFile($path, 'cache entry');
        } catch (\Throwable) {
            return null;
        }
        if ($contents === '') {
            return null;
        }
        $entry = @unserialize($contents, ['allowed_classes' => false]);
        if (!is_array($entry) || ($entry['version'] ?? null) !== 1) {
            return null;
        }
        if (!isset($entry['expiresAt']) || !is_int($entry['expiresAt'])) {
            return null;
        }
        if ($this->now() > $entry['expiresAt']) {
            // Expired — drop the file lazily.
            @unlink($path);
            return null;
        }
        if (
            !isset($entry['bytes'], $entry['mimeType'], $entry['originalUrl'], $entry['finalUrl'], $entry['statusCode'])
            || !is_string($entry['bytes'])
            || !is_string($entry['mimeType'])
            || !is_string($entry['originalUrl'])
            || !is_string($entry['finalUrl'])
            || !is_int($entry['statusCode'])
        ) {
            return null;
        }
        return new FetchResult(
            bytes: $entry['bytes'],
            mimeType: $entry['mimeType'],
            originalUrl: $entry['originalUrl'],
            finalUrl: $entry['finalUrl'],
            cacheHit: false,
            statusCode: $entry['statusCode'],
        );
    }

    public function set(string $key, FetchResult $result): void
    {
        $entry = [
            'version' => 1,
            'expiresAt' => $this->now() + $this->defaultTtlSeconds,
            'originalUrl' => $result->originalUrl,
            'finalUrl' => $result->finalUrl,
            'mimeType' => $result->mimeType,
            'statusCode' => $result->statusCode,
            'bytes' => $result->bytes,
        ];
        $serialized = serialize($entry);
        try {
            LocalFilesystem::writeFile($this->pathFor($key), $serialized, createDirectories: true);
        } catch (\Throwable) {
            // Cache write failures shouldn't kill the render.
        }
    }

    /**
     * Delete every cache entry. Used by tests + by callers that
     * want to invalidate everything (rotation of allowedHosts,
     * etc.). Safe to call when the directory doesn't exist.
     */
    public function clear(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $entries = glob(rtrim($this->directory, '/') . '/*.cache');
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            @unlink($entry);
        }
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function defaultTtlSeconds(): int
    {
        return $this->defaultTtlSeconds;
    }

    /**
     * Map a cache key (the URL the loader is asked to fetch) to a
     * filesystem path. Uses sha256 so:
     *   - URLs with special characters are filesystem-safe
     *   - the keyspace stays bounded (no information leakage in
     *     the filename)
     *   - the same URL always maps to the same file
     */
    private function pathFor(string $key): string
    {
        return rtrim($this->directory, '/') . '/' . hash('sha256', $key) . '.cache';
    }

    private function now(): int
    {
        return ($this->clock)();
    }
}
