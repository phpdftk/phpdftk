<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Tests;

use Phpdftk\ResourceLoader\Cache\FileCache;
use Phpdftk\ResourceLoader\FetchResult;
use PHPUnit\Framework\TestCase;

/**
 * 4F.2 — FileCache. The cache is the only persistent state in the
 * loader, so the tests focus on correctness: round-trip fidelity,
 * TTL expiry, miss for unknown keys, dir auto-creation, deletion.
 */
final class FileCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phpdftk-cache-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            $entries = glob($this->dir . '/*') ?: [];
            foreach ($entries as $entry) {
                @unlink($entry);
            }
            @rmdir($this->dir);
        }
    }

    private function sampleResult(): FetchResult
    {
        // Include a NUL byte so the serialize round-trip is
        // exercised against truly binary content (not just ASCII).
        return new FetchResult(
            bytes: "\x89PNG\r\n\x1a\n" . "\x00\x00\x00\x0Dbinary",
            mimeType: 'image/png',
            originalUrl: 'https://example.com/asset.png',
            finalUrl: 'https://cdn.example.com/asset.png',
            cacheHit: false,
            statusCode: 200,
        );
    }

    // -----------------------------------------------------------------------
    // Round-trip
    // -----------------------------------------------------------------------

    public function testGetReturnsNullForMissingKey(): void
    {
        $cache = new FileCache($this->dir);
        self::assertNull($cache->get('https://example.com/nothing'));
    }

    public function testSetThenGetRoundTripsFetchResult(): void
    {
        $cache = new FileCache($this->dir);
        $original = $this->sampleResult();
        $cache->set('https://example.com/x', $original);

        $fetched = $cache->get('https://example.com/x');
        self::assertNotNull($fetched);
        self::assertSame($original->bytes, $fetched->bytes);
        self::assertSame($original->mimeType, $fetched->mimeType);
        self::assertSame($original->originalUrl, $fetched->originalUrl);
        self::assertSame($original->finalUrl, $fetched->finalUrl);
        self::assertSame($original->statusCode, $fetched->statusCode);
        // cacheHit isn't stored — the ResourceLoader sets it on read.
        self::assertFalse($fetched->cacheHit);
    }

    public function testSetAutoCreatesDirectory(): void
    {
        self::assertDirectoryDoesNotExist($this->dir);
        $cache = new FileCache($this->dir);
        $cache->set('https://example.com/x', $this->sampleResult());
        self::assertDirectoryExists($this->dir);
    }

    public function testDifferentKeysWriteDifferentFiles(): void
    {
        $cache = new FileCache($this->dir);
        $cache->set('https://example.com/a', $this->sampleResult());
        $cache->set('https://example.com/b', $this->sampleResult());

        $files = glob($this->dir . '/*.cache') ?: [];
        self::assertCount(2, $files);
    }

    public function testKeyHashingHandlesUrlsWithSpecialCharacters(): void
    {
        $cache = new FileCache($this->dir);
        $cache->set('https://example.com/path?q=foo&r=bar#frag', $this->sampleResult());
        $files = glob($this->dir . '/*.cache') ?: [];
        // The filename should be a sha256 hex digest — no special
        // characters from the URL leak into the filesystem.
        self::assertCount(1, $files);
        self::assertMatchesRegularExpression('!/[0-9a-f]{64}\\.cache$!', $files[0]);
    }

    // -----------------------------------------------------------------------
    // TTL
    // -----------------------------------------------------------------------

    public function testEntryWithinTtlReturnsResult(): void
    {
        $time = 1_000_000;
        $cache = new FileCache($this->dir, defaultTtlSeconds: 3600, clock: function () use (&$time): int {
            return $time;
        });
        $cache->set('https://example.com/x', $this->sampleResult());

        $time = 1_000_000 + 3599;
        self::assertNotNull($cache->get('https://example.com/x'));
    }

    public function testEntryAfterTtlReturnsNull(): void
    {
        $time = 1_000_000;
        $cache = new FileCache($this->dir, defaultTtlSeconds: 3600, clock: function () use (&$time): int {
            return $time;
        });
        $cache->set('https://example.com/x', $this->sampleResult());

        $time = 1_000_000 + 3601;
        self::assertNull($cache->get('https://example.com/x'));
    }

    public function testExpiredEntryIsDeletedOnRead(): void
    {
        $time = 1_000_000;
        $cache = new FileCache($this->dir, defaultTtlSeconds: 100, clock: function () use (&$time): int {
            return $time;
        });
        $cache->set('https://example.com/x', $this->sampleResult());

        $files = glob($this->dir . '/*.cache') ?: [];
        self::assertCount(1, $files);

        $time = 1_000_000 + 101;
        self::assertNull($cache->get('https://example.com/x'));

        // Lazy delete on read drops the file.
        $files = glob($this->dir . '/*.cache') ?: [];
        self::assertSame([], $files);
    }

    public function testConstructorRejectsNonPositiveTtl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FileCache($this->dir, defaultTtlSeconds: 0);
    }

    // -----------------------------------------------------------------------
    // Resilience
    // -----------------------------------------------------------------------

    public function testCorruptCacheFileReturnsNull(): void
    {
        $cache = new FileCache($this->dir);
        // Force-write garbage to the path the cache would use.
        @mkdir($this->dir, 0755, true);
        $hash = hash('sha256', 'https://example.com/x');
        file_put_contents($this->dir . '/' . $hash . '.cache', 'not valid serialization');

        // Robust to corruption: returns null instead of throwing.
        self::assertNull($cache->get('https://example.com/x'));
    }

    public function testEmptyCacheFileReturnsNull(): void
    {
        $cache = new FileCache($this->dir);
        @mkdir($this->dir, 0755, true);
        $hash = hash('sha256', 'https://example.com/x');
        file_put_contents($this->dir . '/' . $hash . '.cache', '');

        self::assertNull($cache->get('https://example.com/x'));
    }

    // -----------------------------------------------------------------------
    // clear()
    // -----------------------------------------------------------------------

    public function testClearRemovesAllEntries(): void
    {
        $cache = new FileCache($this->dir);
        $cache->set('a', $this->sampleResult());
        $cache->set('b', $this->sampleResult());
        $cache->set('c', $this->sampleResult());

        self::assertCount(3, glob($this->dir . '/*.cache') ?: []);
        $cache->clear();
        self::assertSame([], glob($this->dir . '/*.cache') ?: []);
    }

    public function testClearIsSafeWhenDirectoryMissing(): void
    {
        $missing = '/nonexistent/path/' . uniqid();
        $cache = new FileCache($missing);
        // No exception. clear() is a no-op when the dir doesn't exist.
        $cache->clear();
        self::assertDirectoryDoesNotExist($missing);
    }

    public function testAccessorsReflectConstructorArgs(): void
    {
        $cache = new FileCache($this->dir, defaultTtlSeconds: 7200);
        self::assertSame($this->dir, $cache->directory());
        self::assertSame(7200, $cache->defaultTtlSeconds());
    }
}
