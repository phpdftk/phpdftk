<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests;

use Phpdftk\Html\EncodingSniffer;
use PHPUnit\Framework\TestCase;

/**
 * HTML §13.2.3 — determining the character encoding.
 *
 * The negative cases are the interesting ones. A declaration that must
 * NOT be honoured (inside a comment, inside script text, past the
 * prescan window) is not a cosmetic detail: honouring it re-decodes the
 * whole document under the wrong encoding, so every case where the
 * answer is "ignore this" gets a test.
 */
final class EncodingSnifferTest extends TestCase
{
    /** A windows-1251 `ж` is the single byte 0xE6. */
    private const string ZHE_1251 = "\xE6";
    private const string ZHE_UTF8 = "\u{0436}";

    public function testMetaCharsetTranscodesTheDocument(): void
    {
        self::assertStringContainsString(
            self::ZHE_UTF8,
            EncodingSniffer::toUtf8(
                '<!DOCTYPE html><meta charset="windows-1251"><p>' . self::ZHE_1251,
            ),
        );
    }

    public function testHttpEquivContentTypeIsHonoured(): void
    {
        self::assertStringContainsString(
            self::ZHE_UTF8,
            EncodingSniffer::toUtf8(
                '<meta http-equiv="Content-Type" content="text/html; charset=windows-1251"><p>'
                . self::ZHE_1251,
            ),
        );
    }

    public function testCharsetLabelIsCaseAndWhitespaceInsensitive(): void
    {
        self::assertSame('Windows-1251', EncodingSniffer::sniffMeta('<meta charset=" WINDOWS-1251 ">'));
    }

    public function testUnquotedCharsetValueIsRead(): void
    {
        self::assertSame('Windows-1251', EncodingSniffer::sniffMeta('<meta charset=windows-1251>'));
    }

    public function testCharacterReferencesInTheLabelAreDecoded(): void
    {
        // A tokenizer decodes attribute values, so `&#119;indows-1251`
        // IS the label windows-1251 (WPT `charset/ncr`).
        self::assertSame(
            'Windows-1251',
            EncodingSniffer::sniffMeta('<meta charset="&#119;indows-1251">'),
        );
    }

    public function testDeclarationInsideACommentIsIgnored(): void
    {
        self::assertNull(EncodingSniffer::sniffMeta('<!--<meta charset="windows-1251">--><p>x'));
    }

    public function testDeclarationInsideScriptTextIsIgnored(): void
    {
        self::assertNull(
            EncodingSniffer::sniffMeta('<script><meta charset="windows-1251"></script>'),
        );
    }

    public function testDeclarationInsideStyleTextIsIgnored(): void
    {
        self::assertNull(
            EncodingSniffer::sniffMeta('<style><meta charset="windows-1251"></style>'),
        );
    }

    public function testDeclarationInsideTitleTextIsIgnored(): void
    {
        self::assertNull(
            EncodingSniffer::sniffMeta('<title><meta charset="windows-1251"></title>'),
        );
    }

    public function testDeclarationInsideATemplateCounts(): void
    {
        // A template's children ARE tokenized as markup, unlike rawtext.
        self::assertSame(
            'Windows-1251',
            EncodingSniffer::sniffMeta('<template><meta charset="windows-1251"></template>'),
        );
    }

    public function testDeclarationInsideSvgCounts(): void
    {
        self::assertSame(
            'Windows-1251',
            EncodingSniffer::sniffMeta('<svg><meta charset="windows-1251"></svg>'),
        );
    }

    public function testDeclarationAfterAnUnknownElementCounts(): void
    {
        self::assertSame(
            'Windows-1251',
            EncodingSniffer::sniffMeta('<bogus><meta charset="windows-1251">'),
        );
    }

    public function testDeclarationBeginningAtByte1023Counts(): void
    {
        $meta = '<meta charset="windows-1251">';
        $source = str_repeat("\n", 1023) . $meta;
        self::assertSame('Windows-1251', EncodingSniffer::sniffMeta($source));
    }

    public function testDeclarationBeginningAtByte1024IsTooLate(): void
    {
        $meta = '<meta charset="windows-1251">';
        $source = str_repeat("\n", 1024) . $meta;
        self::assertNull(EncodingSniffer::sniffMeta($source));
    }

    public function testFirstDeclarationWins(): void
    {
        self::assertSame(
            'Windows-1251',
            EncodingSniffer::sniffMeta('<meta charset="windows-1251"><meta charset="iso-8859-7">'),
        );
    }

    public function testMetaWithoutACharsetIsSkippedAndScanningContinues(): void
    {
        self::assertSame(
            'Windows-1251',
            EncodingSniffer::sniffMeta('<meta name="viewport" content="width=device-width"><meta charset="windows-1251">'),
        );
    }

    public function testUnknownLabelResolvesToNothing(): void
    {
        self::assertNull(EncodingSniffer::sniffMeta('<meta charset="x-made-up-encoding">'));
    }

    public function testDocumentWithNoDeclarationIsLeftAlone(): void
    {
        // No locale-guessing fallback: an undeclared document stays as
        // supplied, so the UTF-8 majority is never re-interpreted.
        $source = '<p>' . self::ZHE_UTF8 . '</p>';
        self::assertSame($source, EncodingSniffer::toUtf8($source));
    }

    public function testUtf8DeclarationLeavesTheSourceByteIdentical(): void
    {
        $source = '<meta charset="utf-8"><p>' . self::ZHE_UTF8 . '</p>';
        self::assertSame($source, EncodingSniffer::toUtf8($source));
    }

    public function testUtf8BomIsStrippedRatherThanParsedAsText(): void
    {
        // Left in place it becomes a stray U+FEFF text node before <html>.
        self::assertSame('<p>x</p>', EncodingSniffer::toUtf8("\xEF\xBB\xBF<p>x</p>"));
    }

    public function testBomOverridesAContradictoryMetaDeclaration(): void
    {
        $source = "\xEF\xBB\xBF" . '<meta charset="windows-1251"><p>' . self::ZHE_UTF8;
        self::assertStringContainsString(self::ZHE_UTF8, EncodingSniffer::toUtf8($source));
    }

    public function testUtf16BomIsDetected(): void
    {
        self::assertSame('UTF-16LE', EncodingSniffer::bomEncoding("\xFF\xFE<"));
        self::assertSame('UTF-16BE', EncodingSniffer::bomEncoding("\xFE\xFF<"));
        self::assertNull(EncodingSniffer::bomEncoding('<!doctype html>'));
    }

    public function testExplicitOverrideBeatsTheDocumentDeclaration(): void
    {
        // The transport-level charset outranks `<meta>`.
        self::assertStringContainsString(
            "\u{03B6}", // greek small zeta, 0xE6 in ISO-8859-7
            EncodingSniffer::toUtf8(
                '<meta charset="windows-1251"><p>' . self::ZHE_1251,
                'iso-8859-7',
            ),
        );
    }

    public function testLatin1LabelMapsToWindows1252(): void
    {
        // Encoding Standard §4.2 — `iso-8859-1` IS windows-1252, which is
        // what puts a curly quote at 0x92 instead of an undefined C1 byte.
        self::assertStringContainsString(
            "\u{2019}",
            EncodingSniffer::toUtf8('<meta charset="iso-8859-1"><p>' . "\x92"),
        );
    }

    public function testUnterminatedCommentDoesNotHangTheScan(): void
    {
        self::assertNull(EncodingSniffer::sniffMeta('<!--<meta charset="windows-1251">'));
    }

    public function testMalformedTagDoesNotHangTheScan(): void
    {
        self::assertNull(EncodingSniffer::sniffMeta('<<<<>>>> < = " <meta'));
    }

    public function testUnterminatedRawtextElementDoesNotHangTheScan(): void
    {
        self::assertNull(EncodingSniffer::sniffMeta('<script><meta charset="windows-1251">'));
    }

    public function testEmptySourceIsHandled(): void
    {
        self::assertNull(EncodingSniffer::sniffMeta(''));
        self::assertSame('', EncodingSniffer::toUtf8(''));
    }
}
