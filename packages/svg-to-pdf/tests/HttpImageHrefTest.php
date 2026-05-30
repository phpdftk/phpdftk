<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\ResourceLoader\HttpFetcher;
use Phpdftk\ResourceLoader\MimeSniffer;
use Phpdftk\ResourceLoader\ResourceLoader;
use Phpdftk\ResourceLoader\SsrfGuard;
use Phpdftk\ResourceLoader\Tests\Support\FakeTransport;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 4F.1 — `<image href="https://...">` now resolves through the
 * `phpdftk/resource-loader` package when a ResourceLoader is
 * injected into the Translator. Default behaviour (no loader)
 * preserves the SVG 2 §12.6 "no image available" outcome — see
 * `UseAndImageTest::testImageWithHttpHrefIsRejectedWithoutLoader`.
 *
 * Tests here use a FakeTransport so the network access is fully
 * deterministic.
 */
final class HttpImageHrefTest extends TestCase
{
    private SvgParser $svgParser;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
    }

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

    /**
     * @return array{ops: string, bytes: string}
     */
    private function paintWithLoader(string $svg, ResourceLoader $loader): array
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse($svg);
        (new Translator($loader))->paint($doc, $stream, $page, $writer);
        return [
            'ops' => implode("\n", $stream->getOperators()),
            'bytes' => $writer->toBytes(),
        ];
    }

    public function testHttpHrefWithLoaderFetchesAndEmbedsImage(): void
    {
        // Smallest possible valid PNG (1×1 transparent).
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertNotFalse($png);

        $transport = (new FakeTransport())->queueOk($png);
        $loader = $this->makeLoader($transport);

        $result = $this->paintWithLoader(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image x="10" y="20" width="40" height="30" '
            . 'href="https://example.com/logo.png"/></svg>',
            $loader,
        );

        // The image lands at the expected scaled position.
        self::assertStringContainsString('40 0 0 -30 10 50 cm', $result['ops']);
        self::assertMatchesRegularExpression('!/Im\d+ Do!', $result['ops']);
        // Transport called exactly once.
        self::assertCount(1, $transport->calls);
        self::assertSame('https://example.com/logo.png', $transport->calls[0]['url']);
    }

    public function testHttpHrefWith404DropsSilently(): void
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

        $result = $this->paintWithLoader(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image x="0" y="0" width="10" height="10" '
            . 'href="https://example.com/missing.png"/></svg>',
            $loader,
        );

        // SVG 2 §12.6 — no image available, paint nothing.
        self::assertStringNotContainsString(' Do', $result['ops']);
    }

    public function testHttpHrefRedirectingToLoopbackDropsSilently(): void
    {
        // First response is a redirect to 127.0.0.1 (the
        // SSRF-rebinding attack pattern). Loader's per-hop SSRF
        // guard catches it; svg-to-pdf turns the
        // SsrfBlockedException into the SVG no-image outcome.
        $transport = (new FakeTransport())->queueRedirect('http://127.0.0.1/internal');
        $loader = $this->makeLoader($transport);

        $result = $this->paintWithLoader(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image x="0" y="0" width="10" height="10" '
            . 'href="https://example.com/redirector"/></svg>',
            $loader,
        );

        self::assertStringNotContainsString(' Do', $result['ops']);
    }

    public function testHttpHrefDirectlyTargetingLoopbackDropsSilently(): void
    {
        // Even when a loader is injected, the SSRF guard rejects
        // the pre-flight URL.
        $transport = new FakeTransport();  // never queued — guard fires first
        $loader = $this->makeLoader($transport);

        $result = $this->paintWithLoader(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image x="0" y="0" width="10" height="10" '
            . 'href="http://127.0.0.1/internal.png"/></svg>',
            $loader,
        );

        self::assertStringNotContainsString(' Do', $result['ops']);
        // Transport never reached.
        self::assertSame([], $transport->calls);
    }

    public function testHttpHrefFollowsRedirectAndEmbedsFinalBody(): void
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

        $result = $this->paintWithLoader(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image x="0" y="0" width="20" height="20" '
            . 'href="https://example.com/redirect"/></svg>',
            $loader,
        );

        self::assertMatchesRegularExpression('!/Im\d+ Do!', $result['ops']);
        self::assertCount(2, $transport->calls);
    }
}
