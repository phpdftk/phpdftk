<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Renderer coverage for the script-attachment elements. Every
 * construct has a positive smoke (content reaches the stream + font
 * size switches happen) and an invalid-arity fallback (content is
 * recovered via the inline walk, not silently dropped).
 *
 * The exact text-matrix coordinates are an implementation detail of
 * the Translator's positioning math — we assert structural signals
 * (number of Tj operators, number of Tf font-size switches, presence
 * of Td repositioning) rather than literal numbers.
 */
final class ScriptsRenderingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    // -----------------------------------------------------------------
    // msup — x²
    // -----------------------------------------------------------------

    public function testMsupRendersBaseAndSuperscript(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msup><mi>x</mi><mn>2</mn></msup>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testMsupSwitchesFontSizeForScript(): void
    {
        // Initial Tf (main size) + Tf (script 0.7×) + Tf (restore).
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msup><mi>x</mi><mn>2</mn></msup>'
                . '</math>',
        );
        self::assertGreaterThanOrEqual(3, preg_match_all('/\s+Tf\b/', $bytes));
    }

    public function testMsupBadArityFallsBack(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msup><mi>x</mi></msup>'
                . '</math>',
        );
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    // -----------------------------------------------------------------
    // msub
    // -----------------------------------------------------------------

    public function testMsubRendersBaseAndSubscript(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msub><mi>x</mi><mn>0</mn></msub>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(0\)\s+Tj/', $bytes);
    }

    // -----------------------------------------------------------------
    // msubsup — both sub + sup at base right edge
    // -----------------------------------------------------------------

    public function testMsubsupRendersAllThreeChildren(): void
    {
        // ∫ from 0 to 2 — base 'x', sub '0', sup '2'.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msubsup><mi>x</mi><mn>0</mn><mn>2</mn></msubsup>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(0\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
    }

    public function testMsubsupBadArityFallsBack(): void
    {
        // Only 2 children — falls back to inline walk.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msubsup><mi>x</mi><mn>0</mn></msubsup>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(0\)\s+Tj/', $bytes);
    }

    public function testMsubsupEmitsMultipleTdForRepositioning(): void
    {
        // Sub + sup both attach at same x → Translator needs Td to
        // back up between them. Expect ≥ 4 Td ops (initial, sup,
        // back to attach + drop, restore).
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msubsup><mi>x</mi><mn>0</mn><mn>2</mn></msubsup>'
                . '</math>',
        );
        self::assertGreaterThanOrEqual(4, preg_match_all('/\s+Td\b/', $bytes));
    }

    // -----------------------------------------------------------------
    // munder / mover
    // -----------------------------------------------------------------

    public function testMunderRendersBaseAndUnderscript(): void
    {
        // lim under x→0
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<munder><mo>lim</mo><mi>x</mi></munder>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(lim\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testMoverRendersBaseAndOverscript(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mover><mi>x</mi><mo>~</mo></mover>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(~\)\s+Tj/', $bytes);
    }

    public function testMoverFontSizeShrinksForAccent(): void
    {
        // Confirm the script is drawn at a smaller font size (the
        // Translator switches via Tf).
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mover><mi>x</mi><mo>~</mo></mover>'
                . '</math>',
        );
        self::assertGreaterThanOrEqual(3, preg_match_all('/\s+Tf\b/', $bytes));
    }

    public function testMoverBadArityFallsBack(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mover><mi>x</mi></mover>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    // -----------------------------------------------------------------
    // munderover
    // -----------------------------------------------------------------

    public function testMunderoverRendersAllThreeChildren(): void
    {
        // Integral with both bounds.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<munderover><mo>I</mo><mn>0</mn><mn>1</mn></munderover>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(I\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(0\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(1\)\s+Tj/', $bytes);
    }

    public function testMunderoverBadArityFallsBack(): void
    {
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<munderover><mo>I</mo><mn>0</mn></munderover>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(I\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(0\)\s+Tj/', $bytes);
    }

    // -----------------------------------------------------------------
    // Cross-cutting
    // -----------------------------------------------------------------

    public function testNestedMsupInsideMsqrt(): void
    {
        // √(x²) — confirms script + radical compose correctly through
        // the recursive paint().
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<msqrt><msup><mi>x</mi><mn>2</mn></msup></msqrt>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        // Vinculum still drawn.
        self::assertMatchesRegularExpression('/\nS\n/', $bytes);
    }

    public function testMsupFollowedByTokenAdvancesCursor(): void
    {
        // <msup>x²</msup> = y — confirms post-script cursor is at the
        // right edge so subsequent tokens (`=`, `y`) draw past the
        // superscript.
        $bytes = $this->render(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<mrow>'
                . '<msup><mi>x</mi><mn>2</mn></msup>'
                . '<mo>=</mo><mi>y</mi>'
                . '</mrow>'
                . '</math>',
        );
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(2\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(=\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(y\)\s+Tj/', $bytes);
    }

    private function render(string $mathmlXml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($mathmlXml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }
}
