<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 3R+5 — SVG 2 §11.6 per-glyph positioning. The list-valued `x` / `y` /
 * `rotate` attributes on `<text>` each control the corresponding
 * positioning of successive glyphs. Going past the list length lets the
 * remaining glyphs ride the natural advance from the last positioned
 * glyph (sticky).
 */
final class PerGlyphTextTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    private function paint(string $svg): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse($svg);
        $this->translator->paint($doc, $stream, $page, $writer);
        return implode("\n", $stream->getOperators());
    }

    public function testMultipleXValuesPositionEachGlyph(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10 30 50" y="20">ABC</text></svg>',
        );
        // One Tm per glyph plus one Tj per character.
        self::assertStringContainsString('1 0 0 1 10 20 Tm', $ops);
        self::assertStringContainsString('(A) Tj', $ops);
        self::assertStringContainsString('1 0 0 1 30 20 Tm', $ops);
        self::assertStringContainsString('(B) Tj', $ops);
        self::assertStringContainsString('1 0 0 1 50 20 Tm', $ops);
        self::assertStringContainsString('(C) Tj', $ops);
    }

    public function testTrailingCharactersBatchAfterPositionedGlyphs(): void
    {
        // x has 2 values but content has 5 chars; after the first two
        // glyphs auto-advance handles the remaining "CDE" as one Tj.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10 30" y="20">ABCDE</text></svg>',
        );
        // Two Tm + two single-char Tj for "A" and "B".
        self::assertStringContainsString('1 0 0 1 10 20 Tm', $ops);
        self::assertStringContainsString('1 0 0 1 30 20 Tm', $ops);
        self::assertStringContainsString('(A) Tj', $ops);
        self::assertStringContainsString('(B) Tj', $ops);
        // Remaining batched as one Tj.
        self::assertStringContainsString('(CDE) Tj', $ops);
    }

    public function testMultipleYValuesPositionEachGlyph(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10" y="10 20 30">ABC</text></svg>',
        );
        // x is sticky at 10; y varies per glyph.
        self::assertStringContainsString('1 0 0 1 10 10 Tm', $ops);
        self::assertStringContainsString('1 0 0 1 10 20 Tm', $ops);
        self::assertStringContainsString('1 0 0 1 10 30 Tm', $ops);
    }

    public function testMissingXFallsBackToLastExplicitValue(): void
    {
        // y has 3 values, x has 1 — first glyph uses x=10, subsequent
        // glyphs inherit the previous x (sticky) since we don't have
        // font metrics for the proper auto-advance fallback at 3R+5.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10" y="10 20 30">ABC</text></svg>',
        );
        // All three glyphs land at x=10.
        $tms = preg_match_all('!1 0 0 1 10 (\d+) Tm!', $ops, $m);
        self::assertSame(3, $tms);
        self::assertSame(['10', '20', '30'], $m[1]);
    }

    public function testSingleRotateUsesPerGlyphPath(): void
    {
        // A scalar rotate triggers the per-glyph code path so the
        // rotation actually reaches the text matrix. With one
        // character we still emit just one Tm.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="50" y="50" rotate="45">A</text></svg>',
        );
        // cos(45°) = sin(45°) ≈ 0.707107. Without flip: a=cos, b=sin,
        // c=-sin, d=cos.
        self::assertMatchesRegularExpression(
            '!0\.7071067812 0\.7071067812 -0\.7071067812 0\.7071067812 50 50 Tm!',
            $ops,
        );
    }

    public function testMultipleRotateValuesRotateEachGlyph(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10 20 30" y="50" rotate="0 90 -90">ABC</text></svg>',
        );
        // First glyph: rotate 0 → identity rotation.
        self::assertStringContainsString('1 0 0 1 10 50 Tm', $ops);
        // Second: rotate 90 → cos=0, sin=1. Note floating-point noise
        // means cos isn't exactly 0; the serialised form may have
        // small numeric residue but it's well below 1e-6 — we anchor
        // on the structure rather than the exact bytes.
        self::assertMatchesRegularExpression(
            '![\d.e+-]+ 1 -1 [\d.e+-]+ 20 50 Tm!',
            $ops,
        );
        // Third: rotate -90 → cos=0, sin=-1.
        self::assertMatchesRegularExpression(
            '![\d.e+-]+ -1 1 [\d.e+-]+ 30 50 Tm!',
            $ops,
        );
    }

    public function testTextFlipCompensationFlipsPerGlyphRotation(): void
    {
        // Under SvgRenderer the outer cm has y-flip; the per-glyph
        // path uses the flip-aware Tm formula
        // `[cos sin sin -cos x y]` (instead of `[cos sin -sin cos x y]`).
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $svg = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10 30" y="50">AB</text></svg>',
        );
        $this->translator->paint($svg, $stream, $page, $writer, compensateTextFlip: true);
        $ops = implode("\n", $stream->getOperators());
        // No rotation (rotate=0) + flip → Tm = [1 0 0 -1 x y].
        self::assertStringContainsString('1 0 0 -1 10 50 Tm', $ops);
        self::assertStringContainsString('1 0 0 -1 30 50 Tm', $ops);
    }

    public function testSinglePositionUnchangedByPerGlyphRefactor(): void
    {
        // A bare `<text x="…" y="…">` with no rotate stays on the
        // cheaper single-Tj / Td path (no Tm overhead per glyph).
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10" y="20">Hello</text></svg>',
        );
        self::assertStringContainsString('10 20 Td', $ops);
        self::assertStringContainsString('(Hello) Tj', $ops);
        // Only one Tj for the whole word — not per glyph.
        self::assertSame(1, substr_count($ops, ' Tj'));
    }

    public function testEmptyContentEmitsNothing(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10 20 30" y="40"></text></svg>',
        );
        // No BT/Tj/ET sequence at all.
        self::assertStringNotContainsString('BT', $ops);
    }

    public function testDxOffsetsAccumulateOnStickyX(): void
    {
        // dx accumulates onto the running sticky x — matching common
        // SVG renderer behaviour (each unspecified glyph's position
        // inherits the previous glyph's *adjusted* position). x=10
        // stays sticky, dx="0 5 10" shifts each subsequent glyph by
        // an extra 5 / 10 user units relative to the previous one.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10" y="20" dx="0 5 10">ABC</text></svg>',
        );
        // A: 10 + 0 = 10. B: 10 + 5 = 15. C: 15 + 10 = 25.
        self::assertStringContainsString('1 0 0 1 10 20 Tm', $ops);
        self::assertStringContainsString('1 0 0 1 15 20 Tm', $ops);
        self::assertStringContainsString('1 0 0 1 25 20 Tm', $ops);
    }

    public function testDyOffsetsAccumulateOnStickyY(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10" y="20" dy="0 -5 -10">ABC</text></svg>',
        );
        // A: 20 + 0 = 20. B: 20 + -5 = 15. C: 15 + -10 = 5.
        self::assertStringContainsString('1 0 0 1 10 20 Tm', $ops);
        self::assertStringContainsString('1 0 0 1 10 15 Tm', $ops);
        self::assertStringContainsString('1 0 0 1 10 5 Tm', $ops);
    }

    public function testDxDyForcesPerGlyphPath(): void
    {
        // Bare `<text x y dx>` without multi-valued x should still go
        // through the per-glyph path — the dx list itself signals
        // per-glyph positioning.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10" y="20" dx="0 5">AB</text></svg>',
        );
        self::assertStringContainsString(' Tm', $ops);
        // Two per-glyph showText, not the cheap single-Tj path.
        self::assertGreaterThanOrEqual(2, substr_count($ops, ' Tj'));
    }

    public function testDxDyCompositeWithXY(): void
    {
        // x="10 30" + dx="0 5" — first glyph at 10, second at 30+5=35.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10 30" y="20 40" dx="0 5" dy="0 -3">AB</text></svg>',
        );
        self::assertStringContainsString('1 0 0 1 10 20 Tm', $ops);
        self::assertStringContainsString('1 0 0 1 35 37 Tm', $ops);
    }
}
