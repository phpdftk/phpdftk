<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MathML Core §3.1.6 style cascade through script
 * constructs:
 *
 *   - <msup> / <msub> / <msubsup> propagate scriptLevel + 1 to
 *     their script children.
 *   - <mover> / <munder> / <munderover> propagate to their limits.
 *   - <mroot>'s index uses scriptscriptLevel (+2 from parent).
 *   - All scripts force displayStyle=false on their children.
 *
 * The painter exposes scriptLevel via the Tf operator (font set
 * + size) - we walk the Tf sequence and verify the sizes match
 * the cascade.
 */
final class ScriptCascadeTest extends TestCase
{
    public function testNestedFractionInsideSuperscriptShrinksTwice(): void
    {
        // Top-level inline math.
        //   <msup>x ^ <mfrac>a b</mfrac></msup>
        // Sup is at scriptLevel 1 (script size). Inside the sup,
        // <mfrac> bumps children to scriptLevel 2 (scriptscript
        // size). So the smallest font in the stream should be
        // scriptscript scale of the base.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msup><mi>x</mi><mfrac><mi>a</mi><mi>b</mi></mfrac></msup>'
                . '</math>',
        );
        $sizes = $this->extractFontSizes($bytes);
        $base = max($sizes);
        $smallest = min($sizes);
        // scriptscript scale defaults to 0.55.
        self::assertEqualsWithDelta(
            0.55,
            $smallest / $base,
            0.05,
            'Inner mfrac inside msup should reach scriptscript size',
        );
    }

    public function testMrootIndexAtScriptscriptLevel(): void
    {
        // <mroot>x 3</mroot>: base 'x' at full size; index '3' at
        // scriptscript level (scriptLevel + 2 = 2 from level 0).
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mroot><mi>x</mi><mn>3</mn></mroot>'
                . '</math>',
        );
        $sizes = $this->extractFontSizes($bytes);
        $base = max($sizes);
        $smallest = min($sizes);
        // index renders at scriptscript scale (0.55) of the base.
        self::assertEqualsWithDelta(
            0.55,
            $smallest / $base,
            0.05,
            'mroot index should render at scriptscript size',
        );
    }

    public function testMsubFollowsSameCascadeAsMsup(): void
    {
        // Same cascade applies for subscripts.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msub><mi>x</mi><mfrac><mi>a</mi><mi>b</mi></mfrac></msub>'
                . '</math>',
        );
        $sizes = $this->extractFontSizes($bytes);
        $base = max($sizes);
        $smallest = min($sizes);
        self::assertEqualsWithDelta(0.55, $smallest / $base, 0.05);
    }

    public function testThreefoldNestedScriptsClampAtScriptscript(): void
    {
        // <msup>x ^ <msup>y ^ <msup>z ^ a</msup></msup></msup>
        // The 'a' is three levels deep but scriptLevel clamps at 2
        // (= scriptscript), so the deepest Tf should match the
        // scriptscript scale of the base - NOT a smaller power.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msup><mi>x</mi>'
                . '<msup><mi>y</mi>'
                . '<msup><mi>z</mi><mi>a</mi></msup>'
                . '</msup>'
                . '</msup>'
                . '</math>',
        );
        $sizes = $this->extractFontSizes($bytes);
        $base = max($sizes);
        $smallest = min($sizes);
        self::assertEqualsWithDelta(
            0.55,
            $smallest / $base,
            0.05,
            'Nesting deeper than scriptscript should clamp at scriptscript scale',
        );
    }

    public function testMoverLimitInDisplayModePropagatesCascade(): void
    {
        // Display mode keeps the over centred above the base; the
        // over still renders at scriptLevel + 1 (script size).
        // Inside the over, an mfrac would clamp to scriptscript.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">'
                . '<mover><mi>X</mi><mfrac><mi>a</mi><mi>b</mi></mfrac></mover>'
                . '</math>',
        );
        $sizes = $this->extractFontSizes($bytes);
        $base = max($sizes);
        $smallest = min($sizes);
        self::assertEqualsWithDelta(0.55, $smallest / $base, 0.05);
    }

    private function render(string $xml): string
    {
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
