<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests;

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\ResourceLoader\HttpFetcher;
use Phpdftk\ResourceLoader\MimeSniffer;
use Phpdftk\ResourceLoader\ResourceLoader;
use Phpdftk\ResourceLoader\SsrfGuard;
use Phpdftk\ResourceLoader\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

/**
 * 4F.5 — `<img src="https://...">` now resolves through the
 * `phpdftk/resource-loader` package when a ResourceLoader is
 * passed to `RendererOptions::withResourceLoader()`. Default
 * behaviour (no loader) preserves the no-image silent drop
 * posture so existing call sites don't change behaviour.
 *
 * Tests use a FakeTransport so network access is fully
 * deterministic — no live HTTP server needed.
 */
final class HttpImageSrcTest extends TestCase
{
    private function makeLoader(FakeTransport $transport): ResourceLoader
    {
        $guard = new SsrfGuard();
        return new ResourceLoader(
            ssrfGuard: $guard,
            fetcher: new HttpFetcher(
                ssrfGuard: $guard,
                mimeSniffer: new MimeSniffer(),
                transport: $transport,
            ),
        );
    }

    private function renderWithLoader(string $html, ResourceLoader $loader): string
    {
        $options = (new RendererOptions(pageWidth: 612.0, pageHeight: 792.0))
            ->withResourceLoader($loader);
        $renderer = new Renderer($options);
        return $renderer->render($html)->writer->toBytes();
    }

    public function testHttpImgSrcWithLoaderEmbedsImage(): void
    {
        // Real 1×1 transparent PNG so the ImageParser accepts it.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertNotFalse($png);

        $transport = (new FakeTransport())->queueOk($png);
        $loader = $this->makeLoader($transport);

        // <p>-wrapped <img> — matches the existing data: image test's
        // shape so layout dispatches to paintImage. (Bare <img>
        // outside a flow block is also handled, but the wrapped
        // shape is the canonical phase-1 path.)
        $bytes = $this->renderWithLoader(
            '<html><body><p><img src="https://example.com/logo.png" width="50" height="50"></p></body></html>',
            $loader,
        );

        // Document is well-formed.
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
        // Image XObject got registered + referenced.
        self::assertStringContainsString('/Subtype /Image', $bytes);
        // Transport was called exactly once with the right URL.
        self::assertCount(1, $transport->calls);
        self::assertSame('https://example.com/logo.png', $transport->calls[0]['url']);
    }

    public function testHttpImgSrcWithoutLoaderDropsSilently(): void
    {
        // No loader → no fetch → no image XObject. Renderer
        // doesn't crash; produces a valid PDF with the alt text
        // fallback (or just empty space).
        $renderer = new Renderer(new RendererOptions(pageWidth: 612.0, pageHeight: 792.0));
        $bytes = $renderer->render(
            '<html><body><p><img src="https://example.com/logo.png" width="50" height="50" alt="logo"></p></body></html>',
        )->writer->toBytes();

        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
        self::assertStringNotContainsString('/Subtype /Image', $bytes);
    }

    public function testHttp404DropsSilently(): void
    {
        $transport = (new FakeTransport())->queue(
            new \Phpdftk\ResourceLoader\Transport\RawResponse(
                statusCode: 404,
                headers: [],
                body: 'Not Found',
                finalUrl: '<set-by-fetcher>',
            ),
        );
        $loader = $this->makeLoader($transport);

        $bytes = $this->renderWithLoader(
            '<html><body><p><img src="https://example.com/missing.png" width="50" height="50"></p></body></html>',
            $loader,
        );

        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringNotContainsString('/Subtype /Image', $bytes);
    }

    public function testHttpRedirectToLoopbackBlockedBySsrf(): void
    {
        // SSRF rebinding-style — initial URL passes pre-flight,
        // server redirects to 127.0.0.1. Per-hop guard catches it.
        $transport = (new FakeTransport())->queueRedirect('http://127.0.0.1/internal');
        $loader = $this->makeLoader($transport);

        $bytes = $this->renderWithLoader(
            '<html><body><p><img src="https://example.com/redirector" width="50" height="50"></p></body></html>',
            $loader,
        );

        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringNotContainsString('/Subtype /Image', $bytes);
    }

    public function testDirectLoopbackSrcBlockedBySsrfPreFlight(): void
    {
        // Loader injected, but direct loopback URL fails pre-flight.
        $transport = new FakeTransport();  // never called
        $loader = $this->makeLoader($transport);

        $bytes = $this->renderWithLoader(
            '<html><body><p><img src="http://127.0.0.1/internal.png" width="50" height="50"></p></body></html>',
            $loader,
        );

        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringNotContainsString('/Subtype /Image', $bytes);
        self::assertSame([], $transport->calls);
    }

    public function testHttpRedirectFollowedAndEmbeds(): void
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertNotFalse($png);

        $transport = (new FakeTransport())
            ->queueRedirect('https://cdn.example.com/asset.png')
            ->queueOk($png);
        $loader = $this->makeLoader($transport);

        $bytes = $this->renderWithLoader(
            '<html><body><p><img src="https://example.com/redirect" width="40" height="40"></p></body></html>',
            $loader,
        );

        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('/Subtype /Image', $bytes);
        self::assertCount(2, $transport->calls);
    }

    public function testRendererOptionsWithResourceLoaderRoundTrips(): void
    {
        $loader = $this->makeLoader(new FakeTransport());
        $options = new RendererOptions();
        $updated = $options->withResourceLoader($loader);

        self::assertNull($options->resourceLoader);
        self::assertSame($loader, $updated->resourceLoader);
    }
}
