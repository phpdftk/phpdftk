<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Core;

use PHPUnit\Framework\TestCase;
use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfBoolean;
use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNull;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfReference;
use Phpdftk\Core\PdfString;

class PdfObjectTest extends TestCase
{
    // -----------------------------------------------------------------------
    // PdfName
    // -----------------------------------------------------------------------

    public function testPdfNameSimple(): void
    {
        $name = new PdfName('Type');
        self::assertSame('/Type', $name->toPdf());
    }

    public function testPdfNameWithSpecialChars(): void
    {
        // Forward slash within name value must be escaped
        $name = new PdfName('A/B');
        self::assertStringStartsWith('/', $name->toPdf());
        self::assertStringContainsString('#2F', $name->toPdf());
    }

    public function testPdfNameEmpty(): void
    {
        $name = new PdfName('');
        self::assertSame('/', $name->toPdf());
    }

    public function testPdfNameWithHash(): void
    {
        // # must be escaped
        $name = new PdfName('A#B');
        self::assertStringContainsString('#23', $name->toPdf());
    }

    // -----------------------------------------------------------------------
    // PdfString
    // -----------------------------------------------------------------------

    public function testPdfStringLiteral(): void
    {
        $str = new PdfString('Hello World');
        self::assertSame('(Hello World)', $str->toPdf());
    }

    public function testPdfStringEscapesParentheses(): void
    {
        $str = new PdfString('Hello (World)');
        self::assertSame('(Hello \\(World\\))', $str->toPdf());
    }

    public function testPdfStringEscapesBackslash(): void
    {
        $str = new PdfString('back\\slash');
        self::assertSame('(back\\\\slash)', $str->toPdf());
    }

    public function testPdfStringEscapesNewline(): void
    {
        $str = new PdfString("line1\nline2");
        self::assertSame('(line1\\nline2)', $str->toPdf());
    }

    public function testPdfStringHex(): void
    {
        $str = new PdfString('AB', hex: true);
        self::assertSame('<4142>', $str->toPdf());
    }

    public function testPdfStringEmpty(): void
    {
        $str = new PdfString('');
        self::assertSame('()', $str->toPdf());
    }

    // -----------------------------------------------------------------------
    // PdfNumber
    // -----------------------------------------------------------------------

    public function testPdfNumberInteger(): void
    {
        $n = new PdfNumber(42);
        self::assertSame('42', $n->toPdf());
    }

    public function testPdfNumberNegativeInteger(): void
    {
        $n = new PdfNumber(-7);
        self::assertSame('-7', $n->toPdf());
    }

    public function testPdfNumberZero(): void
    {
        $n = new PdfNumber(0);
        self::assertSame('0', $n->toPdf());
    }

    public function testPdfNumberFloat(): void
    {
        $n = new PdfNumber(3.14);
        self::assertSame('3.14', $n->toPdf());
    }

    public function testPdfNumberFloatTrailingZeros(): void
    {
        $n = new PdfNumber(1.5);
        self::assertSame('1.5', $n->toPdf());
    }

    public function testPdfNumberNegativeFloat(): void
    {
        $n = new PdfNumber(-0.5);
        self::assertSame('-0.5', $n->toPdf());
    }

    // -----------------------------------------------------------------------
    // PdfBoolean
    // -----------------------------------------------------------------------

    public function testPdfBooleanTrue(): void
    {
        $b = new PdfBoolean(true);
        self::assertSame('true', $b->toPdf());
    }

    public function testPdfBooleanFalse(): void
    {
        $b = new PdfBoolean(false);
        self::assertSame('false', $b->toPdf());
    }

    // -----------------------------------------------------------------------
    // PdfNull
    // -----------------------------------------------------------------------

    public function testPdfNull(): void
    {
        $null = new PdfNull();
        self::assertSame('null', $null->toPdf());
    }

    // -----------------------------------------------------------------------
    // PdfArray
    // -----------------------------------------------------------------------

    public function testPdfArrayEmpty(): void
    {
        $arr = new PdfArray([]);
        self::assertSame('[  ]', $arr->toPdf());
    }

    public function testPdfArrayWithNames(): void
    {
        $arr = new PdfArray([new PdfName('PDF'), new PdfName('Text')]);
        self::assertSame('[ /PDF /Text ]', $arr->toPdf());
    }

    public function testPdfArrayWithMixedItems(): void
    {
        $arr = new PdfArray([
            new PdfNumber(0),
            new PdfNumber(0),
            new PdfNumber(612),
            new PdfNumber(792),
        ]);
        self::assertSame('[ 0 0 612 792 ]', $arr->toPdf());
    }

    public function testPdfArrayWithReference(): void
    {
        $arr = new PdfArray([new PdfReference(5)]);
        self::assertSame('[ 5 0 R ]', $arr->toPdf());
    }

    public function testPdfArrayNested(): void
    {
        $inner = new PdfArray([new PdfNumber(1), new PdfNumber(2)]);
        $outer = new PdfArray([$inner]);
        self::assertSame('[ [ 1 2 ] ]', $outer->toPdf());
    }

    // -----------------------------------------------------------------------
    // PdfDictionary
    // -----------------------------------------------------------------------

    public function testPdfDictionaryEmpty(): void
    {
        $dict = new PdfDictionary();
        self::assertSame("<<\n>>", $dict->toPdf());
    }

    public function testPdfDictionaryWithEntries(): void
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName('Catalog'));
        $pdf = $dict->toPdf();
        self::assertStringContainsString('/Type /Catalog', $pdf);
    }

    public function testPdfDictionaryFluentSet(): void
    {
        $dict = (new PdfDictionary())
            ->set('A', new PdfNumber(1))
            ->set('B', new PdfNumber(2));
        $pdf = $dict->toPdf();
        self::assertStringContainsString('/A 1', $pdf);
        self::assertStringContainsString('/B 2', $pdf);
    }

    // -----------------------------------------------------------------------
    // PdfReference
    // -----------------------------------------------------------------------

    public function testPdfReferenceDefault(): void
    {
        $ref = new PdfReference(5);
        self::assertSame('5 0 R', $ref->toPdf());
    }

    public function testPdfReferenceWithGeneration(): void
    {
        $ref = new PdfReference(3, 2);
        self::assertSame('3 2 R', $ref->toPdf());
    }
}
