<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for per-element CSS `font-size` cascade
 * through the MathML painter. The fontSize on the surrounding
 * MathmlPaintContext changes when an element's inline
 * `style="font-size: ..."` differs from the parent's, and
 * children of that subtree paint at the new size. Token siblings
 * outside the subtree keep painting at the original size.
 *
 * Fed by the wpt-harness DOM settler's per-element cascade
 * projection (#107) for tests with external CSS rules, and by
 * author-supplied inline styles for tests that set them directly.
 */
final class StyleFontSizeCascadeTest extends TestCase
{
    public function testElementWithExplicitFontSizeEmitsTfChange(): void
    {
        // Without any font-size cascade, the painter emits one Tf at
        // its default size. With a nested `style="font-size: 24px"`
        // on an mrow, the painter should swap to the new size for
        // the subtree and swap back on exit.
        $bytes = $this->render(
            '<mrow style="font-size: 24px"><mi>x</mi></mrow>',
        );
        preg_match_all('/\/F\d+\s+([\d.]+)\s+Tf/', $bytes, $m);
        $sizes = array_values(array_unique(array_map('floatval', $m[1])));
        self::assertContains(24.0, $sizes, 'expected Tf 24 inside the cascade');
        // The initial 12pt size should also appear (before / after
        // the subtree).
        self::assertContains(12.0, $sizes, 'expected Tf 12 outside the cascade');
    }

    public function testNestedFontSizesScaleEm(): void
    {
        // Math root at 12pt; mfrac at 1.5em -> 18pt. Children paint
        // under 18pt.
        $bytes = $this->render(
            '<mrow style="font-size: 1.5em"><mn>1</mn></mrow>',
        );
        preg_match_all('/\/F\d+\s+([\d.]+)\s+Tf/', $bytes, $m);
        $sizes = array_map('floatval', $m[1]);
        // 18.0 should appear among the emitted sizes.
        $has18 = false;
        foreach ($sizes as $s) {
            if (abs($s - 18.0) < 0.001) {
                $has18 = true;
                break;
            }
        }
        self::assertTrue($has18, 'expected Tf 18 from 1.5em cascade');
    }

    public function testSettlerProjectedFontSizeStillFires(): void
    {
        // The DOM settler emits a /* phpdftk-settle-dom */ marker
        // before its projected declarations. The painter should
        // strip the marker and apply the font-size unchanged.
        $bytes = $this->render(
            '<mrow style="color: red; /* phpdftk-settle-dom */ '
            . 'font-size: 30px"><mn>1</mn></mrow>',
        );
        preg_match_all('/\/F\d+\s+([\d.]+)\s+Tf/', $bytes, $m);
        $sizes = array_map('floatval', $m[1]);
        $has30 = false;
        foreach ($sizes as $s) {
            if (abs($s - 30.0) < 0.001) {
                $has30 = true;
                break;
            }
        }
        self::assertTrue($has30, 'expected Tf 30 from projected font-size');
    }

    public function testNoFontSizeCascadeKeepsSingleSize(): void
    {
        // Plain MathML with no inline font-size: only one Tf size
        // should appear (the renderer's default).
        $bytes = $this->render('<mn>1</mn>');
        preg_match_all('/\/F\d+\s+([\d.]+)\s+Tf/', $bytes, $m);
        $sizes = array_unique(array_map('floatval', $m[1]));
        self::assertCount(
            1,
            $sizes,
            'plain MathML should keep one Tf size',
        );
    }

    public function testAbsoluteFontSizeViaPt(): void
    {
        $bytes = $this->render(
            '<mrow style="font-size: 36pt"><mn>1</mn></mrow>',
        );
        preg_match_all('/\/F\d+\s+([\d.]+)\s+Tf/', $bytes, $m);
        $sizes = array_map('floatval', $m[1]);
        $has36 = false;
        foreach ($sizes as $s) {
            if (abs($s - 36.0) < 0.001) {
                $has36 = true;
                break;
            }
        }
        self::assertTrue($has36, 'expected Tf 36 from pt cascade');
    }

    private function render(string $innerXml): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }
}
