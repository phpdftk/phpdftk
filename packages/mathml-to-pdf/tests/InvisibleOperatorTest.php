<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlGlyphMetrics;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Unicode's invisible operators — U+2061 FUNCTION APPLICATION,
 * U+2062 INVISIBLE TIMES, U+2063 INVISIBLE SEPARATOR, U+2064
 * INVISIBLE PLUS — carry mathematical meaning and no glyph. They
 * exist so `f(x)` and `2x` can say "apply" and "multiply" without
 * drawing anything.
 *
 * The painter encodes text through WinAnsi for the standard faces,
 * and WinAnsi has no room for them, so each one came out as the
 * encoder's `?` substitute: `1⁢2⁢3` rendered as `1?2?3`. Worse than
 * a missing glyph, because the `?` also took up width and shifted
 * everything after it.
 */
final class InvisibleOperatorTest extends TestCase
{
    /** @return iterable<string, array{0: string}> */
    public static function invisibleOperators(): iterable
    {
        yield 'U+2061 function application' => ["\u{2061}"];
        yield 'U+2062 invisible times' => ["\u{2062}"];
        yield 'U+2063 invisible separator' => ["\u{2063}"];
        yield 'U+2064 invisible plus' => ["\u{2064}"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invisibleOperators')]
    public function testInvisibleOperatorDrawsNoSubstituteGlyph(string $operator): void
    {
        $bytes = $this->render("<mrow><mn>1</mn><mo>$operator</mo><mn>2</mn></mrow>");
        self::assertStringNotContainsString(
            '(?)',
            $bytes,
            'an invisible operator must not fall back to the encoder substitute',
        );
        self::assertDoesNotMatchRegularExpression(
            '/\((?:[^()]*\?)[^()]*\)\s*Tj/',
            $bytes,
            'no shown run may contain a substitute glyph',
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invisibleOperators')]
    public function testInvisibleOperatorOccupiesNoWidth(string $operator): void
    {
        self::assertSame(
            0.0,
            MathmlGlyphMetrics::measure($operator, 16.0),
            'an invisible operator has no advance of its own',
        );
    }

    public function testInvisibleOperatorsDoNotShiftTheDigitsAroundThem(): void
    {
        // The WPT reftest's shape: `1⁡2⁢3⁣4⁤5` has to render exactly
        // like `12345`.
        $withOperators = $this->render(
            '<mrow><mn>1</mn>'
            . "<mo>\u{2061}</mo><mn>2</mn>"
            . "<mo>\u{2062}</mo><mn>3</mn>"
            . "<mo>\u{2063}</mo><mn>4</mn>"
            . "<mo>\u{2064}</mo><mn>5</mn>"
            . '</mrow>',
        );
        $plain = $this->render(
            '<mrow><mn>1</mn><mn>2</mn><mn>3</mn><mn>4</mn><mn>5</mn></mrow>',
        );
        self::assertSame($this->shownText($plain), $this->shownText($withOperators));
        self::assertEqualsWithDelta(
            $this->intrinsicWidth('<mrow><mn>1</mn><mn>2</mn></mrow>'),
            $this->intrinsicWidth("<mrow><mn>1</mn><mo>\u{2062}</mo><mn>2</mn></mrow>"),
            0.01,
            'the measured width must not grow either',
        );
    }

    public function testInvisibleCharactersInATokenAreAlsoDropped(): void
    {
        // Not just `<mo>`: the same codepoint inside `<mi>` / `<mtext>`
        // is the same invisible character.
        $bytes = $this->render("<mi>x\u{2062}y</mi>");
        self::assertStringNotContainsString('?', $this->shownText($bytes));
    }

    // -----------------------------------------------------------------
    // Guards: ordinary operators still render and still take space.
    // -----------------------------------------------------------------

    public function testVisibleOperatorStillRenders(): void
    {
        self::assertStringContainsString(
            '+',
            $this->shownText($this->render('<mrow><mn>1</mn><mo>+</mo><mn>2</mn></mrow>')),
        );
    }

    public function testGenuinelyUnencodableCharacterStillSubstitutes(): void
    {
        // The fix must be scoped to the four invisible operators, not
        // a blanket "drop anything WinAnsi cannot encode" — a real
        // glyph that the standard face lacks should still show the
        // substitute so the gap is visible rather than silent.
        self::assertGreaterThan(0.0, MathmlGlyphMetrics::measure("\u{2211}", 16.0));
    }

    private function intrinsicWidth(string $innerXml): float
    {
        $writer = new PdfWriter(compressStreams: false);
        $renderer = new MathmlRenderer($writer->addPage(), $writer);
        $document = (new MathmlParser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">' . $innerXml . '</math>',
        );

        return $renderer->intrinsicSize($document, 16.0)[0];
    }

    /** Concatenated content of every literal show-text operator. */
    private function shownText(string $bytes): string
    {
        preg_match_all('/\(((?:\\\\.|[^()\\\\])*)\)\s*Tj/', $bytes, $matches);

        return implode('', array_map('stripslashes', $matches[1]));
    }

    private function render(string $innerXml): string
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $document = (new MathmlParser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">' . $innerXml . '</math>',
        );
        $renderer->draw($document, 100.0, 600.0, 300.0, 120.0, fontSize: 16.0);

        return $writer->toBytes();
    }
}
