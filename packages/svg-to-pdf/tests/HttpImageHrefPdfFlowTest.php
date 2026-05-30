<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\ResourceLoader\HttpFetcher;
use Phpdftk\ResourceLoader\MimeSniffer;
use Phpdftk\ResourceLoader\ResourceLoader;
use Phpdftk\ResourceLoader\SsrfGuard;
use Phpdftk\ResourceLoader\Tests\Support\FakeTransport;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * API-cleanup follow-up: a single `$pdf->withResourceLoader($loader)`
 * configures the whole document so callers don't need to pass the
 * loader to every `SvgRenderer::addToPdf()` / `createTemplate()`
 * call. Explicit per-call loaders still override.
 */
final class HttpImageHrefPdfFlowTest extends TestCase
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

    public function testPdfWithResourceLoaderRoundTrips(): void
    {
        $loader = $this->makeLoader(new FakeTransport());
        $pdf = new Pdf();

        self::assertNull($pdf->resourceLoader());
        $result = $pdf->withResourceLoader($loader);
        self::assertSame($pdf, $result);
        self::assertSame($loader, $pdf->resourceLoader());
    }

    public function testWithResourceLoaderClearsWithNull(): void
    {
        $loader = $this->makeLoader(new FakeTransport());
        $pdf = (new Pdf())->withResourceLoader($loader);
        $pdf->withResourceLoader(null);
        self::assertNull($pdf->resourceLoader());
    }

    public function testConstructorParameterPathSets(): void
    {
        $loader = $this->makeLoader(new FakeTransport());
        $pdf = new Pdf(resourceLoader: $loader);
        self::assertSame($loader, $pdf->resourceLoader());
    }

    public function testAddToPdfPicksUpPdfsLoaderWhenNotPassedExplicitly(): void
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertNotFalse($png);

        $transport = (new FakeTransport())->queueOk($png);
        $loader = $this->makeLoader($transport);

        $svg = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50">'
            . '<image x="10" y="10" width="80" height="30" href="https://example.com/logo.png"/></svg>',
        );

        $pdf = (new Pdf())->withResourceLoader($loader);
        // No explicit resourceLoader: argument — Pdf's loader picks up.
        SvgRenderer::addToPdf($pdf, $svg, width: 200.0, height: 100.0);

        $bytes = $pdf->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertCount(1, $transport->calls);
        self::assertSame('https://example.com/logo.png', $transport->calls[0]['url']);
    }

    public function testExplicitResourceLoaderArgumentOverridesPdfsLoader(): void
    {
        // Pdf carries loader A; the call passes loader B explicitly.
        // Only B's transport should see the fetch.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertNotFalse($png);

        $transportA = new FakeTransport();
        $transportB = (new FakeTransport())->queueOk($png);
        $loaderA = $this->makeLoader($transportA);
        $loaderB = $this->makeLoader($transportB);

        $svg = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50">'
            . '<image x="10" y="10" width="80" height="30" href="https://example.com/logo.png"/></svg>',
        );

        $pdf = (new Pdf())->withResourceLoader($loaderA);
        SvgRenderer::addToPdf($pdf, $svg, width: 200.0, resourceLoader: $loaderB);

        // A never used.
        self::assertSame([], $transportA->calls);
        // B fetched.
        self::assertCount(1, $transportB->calls);
    }

    public function testWithLoaderFactoryConstructsRendererWithLoader(): void
    {
        // `SvgRenderer::withLoader($page, $writer, $loader)` is the
        // ergonomic alternative to constructing a Translator
        // manually.
        $writer = new \Phpdftk\Pdf\Writer\PdfWriter();
        $page = $writer->addPage();
        $loader = $this->makeLoader(new FakeTransport());

        $renderer = SvgRenderer::withLoader($page, $writer, $loader);
        self::assertInstanceOf(SvgRenderer::class, $renderer);
    }

    public function testTranslatorConstructorWithoutLoaderStillWorks(): void
    {
        // No-arg Translator construction preserved.
        $t = new Translator();
        self::assertInstanceOf(Translator::class, $t);
    }
}
