<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the painter side of <mstyle> — the explicit
 * displaystyle / scriptlevel overrides should change the rendered
 * font sizes for descendants.
 */
final class MstyleRenderingTest extends TestCase
{
    public function testMstyleDisplaystyleTrueAffectsNestedMfrac(): void
    {
        // mstyle wraps a fraction; without mstyle the fraction's
        // children render at script size (inline default). With
        // mstyle displaystyle="true" they render at full size.
        $bare = $this->render('<mfrac><mn>1</mn><mn>2</mn></mfrac>');
        $styled = $this->render(
            '<mstyle displaystyle="true">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</mstyle>',
        );
        $bareSizes = array_unique($this->extractFontSizes($bare));
        $styledSizes = array_unique($this->extractFontSizes($styled));
        // bare should have at least 2 sizes (base + script);
        // styled should have just 1 (full size everywhere).
        self::assertGreaterThan(1, count($bareSizes));
        self::assertCount(1, $styledSizes);
    }

    public function testMstyleScriptlevelAbsoluteForcesSmallerFont(): void
    {
        // mstyle scriptlevel="2" forces scriptscript size for all
        // descendants. The token 'x' inside should render at the
        // scriptscript scale (0.55) of the base.
        $bytes = $this->render(
            '<mstyle scriptlevel="2"><mi>x</mi></mstyle>',
        );
        $sizes = $this->extractFontSizes($bytes);
        $base = max($sizes);
        $smallest = min($sizes);
        self::assertEqualsWithDelta(0.55, $smallest / $base, 0.05);
    }

    public function testMstyleScriptlevelRelativeBumpsByDelta(): void
    {
        // mstyle scriptlevel="+1" bumps level from 0 to 1 (script
        // scale ~0.7).
        $bytes = $this->render(
            '<mstyle scriptlevel="+1"><mi>x</mi></mstyle>',
        );
        $sizes = $this->extractFontSizes($bytes);
        $base = max($sizes);
        $smallest = min($sizes);
        self::assertEqualsWithDelta(0.7, $smallest / $base, 0.05);
    }

    public function testMstyleScriptlevelMinusBackToZero(): void
    {
        // Inside an msup the sup is at level 1; mstyle
        // scriptlevel="-1" should pull the contents back to level 0.
        // The painter still emits the script-size Tf when entering
        // and leaving the sup (open/close), so the stream contains
        // both sizes, but during the mstyle body the size returns
        // to the base.
        $bytes = $this->render(
            '<msup><mi>x</mi>'
                . '<mstyle scriptlevel="-1"><mi>y</mi></mstyle>'
                . '</msup>',
        );
        $sizes = $this->extractFontSizes($bytes);
        // Confirm both the base font size (12) and the script size
        // appear - mstyle's full-size emit makes the base size
        // show up inside the script context.
        $maxSize = max($sizes);
        $hasBaseSizeInside = count(array_filter(
            $sizes,
            static fn(float $s): bool => abs($s - $maxSize) < 0.01,
        )) >= 2;
        self::assertTrue(
            $hasBaseSizeInside,
            'mstyle scriptlevel="-1" should bring the font back to base size '
            . 'at least once inside the script context',
        );
    }

    public function testMstyleNoOverrideActsAsTransparentContainer(): void
    {
        // mstyle with no relevant attributes should produce the
        // same output as if the wrapper weren't there.
        $bare = $this->render('<mi>x</mi>');
        $wrapped = $this->render('<mstyle><mi>x</mi></mstyle>');
        // Both should emit exactly one Tj for 'x'.
        self::assertSame(
            preg_match_all('/\(x\)\s+Tj/', $bare),
            preg_match_all('/\(x\)\s+Tj/', $wrapped),
        );
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

    /**
     * @return list<float>
     */
    private function extractFontSizes(string $bytes): array
    {
        if (!preg_match_all('|/F\d+\s+(\d+(?:\.\d+)?)\s+Tf|', $bytes, $matches)) {
            return [];
        }
        return array_map(static fn(string $s): float => (float) $s, $matches[1]);
    }
}
