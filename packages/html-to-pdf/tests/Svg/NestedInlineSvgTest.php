<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Svg;

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §7.5 — an inner `<svg>` is a nested VIEWPORT inside the SVG
 * pipeline, not a second replaced box in the HTML flow. The HTML box
 * generator treated every `<svg>` as a foreign-content ROOT, so a
 * nested one also got its own atomic-inline box and the painter ran it
 * through `paintInlineSvg` a second time — once correctly inside the
 * outer viewport, and once again at the HTML pipeline's own scale,
 * unclipped by the inner viewport.
 */
final class NestedInlineSvgTest extends TestCase
{
    private function render(string $html): string
    {
        $writer = new PdfWriter(compressStreams: false);
        (new Renderer())->renderInto($writer, $html);
        return $writer->toBytes();
    }

    public function testANestedSvgIsPaintedExactlyOnce(): void
    {
        $bytes = $this->render(
            '<!DOCTYPE html><svg width="90" height="90"'
            . ' xmlns="http://www.w3.org/2000/svg"><svg width="90" height="90">'
            . '<rect width="100" height="100" fill="red"/></svg></svg>',
        );
        self::assertSame(
            1,
            substr_count($bytes, '0 0 100 100 re'),
            'nested <svg> content emitted more than once',
        );
    }

    public function testTheDuplicateWasNotTheOnlyEmission(): void
    {
        // Guard against "fixing" this by dropping the paint entirely:
        // the surviving copy has to be the one inside the correct
        // viewport, which the HTML pipeline reaches through the outer
        // SVG's own transform rather than at its own 0.75 scale.
        $bytes = $this->render(
            '<!DOCTYPE html><svg width="90" height="90"'
            . ' xmlns="http://www.w3.org/2000/svg"><svg width="90" height="90">'
            . '<rect width="100" height="100" fill="red"/></svg></svg>',
        );
        self::assertStringContainsString('0 0 100 100 re', $bytes);
        self::assertStringNotContainsString('0.75 0 0 -0.75 0 792 cm', $bytes);
    }

    public function testAnOuterSvgStillPaints(): void
    {
        // Guard: the root `<svg>` must keep its replaced box.
        $bytes = $this->render(
            '<!DOCTYPE html><svg width="80" height="60"'
            . ' xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="80" height="60" fill="red"/></svg>',
        );
        self::assertSame(1, substr_count($bytes, '0 0 80 60 re'));
    }

    public function testTwoSIBLINGSvgRootsEachPaintOnce(): void
    {
        // Guard: the ancestor test must key on NESTING, not on "we've
        // already seen an svg".
        $bytes = $this->render(
            '<!DOCTYPE html><svg width="10" height="10" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" fill="red"/></svg>'
            . '<svg width="10" height="10" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" fill="red"/></svg>',
        );
        self::assertSame(2, substr_count($bytes, '0 0 10 10 re'));
    }
}
