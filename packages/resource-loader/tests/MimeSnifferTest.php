<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader\Tests;

use Phpdftk\ResourceLoader\MimeSniffer;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4F.1 — byte → MIME detection. Each test feeds a real
 * magic-bytes header + a bit of trailing data so the sniffer is
 * exercised against realistic inputs, not just the signature.
 */
final class MimeSnifferTest extends TestCase
{
    private MimeSniffer $sniffer;

    protected function setUp(): void
    {
        $this->sniffer = new MimeSniffer();
    }

    public function testPngSignatureSniffs(): void
    {
        // The fixed 8-byte PNG signature followed by a faked IHDR.
        $bytes = "\x89PNG\r\n\x1a\n" . "\x00\x00\x00\x0DIHDR";
        self::assertSame('image/png', $this->sniffer->sniff($bytes));
    }

    public function testJpegSignatureSniffs(): void
    {
        // FF D8 FF E0 = JFIF, FF D8 FF E1 = EXIF; both pass the
        // 3-byte FF D8 FF gate.
        self::assertSame('image/jpeg', $this->sniffer->sniff("\xff\xd8\xff\xe0\x00\x10JFIF"));
        self::assertSame('image/jpeg', $this->sniffer->sniff("\xff\xd8\xff\xe1\x00\x10Exif"));
    }

    public function testGif87aSignatureSniffs(): void
    {
        self::assertSame('image/gif', $this->sniffer->sniff('GIF87a' . str_repeat("\x00", 8)));
    }

    public function testGif89aSignatureSniffs(): void
    {
        self::assertSame('image/gif', $this->sniffer->sniff('GIF89a' . str_repeat("\x00", 8)));
    }

    public function testWebpSignatureSniffs(): void
    {
        // RIFF <4-byte size> WEBP <VP8/VP8L/VP8X> ...
        $bytes = 'RIFF' . "\x00\x00\x00\x00" . 'WEBPVP8 ';
        self::assertSame('image/webp', $this->sniffer->sniff($bytes));
    }

    public function testRiffNonWebpDoesNotSniffAsWebp(): void
    {
        // A WAV file starts with RIFF too — but with WAVE not WEBP
        // in offset 8. Should fall through to the fallback.
        $bytes = 'RIFF' . "\x00\x00\x00\x00" . 'WAVEfmt ';
        self::assertSame(MimeSniffer::FALLBACK, $this->sniffer->sniff($bytes));
    }

    public function testTiffLittleEndianSignatureSniffs(): void
    {
        // II 2A 00 — Intel byte order, magic 42.
        self::assertSame('image/tiff', $this->sniffer->sniff("II*\x00" . str_repeat("\x00", 8)));
    }

    public function testTiffBigEndianSignatureSniffs(): void
    {
        // MM 00 2A — Motorola byte order.
        self::assertSame('image/tiff', $this->sniffer->sniff("MM\x00*" . str_repeat("\x00", 8)));
    }

    public function testBmpSignatureSniffs(): void
    {
        self::assertSame('image/bmp', $this->sniffer->sniff('BM' . str_repeat("\x00", 12)));
    }

    public function testSvgWithXmlDeclarationSniffs(): void
    {
        $bytes = '<?xml version="1.0"?>' . "\n" . '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';
        self::assertSame('image/svg+xml', $this->sniffer->sniff($bytes));
    }

    public function testSvgWithoutXmlDeclarationSniffs(): void
    {
        $bytes = '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';
        self::assertSame('image/svg+xml', $this->sniffer->sniff($bytes));
    }

    public function testSvgWithBomSniffs(): void
    {
        // UTF-8 BOM prefix — strip + detect.
        $bytes = "\xEF\xBB\xBF" . '<?xml version="1.0"?><svg></svg>';
        self::assertSame('image/svg+xml', $this->sniffer->sniff($bytes));
    }

    public function testSvgWithLeadingWhitespaceSniffs(): void
    {
        $bytes = "   \n\t" . '<svg></svg>';
        self::assertSame('image/svg+xml', $this->sniffer->sniff($bytes));
    }

    public function testSvgWithDoctypeBetweenXmlAndRootSniffs(): void
    {
        // Real-world SVGs sometimes have a DOCTYPE between the XML
        // declaration and the root.
        $bytes = '<?xml version="1.0"?>' . "\n"
            . '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" '
            . '"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
        self::assertSame('image/svg+xml', $this->sniffer->sniff($bytes));
    }

    public function testXmlButNotSvgFallsThrough(): void
    {
        // XML root that isn't <svg> — should not sniff as SVG.
        $bytes = '<?xml version="1.0"?><rss version="2.0"><channel></channel></rss>';
        self::assertSame(MimeSniffer::FALLBACK, $this->sniffer->sniff($bytes));
    }

    public function testNonSvgTextFallsThrough(): void
    {
        self::assertSame(MimeSniffer::FALLBACK, $this->sniffer->sniff('hello world'));
        self::assertSame(MimeSniffer::FALLBACK, $this->sniffer->sniff('{"json": true}'));
    }

    public function testEmptyInputFallsThrough(): void
    {
        self::assertSame(MimeSniffer::FALLBACK, $this->sniffer->sniff(''));
    }

    public function testVeryShortInputFallsThrough(): void
    {
        self::assertSame(MimeSniffer::FALLBACK, $this->sniffer->sniff('x'));
    }

    public function testSvgPrefixedByWhitespaceWithinElementName(): void
    {
        // `<svg-something>` is NOT an SVG element — make sure we
        // don't false-positive on prefix-match.
        self::assertSame(MimeSniffer::FALLBACK, $this->sniffer->sniff('<svg-something>'));
    }

    public function testPngSignatureWithTruncatedHeaderFallsThrough(): void
    {
        // First 4 bytes of PNG but not full signature → not enough
        // to confirm.
        self::assertSame(MimeSniffer::FALLBACK, $this->sniffer->sniff("\x89PNG"));
    }
}
