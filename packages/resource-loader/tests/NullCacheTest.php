<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Tests;

use Phpdftk\ResourceLoader\Cache\NullCache;
use Phpdftk\ResourceLoader\FetchResult;
use PHPUnit\Framework\TestCase;

/**
 * Sanity test for the default cache backend: get always misses,
 * set silently discards.
 */
final class NullCacheTest extends TestCase
{
    public function testGetAlwaysReturnsNull(): void
    {
        $cache = new NullCache();
        self::assertNull($cache->get('any-key'));
        self::assertNull($cache->get(''));
        self::assertNull($cache->get('http://example.com/image.png'));
    }

    public function testSetDoesNotRetainResult(): void
    {
        $cache = new NullCache();
        $cache->set('k', new FetchResult(
            bytes: 'body',
            mimeType: 'image/png',
            originalUrl: 'http://example.com/x',
            finalUrl: 'http://example.com/x',
            cacheHit: false,
            statusCode: 200,
        ));
        // Set was a no-op — get still misses.
        self::assertNull($cache->get('k'));
    }
}
