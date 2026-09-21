<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\FuzzyTolerance;
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
        // If setUp() skipped the test (no `compare` binary), the typed
        // properties were never assigned — accessing them here would
        // throw "must not be accessed before initialization".
        if (!isset($this->renderedPng)) {
            return;
        }
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

    public function testDeclaredToleranceReplacesTheDefaultThreshold(): void
    {
        // 4 black pixels in an 8×8 = 64-pixel frame is 6.25% — well
        // over the default 1% threshold.
        $a = $this->makePng(8, 8, 255, 255, 255);
        $b = $this->makeDiffyPng(8, 8, 255, 255, 255, 4);
        try {
            self::assertFalse(
                (new Scorer())->diff($a, $b)['passed'],
                '4-of-64 differing pixels should fail the default threshold',
            );

            $declared = FuzzyTolerance::parse('maxDifference=0-255;totalPixels=0-10');
            self::assertNotNull($declared);
            self::assertTrue(
                (new Scorer())->diff($a, $b, $declared)['passed'],
                'a fixture allowing 10 differing pixels of any colour should pass',
            );
        } finally {
            @unlink($a);
            @unlink($b);
        }
    }

    public function testColourDifferenceBeyondDeclaredMaxDifferenceFails(): void
    {
        // Same 4 differing pixels, but this fixture declared that no
        // channel may move by more than 5/255. Black on white is 255.
        // Honouring only `totalPixels` — as the harness used to — would
        // pass this, which is the whole defect.
        $a = $this->makePng(8, 8, 255, 255, 255);
        $b = $this->makeDiffyPng(8, 8, 255, 255, 255, 4);
        try {
            $declared = FuzzyTolerance::parse('maxDifference=0-5;totalPixels=0-10');
            self::assertNotNull($declared);

            $result = (new Scorer())->diff($a, $b, $declared);

            self::assertFalse($result['passed']);
            self::assertStringContainsString('maxDifference=255', (string) $result['reason']);
        } finally {
            @unlink($a);
            @unlink($b);
        }
    }

    public function testDeclaredToleranceCountsDifferingPixelsAtZeroFuzz(): void
    {
        // 6 pixels off by 2/255. That is inside ImageMagick's 1% colour
        // fuzz, so the harness's old `-fuzz 1%` count saw ZERO differing
        // pixels and passed the fixture's `totalPixels=0-2` budget
        // without spending any of it. WPT counts pixels differing at
        // all, so 6 > 2 must fail.
        $a = $this->makePng(8, 8, 255, 255, 255);
        $b = $this->makeShiftedPng(8, 8, 255, 255, 255, 6, 2);
        try {
            $declared = FuzzyTolerance::parse('maxDifference=0-10;totalPixels=0-2');
            self::assertNotNull($declared);

            self::assertFalse((new Scorer())->diff($a, $b, $declared)['passed']);
        } finally {
            @unlink($a);
            @unlink($b);
        }
    }

    public function testExactMatchPassesAnyDeclaredTolerance(): void
    {
        $declared = FuzzyTolerance::parse('maxDifference=0-1;totalPixels=0-2');
        self::assertNotNull($declared);

        $result = (new Scorer())->diff($this->renderedPng, $this->referencePng, $declared);

        self::assertTrue($result['passed']);
    }

    public function testBlankVersusBlankStillPassesButIsFlaggedAsUnevidenced(): void
    {
        // Both fixtures are solid white. They match perfectly, so the
        // verdict stays a PASS — WPT scores it the same way, and a
        // handful of fixtures legitimately draw nothing. What the flag
        // records is that neither side drew anything, so the pass is
        // no evidence that the renderer got anything right.
        $result = (new Scorer())->diff($this->renderedPng, $this->referencePng);

        self::assertTrue($result['passed'], 'a blank-vs-blank pass must NOT be auto-failed');
        self::assertTrue($result['bothSolid']);
    }

    public function testAPassWithDrawnContentIsNotFlaggedBlank(): void
    {
        $a = $this->makeDiffyPng(64, 64, 255, 255, 255, 8);
        $b = $this->makeDiffyPng(64, 64, 255, 255, 255, 8);
        try {
            $result = (new Scorer())->diff($a, $b);

            self::assertTrue($result['passed']);
            self::assertFalse($result['bothSolid'], 'both frames drew 8 black pixels');
        } finally {
            @unlink($a);
            @unlink($b);
        }
    }

    public function testTwoSolidFramesOfDifferentColoursFailAndAreNotFlagged(): void
    {
        // Solid green against solid red is a real, maximal failure.
        // The flag counts *passes* with no evidence, so it must stay
        // false here even though both frames are solid.
        $a = $this->makePng(64, 64, 0, 255, 0);
        $b = $this->makePng(64, 64, 255, 0, 0);
        try {
            $result = (new Scorer())->diff($a, $b);

            self::assertFalse($result['passed']);
            self::assertFalse($result['bothSolid']);
        } finally {
            @unlink($a);
            @unlink($b);
        }
    }

    public function testSolidProbeSeesAPixelThatFallsBetweenItsGridSamples(): void
    {
        // The probe rejects most frames off a coarse 16px grid. A lone
        // pixel at (1, 1) is invisible to every grid sample, so this
        // only comes out right if the exhaustive scan really runs.
        $img = imagecreatetruecolor(64, 64);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagesetpixel($img, 1, 1, imagecolorallocate($img, 0, 0, 0));
        $path = tempnam(sys_get_temp_dir(), 'scorer_test_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        try {
            self::assertFalse(Scorer::isSolidColour($path));
        } finally {
            @unlink($path);
        }
    }

    public function testUnreadableImageIsNotCountedAsBlank(): void
    {
        // No evidence of blankness is not evidence of blankness.
        self::assertFalse(Scorer::isSolidColour('/nonexistent/wpt-harness-probe.png'));
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

    /**
     * Same as `makeDiffyPng()` but the differing pixels are only
     * `$delta` darker rather than pure black — small enough to sit
     * inside ImageMagick's 1% colour fuzz.
     */
    private function makeShiftedPng(int $w, int $h, int $r, int $g, int $b, int $diffPixels, int $delta): string
    {
        $img = imagecreatetruecolor($w, $h);
        self::assertNotFalse($img);
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $shifted = imagecolorallocate($img, $r - $delta, $g - $delta, $b - $delta);
        for ($i = 0; $i < $diffPixels; $i++) {
            imagesetpixel($img, $i, 0, $shifted);
        }
        $path = tempnam(sys_get_temp_dir(), 'scorer_test_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }
}
