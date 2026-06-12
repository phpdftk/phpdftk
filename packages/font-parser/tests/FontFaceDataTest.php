<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

use Phpdftk\FontParser\FontFaceData;
use Phpdftk\FontParser\OpenTypeData;
use Phpdftk\FontParser\TrueTypeData;
use PHPUnit\Framework\TestCase;

/**
 * Verify the two parsed-font subclasses agree on the shared field
 * shape so {@see FontFaceData}-typed consumers (Shaper, FontResolver,
 * RendererOptions) work polymorphically.
 *
 * Negative cases assert that the format-specific accessors stay on
 * the concrete subclass — code that needs to embed the CFF / glyf
 * bytes is forced through an `instanceof` check rather than silently
 * reading whichever field happens to exist.
 */
final class FontFaceDataTest extends TestCase
{
    public function testOpenTypeDataIsFontFaceData(): void
    {
        $data = $this->openType();
        self::assertInstanceOf(FontFaceData::class, $data);
    }

    public function testTrueTypeDataIsFontFaceData(): void
    {
        $data = $this->trueType();
        self::assertInstanceOf(FontFaceData::class, $data);
    }

    public function testSharedFieldsAreAccessibleViaBaseType(): void
    {
        // The Shaper, FontResolver, and PdfWriter-facing accessor code
        // takes `FontFaceData` and reads only the shared fields. Verify
        // each shared field round-trips on both subclasses without
        // upcasting tricks.
        /** @var list<FontFaceData> $fonts */
        $fonts = [$this->openType(), $this->trueType()];
        foreach ($fonts as $f) {
            self::assertSame('Sample', $f->postScriptName);
            self::assertSame('Sample', $f->familyName);
            self::assertSame(800, $f->ascent);
            self::assertSame(-200, $f->descent);
            self::assertSame(700, $f->capHeight);
            self::assertSame(500, $f->xHeight);
            self::assertEqualsWithDelta(0.0, $f->italicAngle, 1e-9);
            self::assertSame(80, $f->stemV);
            self::assertSame(32, $f->flags);
            self::assertSame([0, -200, 1000, 800], $f->fontBBox);
            self::assertSame([65 => 500], $f->charWidths);
            self::assertSame([65 => 0x41], $f->unicodeMap);
            self::assertSame(1000, $f->unitsPerEm);
            self::assertSame([0x41 => 1], $f->fullUnicodeToGid);
            self::assertSame([1 => 500], $f->glyphWidths);
            self::assertTrue($f->embeddingAllowed);
            self::assertNull($f->kernPairs);
            self::assertNull($f->ligatures);
        }
    }

    public function testOpenTypeOnlyFieldsAreSubclassExclusive(): void
    {
        // The CFF table bytes only live on OpenTypeData; the polymorphic
        // base type doesn't expose them.
        $ot = $this->openType();
        self::assertSame('cff-bytes', $ot->cffBytes);
        self::assertFalse(
            property_exists(FontFaceData::class, 'cffBytes'),
            'cffBytes must stay on the OpenType subclass',
        );
    }

    public function testTrueTypeOnlyFieldsAreSubclassExclusive(): void
    {
        // The variable-font axes only live on TrueTypeData.
        $tt = $this->trueType();
        self::assertFalse($tt->isVariableFont);
        self::assertFalse(
            property_exists(FontFaceData::class, 'isVariableFont'),
            'isVariableFont must stay on the TrueType subclass',
        );
    }

    public function testInstanceofDispatchSeparatesSubclasses(): void
    {
        // PdfWriter routes registration via `instanceof`. Confirm the
        // two subclasses don't both satisfy each other's check — a
        // OpenTypeData must not pass as TrueTypeData and vice versa.
        $ot = $this->openType();
        $tt = $this->trueType();
        self::assertInstanceOf(OpenTypeData::class, $ot);
        self::assertInstanceOf(TrueTypeData::class, $tt);
        self::assertNotInstanceOf(TrueTypeData::class, $ot);
        self::assertNotInstanceOf(OpenTypeData::class, $tt);
    }

    private function openType(): OpenTypeData
    {
        return new OpenTypeData(
            postScriptName: 'Sample',
            familyName: 'Sample',
            ascent: 800,
            descent: -200,
            capHeight: 700,
            xHeight: 500,
            italicAngle: 0.0,
            stemV: 80,
            flags: 32,
            fontBBox: [0, -200, 1000, 800],
            charWidths: [65 => 500],
            unicodeMap: [65 => 0x41],
            cffBytes: 'cff-bytes',
            fontBytes: 'otf-bytes',
            embeddingAllowed: true,
            fullUnicodeToGid: [0x41 => 1],
            glyphWidths: [1 => 500],
        );
    }

    private function trueType(): TrueTypeData
    {
        return new TrueTypeData(
            postScriptName: 'Sample',
            familyName: 'Sample',
            ascent: 800,
            descent: -200,
            capHeight: 700,
            xHeight: 500,
            italicAngle: 0.0,
            stemV: 80,
            flags: 32,
            fontBBox: [0, -200, 1000, 800],
            charWidths: [65 => 500],
            unicodeMap: [65 => 0x41],
            fontBytes: 'ttf-bytes',
            embeddingAllowed: true,
            fullUnicodeToGid: [0x41 => 1],
            glyphWidths: [1 => 500],
        );
    }
}
