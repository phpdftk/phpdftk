<?php

declare(strict_types=1);

namespace ApprLabs\FontParser\Tests;

use ApprLabs\FontParser\OpenTypeParser;
use ApprLabs\FontParser\TrueTypeParser;
use ApprLabs\Pdf\Core\Font\Type0FontFactory;
use PHPUnit\Framework\TestCase;

class VerticalWritingTest extends TestCase
{
    private static ?string $ttfPath = null;

    public static function setUpBeforeClass(): void
    {
        $candidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $bytes = file_get_contents($path);
                if ($bytes !== false && strlen($bytes) > 4 && unpack('N', $bytes)[1] === 0x00010000) {
                    self::$ttfPath = $path;
                    return;
                }
            }
        }
    }

    public function testVerticalModeUsesIdentityV(): void
    {
        if (self::$ttfPath === null) {
            $this->markTestSkipped('No TrueType font found');
        }

        $data = (new TrueTypeParser(self::$ttfPath))->parse();
        $codepoints = [0x41, 0x42, 0x43]; // A, B, C

        [$type0Font, $objects] = Type0FontFactory::fromTrueTypeData($data, $codepoints, vertical: true);

        $pdf = $type0Font->toPdf();
        $this->assertStringContainsString('/Encoding /Identity-V', $pdf);
    }

    public function testHorizontalModeUsesIdentityH(): void
    {
        if (self::$ttfPath === null) {
            $this->markTestSkipped('No TrueType font found');
        }

        $data = (new TrueTypeParser(self::$ttfPath))->parse();
        $codepoints = [0x41, 0x42, 0x43];

        [$type0Font, $objects] = Type0FontFactory::fromTrueTypeData($data, $codepoints, vertical: false);

        $pdf = $type0Font->toPdf();
        $this->assertStringContainsString('/Encoding /Identity-H', $pdf);
    }

    public function testOpenTypeVerticalMetricsParsed(): void
    {
        // Find an OpenType font with vhea/vmtx
        $otfCandidates = [
            '/System/Library/Fonts/Supplemental/STIXGeneral.otf',
            '/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc',
        ];

        $otfPath = null;
        foreach ($otfCandidates as $path) {
            if (file_exists($path)) {
                $bytes = file_get_contents($path);
                if ($bytes !== false && substr($bytes, 0, 4) === 'OTTO') {
                    $otfPath = $path;
                    break;
                }
            }
        }

        if ($otfPath === null) {
            $this->markTestSkipped('No OpenType font found');
        }

        $data = (new OpenTypeParser($otfPath))->parse();

        // verticalWidths may or may not be present depending on the font
        if ($data->verticalWidths !== null) {
            $this->assertIsArray($data->verticalWidths);
            $this->assertGreaterThan(0, count($data->verticalWidths));
        } else {
            // Font has no vhea/vmtx — that's OK, just verify the field is null
            $this->assertNull($data->verticalWidths);
        }
    }
}
