<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests;

use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class PdfStringTest extends TestCase
{
    public function testLiteralStringIsParenthesized(): void
    {
        $s = new PdfString('hello');
        $this->assertSame('(hello)', $s->toPdf());
    }

    public function testHexStringIsAngleBracketed(): void
    {
        $s = new PdfString("\xAB\xCD", hex: true);
        $this->assertSame('<abcd>', $s->toPdf());
    }

    public function testLiteralStringEscapesBackslash(): void
    {
        $s = new PdfString('a\\b');
        $this->assertSame('(a\\\\b)', $s->toPdf());
    }

    public function testLiteralStringEscapesParens(): void
    {
        $s = new PdfString('(test)');
        $this->assertSame('(\\(test\\))', $s->toPdf());
    }

    public function testLiteralStringEscapesControlChars(): void
    {
        $s = new PdfString("a\nb\rc\td\x08e\x0Cf");
        $this->assertSame('(a\nb\rc\td\be\ff)', $s->toPdf());
    }

    public function testEmptyLiteralString(): void
    {
        $this->assertSame('()', (new PdfString(''))->toPdf());
    }

    public function testEmptyHexString(): void
    {
        $this->assertSame('<>', (new PdfString('', hex: true))->toPdf());
    }

    public function testNonControlCharsPassThrough(): void
    {
        $s = new PdfString('AaZz09!?');
        $this->assertSame('(AaZz09!?)', $s->toPdf());
    }

    public function testFieldsAreReadable(): void
    {
        $s = new PdfString('val', hex: true);
        $this->assertSame('val', $s->value);
        $this->assertTrue($s->hex);
    }
}
