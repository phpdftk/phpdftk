<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for mathcolor / mathbackground attribute
 * rendering, plus merror default styling.
 *
 * We look at the content stream for the `rg` (fill colour) and
 * `re ... f` (rectangle + fill) operators. Absent any colour
 * directives the standard renderer emits no `rg` operators.
 */
final class MathcolorMathbackgroundRenderTest extends TestCase
{
    public function testMathcolorEmitsFillColorOperator(): void
    {
        $bytes = $this->render(
            '<mi mathcolor="red">x</mi>',
        );
        // 'red' is 1 0 0 rg. Expect a rg operator with 1 0 0 to
        // appear in the stream.
        self::assertMatchesRegularExpression(
            '/1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg/',
            $bytes,
        );
    }

    public function testMathcolorRestoresAfterSubtree(): void
    {
        // After the coloured <mi>, a sibling <mn> should be
        // emitted under the default (black, 0 0 0 rg).
        $bytes = $this->render(
            '<mi mathcolor="red">x</mi><mn>1</mn>',
        );
        // Pick the LAST rg operator and confirm it's the
        // restore-to-black call.
        preg_match_all(
            '/([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+rg/',
            $bytes,
            $m,
        );
        $count = count($m[0]);
        self::assertGreaterThanOrEqual(
            2,
            $count,
            'expected at least 2 rg operators (set + restore)',
        );
        // Last one should be 0 0 0 (black restore).
        $last = $count - 1;
        self::assertEqualsWithDelta(0.0, (float) $m[1][$last], 0.001);
        self::assertEqualsWithDelta(0.0, (float) $m[2][$last], 0.001);
        self::assertEqualsWithDelta(0.0, (float) $m[3][$last], 0.001);
    }

    public function testMathbackgroundDrawsRectangle(): void
    {
        $bytes = $this->render(
            '<mi mathbackground="yellow">x</mi>',
        );
        // 1 1 0 rg + a rectangle + f (fill).
        self::assertMatchesRegularExpression(
            '/1(?:\.0+)?\s+1(?:\.0+)?\s+0(?:\.0+)?\s+rg/',
            $bytes,
        );
        self::assertStringContainsString(' re', $bytes);
        self::assertMatchesRegularExpression('/\bf\b/', $bytes);
    }

    public function testMerrorDefaultsToRedAndSalmon(): void
    {
        $bytes = $this->render('<merror><mtext>X</mtext></merror>');
        // Red foreground (1 0 0 rg).
        self::assertMatchesRegularExpression(
            '/1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+rg/',
            $bytes,
        );
        // Salmon background (250/255 ≈ 0.98, 128/255 ≈ 0.50,
        // 114/255 ≈ 0.447). Just check that a non-default rg
        // exists with first component near 0.98.
        preg_match_all(
            '/(0\.9[78]\d*)\s+(0\.5\d*)\s+(0\.4\d*)\s+rg/',
            $bytes,
            $m,
        );
        self::assertGreaterThan(0, count($m[0]), 'salmon rg expected');
        // The content text should still render.
        self::assertMatchesRegularExpression('/\(X\)\s+Tj/', $bytes);
    }

    public function testMerrorExplicitOverrideWinsOverDefault(): void
    {
        $bytes = $this->render(
            '<merror mathcolor="blue" mathbackground="yellow">'
            . '<mtext>X</mtext></merror>',
        );
        // Blue foreground (0 0 1 rg).
        self::assertMatchesRegularExpression(
            '/0(?:\.0+)?\s+0(?:\.0+)?\s+1(?:\.0+)?\s+rg/',
            $bytes,
        );
    }

    public function testNoColorAttrsLeavesNoRgOperator(): void
    {
        $bytes = $this->render('<mi>x</mi>');
        // No mathcolor/mathbackground anywhere in the doc - no
        // rg operator should be emitted.
        self::assertDoesNotMatchRegularExpression('/\brg\b/', $bytes);
    }

    public function testInvalidColorIsNoOp(): void
    {
        $bytes = $this->render('<mi mathcolor="banana">x</mi>');
        // 'banana' fails to parse - the painter should fall
        // through to the no-color path.
        self::assertDoesNotMatchRegularExpression('/\brg\b/', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
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
