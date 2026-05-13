<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Content;

use PHPUnit\Framework\TestCase;
use Phpdftk\Encoding\TextEncoder;
use Phpdftk\Encoding\WinAnsiEncoder;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Font\RegisteredFont;

class ContentStreamEncoderTest extends TestCase
{
    public function testStringSetFontDoesNotEncode(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $cs->setFont('F1', 12)->showText("café \u{2014}");
        $pdf = $cs->toPdf();
        // Raw UTF-8 bytes survive verbatim: caf + 0xC3 0xA9 + space + 0xE2 0x80 0x94
        self::assertStringContainsString("(caf\xC3\xA9 \xE2\x80\x94) Tj", $pdf);
    }

    public function testRegisteredFontSetFontEncodesToWinAnsi(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $cs->setFont($this->winAnsiFont('F1'), 12)->showText("café \u{2014}");
        $pdf = $cs->toPdf();
        // café → caf 0xE9; em dash → 0x97.
        self::assertStringContainsString("(caf\xE9 \x97) Tj", $pdf);
    }

    public function testShowTextArrayEncodesEachString(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $cs->setFont($this->winAnsiFont('F1'), 12)
            ->showTextArray(["caf\u{00E9}", -50, "\u{2014}"]);
        $pdf = $cs->toPdf();
        self::assertStringContainsString("(caf\xE9)", $pdf);
        self::assertStringContainsString('-50', $pdf);
        self::assertStringContainsString("(\x97)", $pdf);
    }

    public function testSwitchingBackToStringFontClearsEncoder(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        $cs->setFont($this->winAnsiFont('F1'), 12)
            ->showText("caf\u{00E9}")          // encoded → 0xE9
            ->setFont('F2', 12)                 // resets encoder
            ->showText("caf\u{00E9}");         // raw UTF-8 0xC3 0xA9
        $pdf = $cs->toPdf();
        self::assertStringContainsString("(caf\xE9) Tj", $pdf);
        self::assertStringContainsString("(caf\xC3\xA9) Tj", $pdf);
    }

    public function testNullEncoderFontPreservesRawBytes(): void
    {
        $cs = new ContentStream();
        $cs->objectNumber = 1;
        // A registered composite/CID font reports a null encoder.
        $cs->setFont($this->cidFont('F1'), 12)->showText("\xCA\xFE");
        $pdf = $cs->toPdf();
        self::assertStringContainsString("(\xCA\xFE) Tj", $pdf);
    }

    private function winAnsiFont(string $name): RegisteredFont
    {
        return new class ($name) implements RegisteredFont {
            public function __construct(private readonly string $name) {}

            public function getResourceName(): string
            {
                return $this->name;
            }

            public function getTextEncoder(): ?TextEncoder
            {
                return new WinAnsiEncoder();
            }
        };
    }

    private function cidFont(string $name): RegisteredFont
    {
        return new class ($name) implements RegisteredFont {
            public function __construct(private readonly string $name) {}

            public function getResourceName(): string
            {
                return $this->name;
            }

            public function getTextEncoder(): ?TextEncoder
            {
                return null;
            }
        };
    }
}
