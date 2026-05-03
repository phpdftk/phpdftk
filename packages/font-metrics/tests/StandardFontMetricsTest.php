<?php declare(strict_types=1);

namespace Phpdftk\FontMetrics\Tests;

use PHPUnit\Framework\TestCase;
use Phpdftk\FontMetrics\StandardFontMetrics;
use Phpdftk\FontMetrics\AfmData;

class StandardFontMetricsTest extends TestCase
{
    private static array $standardFonts = [
        'Helvetica',
        'Helvetica-Bold',
        'Helvetica-Oblique',
        'Helvetica-BoldOblique',
        'Times-Roman',
        'Times-Bold',
        'Times-Italic',
        'Times-BoldItalic',
        'Courier',
        'Courier-Bold',
        'Courier-Oblique',
        'Courier-BoldOblique',
        'Symbol',
        'ZapfDingbats',
    ];

    public function testAllFourteenFontsReturnAfmData(): void
    {
        foreach (self::$standardFonts as $font) {
            $afm = StandardFontMetrics::get($font);
            $this->assertInstanceOf(AfmData::class, $afm, "Font $font did not return AfmData");
        }
    }

    public function testHelveticaAWidth(): void
    {
        $afm = StandardFontMetrics::get('Helvetica');
        $this->assertSame(667, $afm->getWidth('A'));
    }

    public function testHelveticaSpaceWidth(): void
    {
        $afm = StandardFontMetrics::get('Helvetica');
        $this->assertSame(278, $afm->getWidth('space'));
    }

    public function testHelveticaMetrics(): void
    {
        $afm = StandardFontMetrics::get('Helvetica');
        $this->assertSame(718.0, $afm->ascender);
        $this->assertSame(-207.0, $afm->descender);
        $this->assertSame(718.0, $afm->capHeight);
        $this->assertSame(0.0, $afm->italicAngle);
    }

    public function testHelveticaObliqueItalicAngle(): void
    {
        $afm = StandardFontMetrics::get('Helvetica-Oblique');
        $this->assertSame(-12.0, $afm->italicAngle);
        // Same widths as Helvetica
        $this->assertSame(667, $afm->getWidth('A'));
    }

    public function testHelveticaBoldOblique(): void
    {
        $afm = StandardFontMetrics::get('Helvetica-BoldOblique');
        $this->assertSame(-12.0, $afm->italicAngle);
        // Same widths as Helvetica-Bold
        $this->assertSame(722, $afm->getWidth('A'));
    }

    public function testCourierAWidth(): void
    {
        $afm = StandardFontMetrics::get('Courier');
        $this->assertSame(600, $afm->getWidth('A'));
    }

    public function testCourierAllGlyphsWidth600(): void
    {
        $afm = StandardFontMetrics::get('Courier');
        $testGlyphs = ['A', 'Z', 'a', 'z', 'zero', 'nine', 'space', 'period'];
        foreach ($testGlyphs as $glyph) {
            $this->assertSame(600, $afm->getWidth($glyph), "Courier glyph '$glyph' should be 600");
        }
    }

    public function testCourierMissingWidth(): void
    {
        $afm = StandardFontMetrics::get('Courier');
        $this->assertSame(600, $afm->getWidth('nonexistentglyph'));
        $this->assertSame(600.0, $afm->missingWidth);
    }

    public function testHelveticaMissingWidth(): void
    {
        $afm = StandardFontMetrics::get('Helvetica');
        $this->assertSame(278, $afm->getWidth('nonexistentglyph'));
        $this->assertSame(278.0, $afm->missingWidth);
    }

    public function testTimesRomanMetrics(): void
    {
        $afm = StandardFontMetrics::get('Times-Roman');
        $this->assertSame(683.0, $afm->ascender);
        $this->assertSame(-217.0, $afm->descender);
        $this->assertSame(722, $afm->getWidth('A'));
        $this->assertSame(250, $afm->getWidth('space'));
    }

    public function testTimesItalicAngle(): void
    {
        $afm = StandardFontMetrics::get('Times-Italic');
        $this->assertSame(-15.5, $afm->italicAngle);
    }

    public function testTimesBoldItalicAngle(): void
    {
        $afm = StandardFontMetrics::get('Times-BoldItalic');
        $this->assertSame(-15.0, $afm->italicAngle);
    }

    public function testSymbolFont(): void
    {
        $afm = StandardFontMetrics::get('Symbol');
        $this->assertSame(0.0, $afm->ascender);
        $this->assertSame(722, $afm->getWidth('Alpha'));
        $this->assertSame(250, $afm->getWidth('space'));
    }

    public function testZapfDingbats(): void
    {
        $afm = StandardFontMetrics::get('ZapfDingbats');
        $this->assertSame(0.0, $afm->ascender);
        $this->assertSame(278, $afm->getWidth('space'));
    }

    public function testFontBBox(): void
    {
        $afm = StandardFontMetrics::get('Helvetica');
        $this->assertSame([-166, -225, 1000, 931], $afm->fontBBox);
    }

    public function testUnknownFontThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StandardFontMetrics::get('NotARealFont');
    }

    public function testCourierVariants(): void
    {
        $variants = ['Courier', 'Courier-Bold', 'Courier-Oblique', 'Courier-BoldOblique'];
        foreach ($variants as $variant) {
            $afm = StandardFontMetrics::get($variant);
            $this->assertSame(600, $afm->getWidth('A'), "$variant 'A' should be 600");
        }
    }

    public function testHelveticaBoldWidths(): void
    {
        $afm = StandardFontMetrics::get('Helvetica-Bold');
        $this->assertSame(722, $afm->getWidth('A'));
        $this->assertSame(611, $afm->getWidth('b'));
        $this->assertSame(140.0, $afm->stemV);
    }
}
