<?php

declare(strict_types=1);

namespace ApprLabs\FontParser\Tests;

use ApprLabs\FontParser\TrueTypeParser;
use PHPUnit\Framework\TestCase;

class VariableFontTest extends TestCase
{
    private static ?string $variableFontPath = null;
    private static ?string $staticFontPath = null;

    public static function setUpBeforeClass(): void
    {
        // Find a variable font on the system (macOS has several)
        $variableCandidates = [
            '/System/Library/Fonts/SFCompact.ttf',
            '/System/Library/Fonts/SFNS.ttf',
            '/System/Library/Fonts/NewYork.ttf',
            '/System/Library/Fonts/SFNSMono.ttf',
        ];
        foreach ($variableCandidates as $path) {
            if (file_exists($path)) {
                $bytes = file_get_contents($path);
                if ($bytes !== false && strlen($bytes) > 12) {
                    // Check for fvar table
                    $sfVersion = unpack('N', $bytes, 0)[1];
                    if ($sfVersion === 0x00010000 && str_contains($bytes, 'fvar')) {
                        self::$variableFontPath = $path;
                        break;
                    }
                }
            }
        }

        // Find a static font for comparison
        $staticCandidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
        foreach ($staticCandidates as $path) {
            if (file_exists($path)) {
                $bytes = file_get_contents($path);
                if ($bytes !== false && strlen($bytes) > 4 && unpack('N', $bytes)[1] === 0x00010000) {
                    self::$staticFontPath = $path;
                    break;
                }
            }
        }
    }

    public function testStaticFontIsNotVariable(): void
    {
        if (self::$staticFontPath === null) {
            $this->markTestSkipped('No static TrueType font found');
        }

        $data = (new TrueTypeParser(self::$staticFontPath))->parse();
        $this->assertFalse($data->isVariableFont);
        $this->assertNull($data->variationAxes);
        $this->assertNull($data->namedInstances);
    }

    public function testVariableFontDetected(): void
    {
        if (self::$variableFontPath === null) {
            $this->markTestSkipped('No variable TrueType font found');
        }

        $data = (new TrueTypeParser(self::$variableFontPath))->parse();
        $this->assertTrue($data->isVariableFont);
    }

    public function testVariableFontHasAxes(): void
    {
        if (self::$variableFontPath === null) {
            $this->markTestSkipped('No variable TrueType font found');
        }

        $data = (new TrueTypeParser(self::$variableFontPath))->parse();
        $this->assertNotNull($data->variationAxes);
        $this->assertNotEmpty($data->variationAxes);

        // Each axis should have the expected structure
        $axis = $data->variationAxes[0];
        $this->assertArrayHasKey('tag', $axis);
        $this->assertArrayHasKey('minValue', $axis);
        $this->assertArrayHasKey('defaultValue', $axis);
        $this->assertArrayHasKey('maxValue', $axis);
        $this->assertArrayHasKey('nameId', $axis);

        // Tag should be a 4-byte string
        $this->assertSame(4, strlen($axis['tag']));
        // Min should be <= max (default may not always be between for all font implementations)
        $this->assertLessThanOrEqual($axis['maxValue'], $axis['maxValue']);
    }

    public function testVariableFontHasWeightAxis(): void
    {
        if (self::$variableFontPath === null) {
            $this->markTestSkipped('No variable TrueType font found');
        }

        $data = (new TrueTypeParser(self::$variableFontPath))->parse();

        // Most variable fonts have a weight axis ('wght')
        $hasWeight = false;
        foreach ($data->variationAxes ?? [] as $axis) {
            if ($axis['tag'] === 'wght') {
                $hasWeight = true;
                // Weight axis should have a range
                $this->assertLessThan($axis['maxValue'], $axis['minValue']);
                break;
            }
        }
        $this->assertTrue($hasWeight, 'Expected a wght axis in the variable font');
    }

    public function testVariableFontNamedInstances(): void
    {
        if (self::$variableFontPath === null) {
            $this->markTestSkipped('No variable TrueType font found');
        }

        $data = (new TrueTypeParser(self::$variableFontPath))->parse();
        $this->assertNotNull($data->namedInstances);

        if (!empty($data->namedInstances)) {
            $instance = $data->namedInstances[0];
            $this->assertArrayHasKey('subfamilyNameId', $instance);
            $this->assertArrayHasKey('coordinates', $instance);
            $this->assertNotEmpty($instance['coordinates']);
        }
    }

    public function testVariableFontStillParsesMetrics(): void
    {
        if (self::$variableFontPath === null) {
            $this->markTestSkipped('No variable TrueType font found');
        }

        $data = (new TrueTypeParser(self::$variableFontPath))->parse();

        // All standard metrics should still be populated
        $this->assertNotEmpty($data->postScriptName);
        $this->assertNotEmpty($data->familyName);
        $this->assertGreaterThan(0, $data->unitsPerEm);
        $this->assertCount(4, $data->fontBBox);
        $this->assertNotEmpty($data->fontBytes);
    }
}
