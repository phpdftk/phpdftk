<?php

declare(strict_types=1);

namespace Phpdftk\Benchmarks;

use PhpBench\Attributes as Bench;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;
use Phpdftk\SvgToPdf\Translator;

/**
 * Benchmarks for the full SVG-to-PDF pipeline. Each subject parses a
 * fixture SVG, paints it through `SvgRenderer::draw`, and round-trips
 * the result through `PdfWriter::toBytes()`. The subjects target each
 * major feature family individually plus a combined "realistic" mix so
 * regression-slope from any single sub-phase is visible.
 *
 * Fixtures are inlined as strings rather than loaded from disk to keep
 * the timing focused on the painter rather than filesystem I/O — and to
 * keep the bench self-contained for `composer benchmark` in CI.
 */
#[Bench\Iterations(3)]
#[Bench\Revs(3)]
class SvgToPdfBench
{
    private SvgParser $svgParser;

    public function __construct()
    {
        $this->svgParser = new SvgParser();
    }

    public function benchBasicShapes(): void
    {
        // ~30 basic-shape primitives in a single SVG — covers the 3K
        // path through the painter (rect / circle / ellipse / line /
        // polyline / polygon) with fill, stroke, opacity, and dash
        // (3N stroke params).
        $body = '';
        for ($i = 0; $i < 30; $i++) {
            $body .= sprintf(
                '<rect x="%d" y="%d" width="20" height="20" '
                . 'fill="rgb(%d, %d, %d)" stroke="black" '
                . 'stroke-width="1.5" stroke-opacity="0.7"/>',
                ($i % 10) * 30,
                ((int) ($i / 10)) * 30,
                ($i * 23) % 256,
                ($i * 47) % 256,
                ($i * 71) % 256,
            );
        }
        $this->render(
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 100">%s</svg>', $body),
        );
    }

    public function benchPathHeavyDocument(): void
    {
        // A handful of paths exercising the 3L painter — cubic / smooth
        // cubic / quadratic / arc commands, all with the 3R+9 bbox
        // computation kicking in (objectBoundingBox gradients on
        // paths).
        $body = '<defs><linearGradient id="g">'
            . '<stop offset="0" stop-color="red"/>'
            . '<stop offset="1" stop-color="blue"/></linearGradient></defs>';
        for ($i = 0; $i < 12; $i++) {
            $body .= sprintf(
                '<path d="M %d %d C %d %d %d %d %d %d S %d %d %d %d '
                . 'Q %d %d %d %d A 15 15 0 0 1 %d %d Z" fill="url(#g)" '
                . 'stroke="black" stroke-width="0.5"/>',
                $i * 25,
                $i * 8,
                $i * 25 + 10,
                $i * 8 + 30,
                $i * 25 + 20,
                $i * 8 + 30,
                $i * 25 + 30,
                $i * 8,
                $i * 25 + 50,
                $i * 8 - 10,
                $i * 25 + 60,
                $i * 8,
                $i * 25 + 70,
                $i * 8 + 5,
                $i * 25 + 80,
                $i * 8,
                $i * 25 + 100,
                $i * 8 + 20,
            );
        }
        $this->render(
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 200">%s</svg>', $body),
        );
    }

    public function benchGradientHeavyDocument(): void
    {
        // 10 linear + 10 radial gradients, each referenced by a
        // fill="url(#g_n)" rect. Stresses 3O shading registration plus
        // 3R+4 `gradientTransform`.
        $defs = '';
        $body = '';
        for ($i = 0; $i < 10; $i++) {
            $defs .= sprintf(
                '<linearGradient id="lin%d" gradientUnits="userSpaceOnUse" '
                . 'x1="%d" y1="0" x2="%d" y2="%d" '
                . 'gradientTransform="rotate(%d)">'
                . '<stop offset="0" stop-color="red"/>'
                . '<stop offset="0.5" stop-color="white"/>'
                . '<stop offset="1" stop-color="blue"/>'
                . '</linearGradient>',
                $i,
                $i * 10,
                $i * 10 + 80,
                40,
                $i * 9,
            );
            $defs .= sprintf(
                '<radialGradient id="rad%d" gradientUnits="userSpaceOnUse" '
                . 'cx="%d" cy="40" r="30" fx="%d" fy="35" fr="5">'
                . '<stop offset="0" stop-color="yellow"/>'
                . '<stop offset="1" stop-color="purple"/>'
                . '</radialGradient>',
                $i,
                $i * 50 + 40,
                $i * 50 + 30,
            );
            $body .= sprintf(
                '<rect x="%d" y="0" width="40" height="80" fill="url(#lin%d)"/>',
                $i * 50,
                $i,
            );
            $body .= sprintf(
                '<circle cx="%d" cy="120" r="20" fill="url(#rad%d)"/>',
                $i * 50 + 20,
                $i,
            );
        }
        $this->render(
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150">'
                . '<defs>%s</defs>%s</svg>',
                $defs,
                $body,
            ),
        );
    }

    public function benchTextHeavyDocument(): void
    {
        // 50 text elements across all four font variants. Exercises 3P
        // font caching (Helvetica / Times / Courier) plus 3R+5 per-glyph
        // positioning for a quarter of them.
        $variants = [
            ['family' => 'sans-serif', 'weight' => 'normal', 'style' => 'normal'],
            ['family' => 'serif', 'weight' => 'bold', 'style' => 'normal'],
            ['family' => 'monospace', 'weight' => 'normal', 'style' => 'italic'],
            ['family' => 'sans-serif', 'weight' => 'bold', 'style' => 'italic'],
        ];
        $body = '';
        for ($i = 0; $i < 50; $i++) {
            $v = $variants[$i % 4];
            $perGlyph = ($i % 4 === 3) ? ' x="10 15 20 25 30 35 40 45 50 55 60 65"' : '';
            $body .= sprintf(
                '<text%s y="%d" font-family="%s" font-weight="%s" '
                . 'font-style="%s" font-size="12">phpdftk %d</text>',
                $perGlyph,
                ($i + 1) * 14,
                $v['family'],
                $v['weight'],
                $v['style'],
                $i,
            );
        }
        $this->render(
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 800">%s</svg>', $body),
        );
    }

    public function benchUseSymbolExpansion(): void
    {
        // A single symbol referenced 100 times via `<use>`. Stresses
        // 3Q symbol expansion + 3G findById caching.
        $body = '<defs><symbol id="star" viewBox="0 0 20 20">'
            . '<polygon points="10,1 12,7 19,8 14,12 16,19 10,15 4,19 6,12 1,8 8,7" fill="gold"/>'
            . '</symbol></defs>';
        for ($i = 0; $i < 100; $i++) {
            $body .= sprintf(
                '<use href="#star" x="%d" y="%d"/>',
                ($i % 20) * 22,
                ((int) ($i / 20)) * 22,
            );
        }
        $this->render(
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 450 120">%s</svg>', $body),
        );
    }

    public function benchClipAndMaskHeavy(): void
    {
        // 15 elements each carrying both `clip-path` and `mask`. Stresses
        // 3R+3 / 3R+8 / 3R+10 / 3R+11 — the four-attribute composition
        // path (transform + opacity + clip + mask) plus ExtGState +
        // FormXObject churn.
        $defs = '<defs>'
            . '<clipPath id="clip" clipPathUnits="objectBoundingBox" '
            . 'transform="scale(0.9)"><rect width="1" height="1"/></clipPath>'
            . '<mask id="m" maskContentUnits="userSpaceOnUse">'
            . '<rect width="100" height="100" fill="white"/></mask>'
            . '</defs>';
        $body = '';
        for ($i = 0; $i < 15; $i++) {
            $body .= sprintf(
                '<rect x="%d" y="%d" width="40" height="40" '
                . 'fill="hsl(%d, 70%%, 50%%)" opacity="0.6" '
                . 'transform="rotate(%d, %d, %d)" '
                . 'clip-path="url(#clip)" mask="url(#m)"/>',
                ($i % 5) * 50,
                ((int) ($i / 5)) * 50,
                $i * 24,
                $i * 7,
                ($i % 5) * 50 + 20,
                ((int) ($i / 5)) * 50 + 20,
            );
        }
        $this->render(
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 250 150">%s%s</svg>', $defs, $body),
        );
    }

    public function benchRealisticIconAtlas(): void
    {
        // Combined fixture: a 60-element icon atlas using symbols,
        // gradients, paths, and labelled text — representative of the
        // common "render an SVG icon sheet into a print catalog" use
        // case the package is built for.
        $body = '<defs>'
            . '<symbol id="icon" viewBox="0 0 24 24">'
            . '<circle cx="12" cy="12" r="10" fill="#3b82f6" stroke="#1e40af" stroke-width="1"/>'
            . '<path d="M 6 12 L 10 16 L 18 8" stroke="white" stroke-width="2" fill="none"/>'
            . '</symbol>'
            . '<linearGradient id="bg" x1="0" y1="0" x2="0" y2="1">'
            . '<stop offset="0" stop-color="#f3f4f6"/>'
            . '<stop offset="1" stop-color="#d1d5db"/>'
            . '</linearGradient>'
            . '</defs>'
            . '<rect width="600" height="400" fill="url(#bg)"/>';
        for ($i = 0; $i < 60; $i++) {
            $col = $i % 10;
            $row = (int) ($i / 10);
            $x = 30 + $col * 55;
            $y = 30 + $row * 60;
            $body .= sprintf('<use href="#icon" x="%d" y="%d" width="32" height="32"/>', $x, $y);
            $body .= sprintf(
                '<text x="%d" y="%d" font-size="9" fill="#1f2937" '
                . 'text-anchor="middle">Item %02d</text>',
                $x + 16,
                $y + 48,
                $i + 1,
            );
        }
        $this->render(
            sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400">%s</svg>', $body),
        );
    }

    public function benchTranslatorWithoutAdapter(): void
    {
        // Direct `Translator::paint()` use — no SvgRenderer wrapping.
        // Confirms the lower-level entry stays as fast as the adapter
        // (the adapter only adds a cm + the page setup; everything
        // else is the same dispatch).
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            . '<rect width="100" height="100" fill="lightblue"/>'
            . '<circle cx="50" cy="50" r="30" fill="red" stroke="black"/>'
            . '<path d="M 20 50 Q 50 20 80 50 T 80 50 Z" fill="purple"/>'
            . '</svg>',
        );
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        (new Translator())->paint($svg, $stream, $page, $writer);
        $writer->toBytes();
    }

    /**
     * Shared end-stage for most subjects: parse the source SVG, set up
     * a 612×792 page, draw the SVG into it via the 3R adapter, and
     * serialise to bytes. Returning the byte length keeps the result
     * from being optimised out.
     */
    private function render(string $svg): int
    {
        $doc = $this->svgParser->parse($svg);
        $writer = new PdfWriter();
        $page = $writer->addPage(612.0, 792.0);
        $renderer = new SvgRenderer($page, $writer);
        $renderer->draw($doc, x: 72.0, y: 72.0, width: 468.0, height: 648.0);
        return strlen($writer->toBytes());
    }
}
