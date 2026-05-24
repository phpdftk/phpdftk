<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Writer\ListBlock;
use Phpdftk\Pdf\Writer\ListStyle;
use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class ListTest extends TestCase
{
    use QpdfValidationTrait;

    public function testBulletListRendersEachItem(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addList(['Apples', 'Bananas', 'Cherries']);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(Apples)', $bytes);
        self::assertStringContainsString('(Bananas)', $bytes);
        self::assertStringContainsString('(Cherries)', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testBulletGlyphIsEncodedIntoOutput(): void
    {
        // The default bullet glyph (U+2022) maps to WinAnsi 0x95 and
        // is emitted as a literal byte inside the showText parens.
        $pdf = new Pdf(compressStreams: false);
        $pdf->addList(['Item one']);

        $bytes = $pdf->toBytes();
        $bulletByte = chr(0x95);
        self::assertStringContainsString("({$bulletByte}) Tj", $bytes);
    }

    public function testNumberedListEmitsRunningIndex(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addNumberedList(['First', 'Second', 'Third']);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(1.)', $bytes);
        self::assertStringContainsString('(2.)', $bytes);
        self::assertStringContainsString('(3.)', $bytes);
    }

    public function testNumberedListNumberSuffixIsConfigurable(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addNumberedList(
            ['Alpha', 'Beta'],
            new ListStyle(numberSuffix: ')'),
        );

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(1\\))', $bytes);
        self::assertStringContainsString('(2\\))', $bytes);
    }

    public function testLongItemWrapsToMultipleLines(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $longItem = 'The quick brown fox jumps over the lazy dog repeatedly, while a thoughtful narrator describes the scene in tremendous detail and at considerable length.';
        $pdf->addList([$longItem]);

        $bytes = $pdf->toBytes();
        // A wrapped item emits multiple text showText calls (bullet +
        // first line + subsequent lines).
        self::assertGreaterThanOrEqual(3, substr_count($bytes, ') Tj'));
    }

    public function testLongListAutoPaginates(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $items = [];
        for ($i = 1; $i <= 80; $i++) {
            $items[] = "Item {$i} of a deliberately long list to force pagination across pages.";
        }
        $pdf->addList($items);

        $bytes = $pdf->toBytes();
        self::assertGreaterThanOrEqual(2, substr_count($bytes, "/Type /Page\n"));
        // All items still rendered
        self::assertStringContainsString('Item 1 of', $bytes);
        self::assertStringContainsString('Item 80 of', $bytes);
    }

    public function testEmptyListIsNoOp(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addPage();
        $cursorReflection = new \ReflectionProperty(Pdf::class, 'cursorY');

        $before = $cursorReflection->getValue($pdf);
        $pdf->addList([]);
        $pdf->addNumberedList([]);
        $after = $cursorReflection->getValue($pdf);

        self::assertSame($before, $after);
    }

    public function testListStyleBulletAtCyclesByDepth(): void
    {
        $style = new ListStyle(bulletGlyphs: ['a', 'b', 'c']);
        self::assertSame('a', $style->bulletAt(0));
        self::assertSame('b', $style->bulletAt(1));
        self::assertSame('c', $style->bulletAt(2));
        self::assertSame('a', $style->bulletAt(3), 'depth cycles modulo glyph count');
        self::assertSame('b', $style->bulletAt(4));
    }

    public function testListStyleBulletAtHandlesEmptyGlyphList(): void
    {
        $style = new ListStyle(bulletGlyphs: []);
        self::assertSame('•', $style->bulletAt(0));
    }

    public function testWriterPageDrawListPlacesItemsExplicitly(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $page = $pdf->doc()->addPage();
        $font = $pdf->writer()->addFont(new Type1Font(StandardFont::Helvetica));

        $list = new ListBlock(['Red', 'Green', 'Blue'], numbered: true);
        $page->drawList($list, 72.0, 720.0, $font, fontSize: 11.0, maxWidth: 200.0);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('(Red)', $bytes);
        self::assertStringContainsString('(Green)', $bytes);
        self::assertStringContainsString('(Blue)', $bytes);
        self::assertStringContainsString('(1.)', $bytes);
        self::assertStringContainsString('(3.)', $bytes);
    }

    public function testCustomIndentAffectsBulletPosition(): void
    {
        // Doesn't directly inspect coordinates, but we ensure the style
        // is plumbed through without errors and a wider indent produces
        // a smaller wrapped text width (which makes long items wrap
        // earlier — observable as more lines).
        $pdf = new Pdf(compressStreams: false);
        $item = 'Some long text that will fit on a single line normally, but should wrap when the indent is large.';
        $pdf->addList([$item], new ListStyle(indent: 200.0));

        $bytes = $pdf->toBytes();
        self::assertGreaterThanOrEqual(2, substr_count($bytes, ') Tj'));
    }

    public function testNumberedListNumberingIsIndependentPerCall(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addNumberedList(['A', 'B']);
        $pdf->addNumberedList(['C', 'D']);

        $bytes = $pdf->toBytes();
        // Each addNumberedList restarts at 1 — we should see "1." and
        // "2." twice in the document.
        self::assertSame(2, substr_count($bytes, '(1.)'));
        self::assertSame(2, substr_count($bytes, '(2.)'));
    }
}
