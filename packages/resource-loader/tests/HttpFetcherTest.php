<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Tests;

use Phpdftk\ResourceLoader\Exception\FetchFailedException;
use Phpdftk\ResourceLoader\Exception\SsrfBlockedException;
use Phpdftk\ResourceLoader\FetchOptions;
use Phpdftk\ResourceLoader\HttpFetcher;
use Phpdftk\ResourceLoader\MimeSniffer;
use Phpdftk\ResourceLoader\SsrfGuard;
use Phpdftk\ResourceLoader\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4F.1 — HttpFetcher orchestration tests against a fake
 * transport. The transport is mocked out so the orchestration logic
 * (redirect handling, content-length cap, MIME sniffing, SSRF
 * re-check on redirect target, Authorization stripping across
 * hosts) can be exercised without a live HTTP server.
 *
 * Production network tests against curl + a real server live in
 * 4F.4 and are gated on `WPT_LIVE_HTTP=1` so they don't run in
 * regular CI.
 */
final class HttpFetcherTest extends TestCase
{
    private function fetcher(FakeTransport $transport, ?SsrfGuard $guard = null): HttpFetcher
    {
        return new HttpFetcher(
            ssrfGuard: $guard ?? new SsrfGuard(),
            mimeSniffer: new MimeSniffer(),
            transport: $transport,
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function testSimpleOkResponseReturnsBodyAndSniffsMime(): void
    {
        $png = "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 16);
        $transport = (new FakeTransport())->queueOk($png);
        $fetcher = $this->fetcher($transport);

        $result = $fetcher->fetch('https://example.com/logo.png', new FetchOptions());

        self::assertSame($png, $result->bytes);
        self::assertSame('image/png', $result->mimeType);
        self::assertSame('https://example.com/logo.png', $result->originalUrl);
        self::assertSame('https://example.com/logo.png', $result->finalUrl);
        self::assertFalse($result->cacheHit);
        self::assertSame(200, $result->statusCode);
    }

    public function testUserAgentAndAcceptHeadersAreSet(): void
    {
        $transport = (new FakeTransport())->queueOk('hello');
        $fetcher = $this->fetcher($transport);

        $fetcher->fetch('https://example.com/x', new FetchOptions(userAgent: 'test-ua/9.9'));

        self::assertCount(1, $transport->calls);
        self::assertSame('test-ua/9.9', $transport->calls[0]['headers']['User-Agent']);
        self::assertSame('*/*', $transport->calls[0]['headers']['Accept']);
    }

    public function testCustomHeadersThreadThrough(): void
    {
        $transport = (new FakeTransport())->queueOk('x');
        $fetcher = $this->fetcher($transport);

        $fetcher->fetch(
            'https://example.com/x',
            new FetchOptions(headers: ['Accept-Language' => 'en-US', 'X-Custom' => 'yes']),
        );

        self::assertSame('en-US', $transport->calls[0]['headers']['Accept-Language']);
        self::assertSame('yes', $transport->calls[0]['headers']['X-Custom']);
    }

    public function testTimeoutAndMaxBodyAreForwardedToTransport(): void
    {
        $transport = (new FakeTransport())->queueOk('x');
        $fetcher = $this->fetcher($transport);

        $fetcher->fetch(
            'https://example.com/x',
            new FetchOptions(timeoutSeconds: 30, maxContentLengthBytes: 1024),
        );

        self::assertSame(30, $transport->calls[0]['timeout']);
        self::assertSame(1024, $transport->calls[0]['maxBody']);
    }

    // -----------------------------------------------------------------------
    // SSRF pre-flight
    // -----------------------------------------------------------------------

    public function testSsrfPreFlightBlocksLoopback(): void
    {
        $transport = new FakeTransport();
        $fetcher = $this->fetcher($transport);

        $this->expectException(SsrfBlockedException::class);
        $fetcher->fetch('http://127.0.0.1/', new FetchOptions());

        // Transport never called — guard fired first.
        self::assertSame([], $transport->calls);
    }

    public function testSsrfReRunsOnRedirectTarget(): void
    {
        // First hop OK (example.com), redirect to a loopback.
        // The guard must catch the redirect target.
        $transport = (new FakeTransport())
            ->queueRedirect('http://127.0.0.1/internal');

        $fetcher = $this->fetcher($transport);

        $this->expectException(SsrfBlockedException::class);
        $fetcher->fetch('https://example.com/x', new FetchOptions(maxRedirects: 3));
    }

    // -----------------------------------------------------------------------
    // Redirect handling
    // -----------------------------------------------------------------------

    public function testFollowsAbsoluteRedirect(): void
    {
        $transport = (new FakeTransport())
            ->queueRedirect('https://example.com/asset.png')
            ->queueOk("\xff\xd8\xff\xe0fake-jpeg-data");

        $fetcher = $this->fetcher($transport);
        $result = $fetcher->fetch('https://example.com/x', new FetchOptions(maxRedirects: 3));

        self::assertSame('https://example.com/x', $result->originalUrl);
        self::assertSame('https://example.com/asset.png', $result->finalUrl);
        self::assertSame('image/jpeg', $result->mimeType);
    }

    public function testFollowsRootRelativeRedirect(): void
    {
        $transport = (new FakeTransport())
            ->queueRedirect('/asset.png')
            ->queueOk("\xff\xd8\xfffake");

        $fetcher = $this->fetcher($transport);
        $result = $fetcher->fetch('https://example.com/some/path/x', new FetchOptions(maxRedirects: 3));

        self::assertSame('https://example.com/asset.png', $result->finalUrl);
    }

    public function testFollowsRelativeRedirect(): void
    {
        $transport = (new FakeTransport())
            ->queueRedirect('other.png')
            ->queueOk("\xff\xd8\xfffake");

        $fetcher = $this->fetcher($transport);
        $result = $fetcher->fetch('https://example.com/dir/x', new FetchOptions(maxRedirects: 3));

        self::assertSame('https://example.com/dir/other.png', $result->finalUrl);
    }

    public function testExceedingRedirectLimitThrows(): void
    {
        $transport = (new FakeTransport())
            ->queueRedirect('https://a.example.com/1')
            ->queueRedirect('https://b.example.com/2')
            ->queueRedirect('https://c.example.com/3');

        $fetcher = $this->fetcher($transport);

        $this->expectException(FetchFailedException::class);
        $this->expectExceptionMessageMatches('/redirect limit/i');
        $fetcher->fetch('https://example.com/start', new FetchOptions(maxRedirects: 2));
    }

    public function testRedirectWithoutLocationHeaderThrows(): void
    {
        $transport = (new FakeTransport())
            ->queue(new \Phpdftk\ResourceLoader\Transport\RawResponse(
                statusCode: 302,
                headers: [],
                body: '',
                finalUrl: '<set-by-fetcher>',
            ));

        $fetcher = $this->fetcher($transport);

        $this->expectException(FetchFailedException::class);
        $this->expectExceptionMessageMatches('/Location header/i');
        $fetcher->fetch('https://example.com/x', new FetchOptions());
    }

    public function testAuthorizationStrippedOnCrossHostRedirect(): void
    {
        // First hop carries Authorization, response redirects to a
        // different host — second-hop request must NOT carry it.
        $transport = (new FakeTransport())
            ->queueRedirect('https://other.example.com/asset')
            ->queueOk('payload');

        $fetcher = $this->fetcher($transport);
        $fetcher->fetch(
            'https://example.com/x',
            new FetchOptions(
                maxRedirects: 3,
                headers: ['Authorization' => 'Bearer leaked-token'],
            ),
        );

        self::assertCount(2, $transport->calls);
        self::assertSame('Bearer leaked-token', $transport->calls[0]['headers']['Authorization'] ?? null);
        self::assertArrayNotHasKey('Authorization', $transport->calls[1]['headers']);
    }

    public function testAuthorizationPreservedOnSameHostRedirect(): void
    {
        $transport = (new FakeTransport())
            ->queueRedirect('https://example.com/asset')
            ->queueOk('payload');

        $fetcher = $this->fetcher($transport);
        $fetcher->fetch(
            'https://example.com/x',
            new FetchOptions(headers: ['Authorization' => 'Bearer keep-me']),
        );

        self::assertSame('Bearer keep-me', $transport->calls[1]['headers']['Authorization'] ?? null);
    }

    // -----------------------------------------------------------------------
    // Error responses
    // -----------------------------------------------------------------------

    public function testNon2xxResponseThrows(): void
    {
        $transport = (new FakeTransport())
            ->queue(new \Phpdftk\ResourceLoader\Transport\RawResponse(
                statusCode: 404,
                headers: [],
                body: 'Not Found',
                finalUrl: '<set-by-fetcher>',
            ));

        $fetcher = $this->fetcher($transport);

        $this->expectException(FetchFailedException::class);
        $this->expectExceptionMessageMatches('/HTTP 404/');
        $fetcher->fetch('https://example.com/missing', new FetchOptions());
    }

    public function testBodyExceedingCapThrows(): void
    {
        // Transport queued a body bigger than the cap. The fetcher's
        // defence-in-depth check catches this even if the transport
        // didn't.
        $transport = (new FakeTransport())->queueOk(str_repeat('x', 2048));

        $fetcher = $this->fetcher($transport);

        $this->expectException(FetchFailedException::class);
        $this->expectExceptionMessageMatches('/byte limit/i');
        $fetcher->fetch(
            'https://example.com/big',
            new FetchOptions(maxContentLengthBytes: 1024),
        );
    }
}
