<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\Scorer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the perceptual-diff Scorer wrapper around
 * ImageMagick `compare`. The tests skip themselves when `compare`
 * isn't installed — same portability story as the smoke-integration
 * suite.
 */
final class ScorerTest extends TestCase
{
    private string $renderedPng;
    private string $referencePng;
    private string $diffyPng;

    protected function setUp(): void
    {
        if (!(new Scorer())->isAvailable()) {
            $this->markTestSkipped('ImageMagick `compare` not installed');
        }
        $this->renderedPng = $this->makePng(64, 64, 255, 255, 255);
        $this->referencePng = $this->makePng(64, 64, 255, 255, 255);
        // Slightly different image — 8 pixels off in one corner.
        $this->diffyPng = $this->makeDiffyPng(64, 64, 255, 255, 255, 8);
    }

    protected function tearDown(): void
    {
        foreach ([$this->renderedPng, $this->referencePng, $this->diffyPng] as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
    }

    public function testIdenticalImagesPass(): void
    {
        $result = (new Scorer())->diff($this->renderedPng, $this->referencePng);
        self::assertTrue($result['passed']);
        self::assertEqualsWithDelta(0.0, $result['score'], 0.0001);
    }

    public function testFuzzyMaxPixelsOverrideAllowsDiff(): void
    {
        // The diffyPng has 8 differing pixels in a 64×64 = 4096 image,
        // so the score is 8/4096 ≈ 0.002 — already under the default
        // 0.01 threshold. Use a tiny image to trigger the override
        // path: make a smaller diffy and pass `maxAllowedPixels`.
        $tinyA = $this->makePng(8, 8, 255, 255, 255);
        $tinyB = $this->makeDiffyPng(8, 8, 255, 255, 255, 4);
        try {
            $strict = (new Scorer())->diff($tinyA, $tinyB);
            self::assertFalse(
                $strict['passed'],
                '4-of-64 pixel diff should fail the default 1% threshold',
            );
            $relaxed = (new Scorer())->diff($tinyA, $tinyB, maxAllowedPixels: 10);
            self::assertTrue(
                $relaxed['passed'],
                '4-of-64 pixel diff should pass when maxAllowedPixels=10',
            );
        } finally {
            @unlink($tinyA);
            @unlink($tinyB);
        }
    }

    private function makePng(int $w, int $h, int $r, int $g, int $b): string
    {
        $img = imagecreatetruecolor($w, $h);
        self::assertNotFalse($img);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $path = tempnam(sys_get_temp_dir(), 'scorer_test_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }

    /**
     * Same as `makePng()` but flips `$diffPixels` pixels in the
     * top-left to pure black so the comparator sees them as different.
     */
    private function makeDiffyPng(int $w, int $h, int $r, int $g, int $b, int $diffPixels): string
    {
        $img = imagecreatetruecolor($w, $h);
        self::assertNotFalse($img);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $black = imagecolorallocate($img, 0, 0, 0);
        for ($i = 0; $i < $diffPixels; $i++) {
            imagesetpixel($img, $i, 0, $black);
        }
        $path = tempnam(sys_get_temp_dir(), 'scorer_test_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }
}
