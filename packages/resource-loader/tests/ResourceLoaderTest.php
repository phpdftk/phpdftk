<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Tests;

use Phpdftk\ResourceLoader\Cache\CacheInterface;
use Phpdftk\ResourceLoader\Exception\SsrfBlockedException;
use Phpdftk\ResourceLoader\FetchOptions;
use Phpdftk\ResourceLoader\FetchResult;
use Phpdftk\ResourceLoader\HttpFetcher;
use Phpdftk\ResourceLoader\MimeSniffer;
use Phpdftk\ResourceLoader\ResourceLoader;
use Phpdftk\ResourceLoader\SsrfGuard;
use Phpdftk\ResourceLoader\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4F.1 — ResourceLoader integration tests. The wiring path is:
 *
 *   fetch(url)
 *     → ssrfGuard->assertSafe(url)
 *     → cache->get(url)         (return cached if hit)
 *     → httpFetcher->fetch(url)
 *     → cache->set(url, result)
 *     → return result
 *
 * Tests verify each link.
 */
final class ResourceLoaderTest extends TestCase
{
    private function makeLoader(
        FakeTransport $transport,
        ?CacheInterface $cache = null,
        ?SsrfGuard $guard = null,
    ): ResourceLoader {
        $guard ??= new SsrfGuard();
        return new ResourceLoader(
            cache: $cache ?? new \Phpdftk\ResourceLoader\Cache\NullCache(),
            ssrfGuard: $guard,
            defaultOptions: new FetchOptions(),
            fetcher: new HttpFetcher(
                ssrfGuard: $guard,
                mimeSniffer: new MimeSniffer(),
                transport: $transport,
            ),
        );
    }

    public function testHappyPathFetchReturnsBytes(): void
    {
        $png = "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 16);
        $transport = (new FakeTransport())->queueOk($png);
        $loader = $this->makeLoader($transport);

        $result = $loader->fetch('https://example.com/image.png');

        self::assertSame($png, $result->bytes);
        self::assertSame('image/png', $result->mimeType);
        self::assertFalse($result->cacheHit);
    }

    public function testCacheHitShortCircuitsTheFetch(): void
    {
        $cached = new FetchResult(
            bytes: 'cached body',
            mimeType: 'image/jpeg',
            originalUrl: 'https://example.com/x',
            finalUrl: 'https://example.com/final',
            cacheHit: false,
            statusCode: 200,
        );
        $cache = new InMemoryCacheStub(['https://example.com/x' => $cached]);
        $transport = new FakeTransport();  // empty — should never be called
        $loader = $this->makeLoader($transport, $cache);

        $result = $loader->fetch('https://example.com/x');

        self::assertSame('cached body', $result->bytes);
        self::assertTrue($result->cacheHit);
        self::assertSame([], $transport->calls);
    }

    public function testCacheMissPopulatesCache(): void
    {
        $cache = new InMemoryCacheStub();
        $transport = (new FakeTransport())->queueOk('fresh body');
        $loader = $this->makeLoader($transport, $cache);

        $loader->fetch('https://example.com/x');

        self::assertArrayHasKey('https://example.com/x', $cache->stored);
        self::assertSame('fresh body', $cache->stored['https://example.com/x']->bytes);
    }

    public function testSsrfGuardBlocksBeforeCacheLookup(): void
    {
        $cache = new InMemoryCacheStub();
        $transport = new FakeTransport();
        $loader = $this->makeLoader($transport, $cache);

        $this->expectException(SsrfBlockedException::class);
        $loader->fetch('http://127.0.0.1/');

        self::assertFalse($cache->getCalled);
    }
}

/**
 * In-test cache that records `get()` calls and `set()` writes so
 * tests can assert on the wiring behaviour without depending on
 * NullCache's silent-discard semantics.
 */
final class InMemoryCacheStub implements CacheInterface
{
    public bool $getCalled = false;
    /** @var array<string, FetchResult> */
    public array $stored = [];

    /** @param array<string, FetchResult> $initial */
    public function __construct(array $initial = [])
    {
        $this->stored = $initial;
    }

    public function get(string $key): ?FetchResult
    {
        $this->getCalled = true;
        return $this->stored[$key] ?? null;
    }

    public function set(string $key, FetchResult $result): void
    {
        $this->stored[$key] = $result;
    }
}
