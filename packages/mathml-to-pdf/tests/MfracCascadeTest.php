<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MathML Core §3.1.6 style cascade on `<mfrac>`:
 *
 *   - <mfrac> always forces displaystyle=false on its children.
 *   - When the surrounding displaystyle is false, <mfrac> bumps
 *     children's scriptLevel by 1 (children render smaller).
 *   - When the surrounding displaystyle is true, scriptLevel stays
 *     (children render at full size).
 *   - Nested fractions in inline mode get scriptscript size for
 *     the second level (scriptLevel clamps at 2).
 *
 * Sizes are exposed in the PDF via `Tf` (set font + size) operators.
 * We extract the sequence of font sizes and verify the cascade
 * picks them correctly.
 */
final class MfracCascadeTest extends TestCase
{
    public function testInlineMfracChildrenRenderAtScriptSize(): void
    {
        // `<math>` defaults to inline. Inside an inline mfrac,
        // both numerator and denominator should switch to script-
        // sized font - so the content stream emits a Tf at the
        // smaller size between the BT and the digit Tj.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</math>',
            displayBlock: false,
        );
        $sizes = $this->extractFontSizes($bytes);
        $maxSize = max($sizes);
        $minSize = min($sizes);
        // The script size should be measurably smaller than the
        // base (default ratio is 0.7).
        self::assertLessThan(
            $maxSize,
            $minSize,
            'Inline mfrac children should render at a smaller font size',
        );
        self::assertEqualsWithDelta(
            0.7,
            $minSize / $maxSize,
            0.05,
            'Script scale should be ~0.7 of the base',
        );
    }

    public function testDisplayMfracChildrenStayAtFullSize(): void
    {
        // `<math display="block">` enables display style. mfrac
        // still forces displaystyle=false on children but does NOT
        // bump scriptLevel, so the children render at the parent's
        // full size.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '</math>',
            displayBlock: true,
        );
        $sizes = $this->extractFontSizes($bytes);
        // All font sizes should be the same (no script-level scaling).
        self::assertCount(
            1,
            array_unique($sizes),
            'Display mfrac children should NOT shrink',
        );
    }

    public function testNestedInlineMfracChildrenShrinkToScriptscript(): void
    {
        // Outer mfrac: numerator at scriptLevel 1 (~0.7x).
        // Inner mfrac inside that numerator: scriptLevel 2 (~0.55x).
        // The deepest font size should match the scriptscript scale.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac>'
                . '<mfrac><mn>1</mn><mn>2</mn></mfrac>'
                . '<mn>3</mn>'
                . '</mfrac>'
                . '</math>',
            displayBlock: false,
        );
        $sizes = $this->extractFontSizes($bytes);
        self::assertGreaterThanOrEqual(3, count(array_unique($sizes)));
        $maxSize = max($sizes);
        $minSize = min($sizes);
        // scriptscript scale defaults to 0.55.
        self::assertEqualsWithDelta(
            0.55,
            $minSize / $maxSize,
            0.05,
        );
    }

    public function testMfracDisplaystyleAttributeOverridesParentCascade(): void
    {
        // Even inside inline mode, explicit `displaystyle="true"` on
        // the inner mfrac should keep its children at full size.
        // Outer mfrac in inline puts its numerator at script size,
        // but the inner mfrac (with explicit displaystyle) renders
        // at the outer's script size - not further shrunk.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mfrac>'
                . '<mfrac displaystyle="true"><mn>1</mn><mn>2</mn></mfrac>'
                . '<mn>3</mn>'
                . '</mfrac>'
                . '</math>',
            displayBlock: false,
        );
        $sizes = $this->extractFontSizes($bytes);
        // Should be at most 2 distinct sizes: base + script-of-outer.
        self::assertLessThanOrEqual(
            2,
            count(array_unique($sizes)),
            'Inner displaystyle=true should NOT further shrink the children',
        );
    }

    private function render(string $xml, bool $displayBlock): string
    {
        // Caller supplies the full `<math>` payload so the test can
        // set display attribute precisely.
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        unset($displayBlock); // present for explicitness; doc carries it
        return $writer->toBytes();
    }

    /**
     * Extract all font sizes set via Tf operators in the content
     * stream. Order preserved.
     *
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
