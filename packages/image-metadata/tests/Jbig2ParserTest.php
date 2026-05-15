<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata\Tests;

use Phpdftk\ImageMetadata\Jbig2Parser;
use PHPUnit\Framework\TestCase;

class Jbig2ParserTest extends TestCase
{
    private const SIGNATURE = "\x97\x4A\x42\x32\x0D\x0A\x1A\x0A";

    /**
     * Build a minimal JBIG2 segment header followed by an optional fixed body.
     *
     * @param int $segNum     segment number (4 bytes)
     * @param int $segType    type (6 bits of flags byte)
     * @param int $pageAssoc  page association (1 byte unless large)
     * @param string $body    fixed segment data
     * @param bool $largePage  use 4-byte page association
     */
    private function segment(int $segNum, int $segType, int $pageAssoc, string $body = '', bool $largePage = false): string
    {
        $out = pack('N', $segNum);
        $flags = $segType & 0x3F;
        if ($largePage) {
            $flags |= 0x40;
        }
        $out .= chr($flags);
        // refCount = 0 (no referred segments)
        $out .= "\x00";
        // Page association
        if ($largePage) {
            $out .= pack('N', $pageAssoc);
        } else {
            $out .= chr($pageAssoc);
        }
        // Data length
        $out .= pack('N', strlen($body));
        $out .= $body;
        return $out;
    }

    public function testParseFileFormatWithPageInformation(): void
    {
        // File header (sequential, known page count)
        $header = self::SIGNATURE . "\x00" . pack('N', 1);
        // Page Information segment: type 48, body has width+height as uint32
        $body = pack('N', 800) . pack('N', 600);
        $data = $header . $this->segment(1, 48, 1, $body);

        $info = Jbig2Parser::parse($data);
        $this->assertSame(800, $info->width);
        $this->assertSame(600, $info->height);
        $this->assertSame('DeviceGray', $info->colorSpace);
        $this->assertSame(1, $info->bitsPerComponent);
        $this->assertSame('jbig2', $info->format);
        $this->assertFalse($info->hasAlpha);
    }

    public function testParseFileFormatUnknownPageCount(): void
    {
        // flags = 0x02 = unknown page count (no 4-byte count)
        $header = self::SIGNATURE . "\x02";
        $body = pack('N', 100) . pack('N', 200);
        $data = $header . $this->segment(1, 48, 1, $body);

        $info = Jbig2Parser::parse($data);
        $this->assertSame(100, $info->width);
        $this->assertSame(200, $info->height);
    }

    public function testParseEmbeddedFormatNoFileHeader(): void
    {
        // No file header — straight into segments
        $body = pack('N', 50) . pack('N', 25);
        $data = $this->segment(1, 48, 1, $body);

        $info = Jbig2Parser::parse($data);
        $this->assertSame(50, $info->width);
        $this->assertSame(25, $info->height);
    }

    public function testParseLargePageAssociation(): void
    {
        // Segment with 4-byte page association.
        $body = pack('N', 11) . pack('N', 22);
        $data = $this->segment(1, 48, 1, $body, largePage: true);

        $info = Jbig2Parser::parse($data);
        $this->assertSame(11, $info->width);
        $this->assertSame(22, $info->height);
    }

    public function testSkipsNonPageInfoSegments(): void
    {
        // First a non-48 segment (e.g., type 0), then a Page Info segment.
        $other = $this->segment(1, 0, 1, "\x00\x00\x00\x00");
        $page = $this->segment(2, 48, 1, pack('N', 7) . pack('N', 8));
        $data = $other . $page;

        $info = Jbig2Parser::parse($data);
        $this->assertSame(7, $info->width);
        $this->assertSame(8, $info->height);
    }

    public function testRefCountLongFormIsHandled(): void
    {
        // Build segment with refCount=7 (long form) → next 4 bytes give actual count (0).
        $segHeader = pack('N', 1) . chr(48 & 0x3F) . chr(0xE0); // 0xE0 = bits 5-7 = 7
        $segHeader .= pack('N', 0); // long-form refCount = 0
        $segHeader .= chr(1); // page assoc
        $body = pack('N', 99) . pack('N', 88);
        $segHeader .= pack('N', strlen($body)) . $body;

        $info = Jbig2Parser::parse($segHeader);
        $this->assertSame(99, $info->width);
        $this->assertSame(88, $info->height);
    }

    public function testHigherSegmentNumberUses2ByteRefSize(): void
    {
        // Segment number > 256 means refSize=2. Use refCount=1 referring to seg 0.
        $segHeader = pack('N', 300);  // segNum > 256
        $segHeader .= chr(48 & 0x3F);   // type 48
        $segHeader .= chr(0x20);        // refCount=1
        $segHeader .= "\x00\x00";       // 2-byte referred segment number
        $segHeader .= chr(1);           // page assoc
        $body = pack('N', 5) . pack('N', 6);
        $segHeader .= pack('N', strlen($body)) . $body;

        $info = Jbig2Parser::parse($segHeader);
        $this->assertSame(5, $info->width);
        $this->assertSame(6, $info->height);
    }

    public function testNoPageInfoSegmentThrows(): void
    {
        // Single non-page-info segment, no page info follows.
        $data = $this->segment(1, 0, 1, '');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unable to find page dimensions');
        Jbig2Parser::parse($data);
    }

    public function testFileTooShortAfterSignatureThrows(): void
    {
        // Signature present but no flags byte
        $data = self::SIGNATURE;
        $this->expectException(\RuntimeException::class);
        Jbig2Parser::parse($data);
    }

    public function testParseFileReadsFromDisk(): void
    {
        $header = self::SIGNATURE . "\x00" . pack('N', 1);
        $body = pack('N', 17) . pack('N', 19);
        $bytes = $header . $this->segment(1, 48, 1, $body);
        $path = tempnam(sys_get_temp_dir(), 'jbig2_') . '.jb2';
        file_put_contents($path, $bytes);
        try {
            $info = Jbig2Parser::parseFile($path);
            $this->assertSame(17, $info->width);
            $this->assertSame(19, $info->height);
        } finally {
            @unlink($path);
        }
    }

    public function testUnknownDataLengthBreaksLoop(): void
    {
        // 0xFFFFFFFF as data length triggers the bail-out branch.
        $segHeader = pack('N', 1) . chr(0 & 0x3F) . chr(0) . chr(1) . pack('N', 0xFFFFFFFF);
        $data = $segHeader; // No following Page Info segment

        $this->expectException(\RuntimeException::class);
        Jbig2Parser::parse($data);
    }
}
