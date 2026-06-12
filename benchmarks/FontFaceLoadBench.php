<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\TrueTypeParser;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;

/**
 * Track the cost of `@font-face` loading for both font formats. The
 * renderer parses sources eagerly at document setup so any regression
 * in parse + descriptor extraction shows up immediately as cold-page
 * latency.
 *
 * The two formats are reported side-by-side so a future change in one
 * (CFF charstring decode, glyf walking) doesn't silently pessimise
 * the other.
 *
 * Fixtures:
 *  - OpenType CFF: FreeSans (vendor-data/poppler-test)
 *  - TrueType: Ahem (vendor-data/wpt) — the canonical WPT font, drives
 *    most of the CSS WPT corpus.
 */
#[Bench\Iterations(3)]
#[Bench\Revs(5)]
class FontFaceLoadBench
{
    private string $otfBaseDir;
    private string $ttfBaseDir;

    public function __construct()
    {
        $this->otfBaseDir = realpath(__DIR__ . '/../vendor-data/poppler-test/unittestcases/fonts')
            ?: __DIR__;
        $this->ttfBaseDir = realpath(__DIR__ . '/../vendor-data/wpt/fonts')
            ?: __DIR__;
    }

    public function benchLoadOpenTypeFromBytes(): void
    {
        // Pure parser cost: read + parse OTF. No renderer overhead.
        $path = $this->otfBaseDir . '/FreeSans.otf';
        if (!is_file($path)) {
            return;
        }
        $bytes = (string) file_get_contents($path);
        OpenTypeParser::fromBytes($bytes)->parse();
    }

    public function benchLoadTrueTypeFromBytes(): void
    {
        $path = $this->ttfBaseDir . '/Ahem.ttf';
        if (!is_file($path)) {
            return;
        }
        $bytes = (string) file_get_contents($path);
        TrueTypeParser::fromBytes($bytes)->parse();
    }

    public function benchOpenTypeFontFaceRender(): void
    {
        // End-to-end @font-face flow: parse → register → emit a
        // page that uses the registered font.
        $renderer = new Renderer(
            (new RendererOptions())->withBaseDir($this->otfBaseDir),
        );
        $renderer->render(
            '<html><head><style>'
            . '@font-face { font-family: TestFace; src: url(FreeSans.otf); }'
            . 'div { font-family: TestFace; font-size: 12px; }'
            . '</style></head><body><div>The quick brown fox jumps over the lazy dog.</div></body></html>',
        );
    }

    public function benchTrueTypeFontFaceRender(): void
    {
        $renderer = new Renderer(
            (new RendererOptions())->withBaseDir($this->ttfBaseDir),
        );
        $renderer->render(
            '<html><head><style>'
            . '@font-face { font-family: TestFace; src: url(Ahem.ttf); }'
            . 'div { font-family: TestFace; font-size: 12px; }'
            . '</style></head><body><div>The quick brown fox jumps over the lazy dog.</div></body></html>',
        );
    }
}
