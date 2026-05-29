<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 3P `<text>` painter. Requires `?Page` + `?PdfWriter` to register
 * standard fonts; without them the text element silently paints
 * nothing (parser-only callers stay working).
 */
final class TextPainterTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    /**
     * @return array{ops: string, bytes: string}
     */
    private function paintWithWriter(string $svg): array
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse($svg);
        $this->translator->paint($doc, $stream, $page, $writer);
        return [
            'ops' => implode("\n", $stream->getOperators()),
            'bytes' => $writer->toBytes(),
        ];
    }

    public function testTextEmitsBeginTextSetFontShowTextEndText(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="50" y="100">Hello</text></svg>',
        );
        // BT … ET frame.
        self::assertSame(1, substr_count($result['ops'], "\nBT\n"));
        self::assertSame(1, substr_count($result['ops'], "\nET"));
        // Tf with font resource name (F1 for first registered) + size 16.
        self::assertMatchesRegularExpression('!/F\d+ 16 Tf!', $result['ops']);
        // Td positions at (50, 100).
        self::assertStringContainsString('50 100 Td', $result['ops']);
        // Tj shows the encoded text (Helvetica uses WinAnsi, ASCII passes through).
        self::assertStringContainsString('(Hello) Tj', $result['ops']);
    }

    public function testEmptyTextEmitsNothing(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg"><text x="0" y="0"></text></svg>',
        );
        self::assertStringNotContainsString('BT', $result['ops']);
    }

    public function testTextWithoutXYDefaultsToOriginAtZero(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg"><text>X</text></svg>',
        );
        self::assertStringContainsString('0 0 Td', $result['ops']);
    }

    public function testFontSizeAttributeIsHonoured(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-size="24">X</text></svg>',
        );
        self::assertMatchesRegularExpression('!/F\d+ 24 Tf!', $result['ops']);
    }

    public function testGenericFamilySerifSelectsTimes(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-family="serif">X</text></svg>',
        );
        // PDF body should embed a Type1Font with /BaseFont /Times-Roman.
        self::assertStringContainsString('/BaseFont /Times-Roman', $result['bytes']);
    }

    public function testGenericFamilyMonospaceSelectsCourier(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-family="monospace">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Courier', $result['bytes']);
    }

    public function testFontWeightBoldPicksBoldVariant(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-weight="bold">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $result['bytes']);
    }

    public function testNumericFontWeightAtLeast600IsBold(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-weight="700">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $result['bytes']);
    }

    public function testItalicStyleSelectsOblique(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-style="italic">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica-Oblique', $result['bytes']);
    }

    public function testBoldItalicSelectsBoldObliqueVariant(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-weight="bold" font-style="italic">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica-BoldOblique', $result['bytes']);
    }

    public function testFontFamilyListFallsToFirstRecognisedKeyword(): void
    {
        // The author's first family (`Open Sans`) isn't a generic the
        // resolver recognises; it walks the list and picks
        // `sans-serif` → Helvetica.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" font-family="&quot;Open Sans&quot;, sans-serif">X</text></svg>',
        );
        self::assertStringContainsString('/BaseFont /Helvetica', $result['bytes']);
        self::assertStringNotContainsString('/BaseFont /Times', $result['bytes']);
    }

    public function testTspanTextNodesConcatenateIntoParentTextRun(): void
    {
        // 3P uses a single Tj for the combined content of `<text>` and
        // its tspan children. Per-tspan positioning / styling is
        // deferred to a future sub-phase.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="10" y="10">Hello <tspan>world</tspan>!</text></svg>',
        );
        self::assertStringContainsString('(Hello world!) Tj', $result['ops']);
    }

    public function testTextWithoutWriterFallsBackToSilentNoOp(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><text x="0" y="0">X</text></svg>',
        );
        $this->translator->paint($doc, $stream); // no Page / Writer
        $ops = implode("\n", $stream->getOperators());
        self::assertSame('', $ops);
    }

    public function testTextWithExplicitFillEmitsColorBeforeBT(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text x="0" y="0" fill="red">X</text></svg>',
        );
        // Find the `rg` line and the `BT` line and confirm `rg` precedes `BT`.
        $rgPos = strpos($result['ops'], '1 0 0 rg');
        $btPos = strpos($result['ops'], 'BT');
        self::assertNotFalse($rgPos);
        self::assertNotFalse($btPos);
        self::assertLessThan($btPos, $rgPos);
    }

    public function testIntegrationProducesValidPdfWithText(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse(<<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100">
              <text x="10" y="40" font-size="24" fill="blue">Hello, SVG!</text>
            </svg>
            SVG);
        $this->translator->paint($doc, $stream, $page, $writer);
        $bytes = $writer->toBytes();
        self::assertStringStartsWith('%PDF-', $bytes);
        self::assertStringContainsString('%%EOF', $bytes);
        self::assertStringContainsString('/Type /Font', $bytes);
    }
}
