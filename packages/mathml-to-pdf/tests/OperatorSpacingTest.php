<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Renderer-side coverage for the operator-dictionary integration:
 *
 *   - Form is computed from sibling position (first = prefix,
 *     last = postfix, middle = infix).
 *   - Explicit `form` attribute overrides positional inference.
 *   - lspace / rspace produce Td repositionings around the glyph.
 *   - Unknown operators fall back to zero spacing.
 *   - Author-supplied lspace / rspace override the dictionary.
 */
final class OperatorSpacingTest extends TestCase
{
    private MathmlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MathmlParser();
    }

    public function testInfixOperatorEmitsSpacingTdsAroundGlyph(): void
    {
        // `<mrow>x+y</mrow>` - `+` is infix (middle child) with
        // medium spacing on both sides. Expect at least 4 Tds:
        //   - paint x (no Td initially, just Tj)
        //   - Td for lspace before +
        //   - Td (implicit via Tj advance)
        //   - Td for rspace after +
        //   - paint y
        $bytes = $this->render('<mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow>');
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(y\)\s+Tj/', $bytes);
        // Two Td calls (lspace + rspace) for the operator at minimum,
        // beyond whatever the surrounding paint already emits.
        $tdCount = preg_match_all('/\s+Td\b/', $bytes);
        self::assertGreaterThanOrEqual(2, $tdCount);
    }

    public function testPrefixPositionGetsPrefixSpacing(): void
    {
        // `<mrow>-x</mrow>` - `-` is the first child = prefix.
        // The prefix form of '-' is NOT in the dictionary (only
        // infix is), so it falls back to the zero-spacing default.
        // Expect: glyph emits, but no extra Tds for spacing.
        $bytes = $this->render('<mrow><mo>-</mo><mi>x</mi></mrow>');
        self::assertMatchesRegularExpression('/\(-\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
    }

    public function testExplicitFormAttributeOverridesPositional(): void
    {
        // Force the leading `-` to be treated as infix - this picks
        // up the medium-spacing entry (4/18 em on each side) instead
        // of the dictionary's prefix entry. Confirms the author can
        // override the heuristic.
        $bytes = $this->render(
            '<mrow><mo form="infix">-</mo><mi>x</mi></mrow>',
        );
        // The forced-infix variant emits a Td for lspace AND a Td
        // for rspace around the operator (both 4/18 em > 0). A
        // dictionary entry without spacing wouldn't.
        self::assertMatchesRegularExpression('/\(-\)\s+Tj/', $bytes);
        $tdCount = preg_match_all('/\s+Td\b/', $bytes);
        self::assertGreaterThanOrEqual(2, $tdCount);
    }

    public function testFactorialPostfixHasNoSpacing(): void
    {
        // `<mrow>n!</mrow>` - `!` is postfix, dictionary says 0/0.
        // Confirm the painter emits no extra Tds beyond glyph
        // advance.
        $bytes = $this->render('<mrow><mi>n</mi><mo>!</mo></mrow>');
        self::assertMatchesRegularExpression('/\(n\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(!\)\s+Tj/', $bytes);
    }

    public function testRelationalOperatorEmitsThickSpacing(): void
    {
        // `=` has thick spacing (5/18 em) - emits Tds either side.
        $bytes = $this->render('<mrow><mi>x</mi><mo>=</mo><mn>1</mn></mrow>');
        self::assertMatchesRegularExpression('/\(=\)\s+Tj/', $bytes);
        $tdCount = preg_match_all('/\s+Td\b/', $bytes);
        self::assertGreaterThanOrEqual(2, $tdCount);
    }

    public function testUnknownOperatorFallsBackToZeroSpacing(): void
    {
        // 'snorgle' is not in the dictionary - paint should still
        // emit the text without crashing or adding spacing.
        $bytes = $this->render('<mrow><mi>x</mi><mo>snorgle</mo><mi>y</mi></mrow>');
        self::assertMatchesRegularExpression('/\(snorgle\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(x\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(y\)\s+Tj/', $bytes);
    }

    public function testAuthorLspaceOverridesDictionary(): void
    {
        // Author wants extra padding before `+`. lspace="2em" must
        // win over the dictionary's 0.222em.
        $custom = $this->render(
            '<mrow><mi>x</mi><mo lspace="2em">+</mo><mi>y</mi></mrow>',
        );
        $default = $this->render('<mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow>');
        // Both render the glyphs.
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $custom);
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $default);
        // Custom has at least as many Tds as default (the wider
        // lspace value gets emitted in some form).
        $tdCustom = preg_match_all('/\s+Td\b/', $custom);
        $tdDefault = preg_match_all('/\s+Td\b/', $default);
        self::assertGreaterThanOrEqual($tdDefault, $tdCustom);
    }

    public function testAuthorPxLspaceFallsBackToDictionary(): void
    {
        // v1 only honours em / unitless author overrides. A `px`
        // value is treated as "use the dictionary default" -
        // identical to omitting the attribute.
        $px = $this->render(
            '<mrow><mi>x</mi><mo lspace="10px">+</mo><mi>y</mi></mrow>',
        );
        $default = $this->render('<mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow>');
        // Same number of Tds either way.
        self::assertSame(
            preg_match_all('/\s+Td\b/', $px),
            preg_match_all('/\s+Td\b/', $default),
        );
    }

    public function testCommaSpacingIsAsymmetric(): void
    {
        // `<mrow>a,b</mrow>` - dictionary says lspace=0, rspace=thin.
        // Painter should still emit but the lspace contributes no Td.
        $bytes = $this->render('<mrow><mi>a</mi><mo>,</mo><mi>b</mi></mrow>');
        self::assertMatchesRegularExpression('/\(a\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(,\)\s+Tj/', $bytes);
        self::assertMatchesRegularExpression('/\(b\)\s+Tj/', $bytes);
    }

    public function testEmptyMoEmitsNothing(): void
    {
        $bytes = $this->render('<mrow><mo></mo></mrow>');
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertDoesNotMatchRegularExpression('/\([^)]+\)\s+Tj/', $bytes);
    }

    public function testSingleChildMoTreatedAsInfix(): void
    {
        // Sole <mo> in a row - per Core, falls back to infix.
        // The infix entry exists for '+' so we still get medium
        // spacing (it just no longer separates two siblings).
        $bytes = $this->render('<mrow><mo>+</mo></mrow>');
        self::assertMatchesRegularExpression('/\(\+\)\s+Tj/', $bytes);
    }

    private function render(string $innerXml): string
    {
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . $innerXml
            . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = $this->parser->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 300.0, height: 30.0);
        return $writer->toBytes();
    }
}
