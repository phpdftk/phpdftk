<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\ConsensusScorer;
use Phpdftk\WptHarness\ConsensusVerdict;
use Phpdftk\WptHarness\Scorer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the three-way ConsensusScorer. We fabricate PNGs at
 * known pixel-AE distances so the decision tree exercises:
 *
 *  - all three engines agree, ours matches  → PASS
 *  - all three engines agree, ours differs  → FAIL
 *  - browsers disagree                       → SKIP_DISAGREE
 *  - two engines agree, third diverges (consensus is the agreeing two)
 *  - only one engine supplied                → INSUFFICIENT_ENGINES
 *  - no engines supplied                     → INSUFFICIENT_ENGINES
 *
 * The PNGs are 100×100 (10 000 px) so a "1% drift" is exactly 100
 * pixels — easy mental arithmetic for fuzz tuning.
 */
final class ConsensusScorerTest extends TestCase
{
    private const SIZE = 100; // 100×100 → 10 000 px total

    /** @var list<string> Temporary files cleaned up in tearDown */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        if (!(new Scorer())->isAvailable()) {
            $this->markTestSkipped('ImageMagick `compare` not installed');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    public function testTwoEnginesAgreeAndOursMatchesPasses(): void
    {
        $ours = $this->makeSolidPng();
        $chromium = $this->makeSolidPng();
        $firefox = $this->makeSolidPng();
        $result = (new ConsensusScorer())->score($ours, [
            'chromium' => $chromium,
            'firefox' => $firefox,
        ]);
        self::assertSame(ConsensusVerdict::Pass, $result['verdict']);
        self::assertSame(['chromium', 'firefox'], $result['consensus']);
    }

    public function testThreeEnginesAgreeAndOursMatchesPasses(): void
    {
        $ours = $this->makeSolidPng();
        $renders = [
            'chromium' => $this->makeSolidPng(),
            'firefox' => $this->makeSolidPng(),
            'webkit' => $this->makeSolidPng(),
        ];
        $result = (new ConsensusScorer())->score($ours, $renders);
        self::assertSame(ConsensusVerdict::Pass, $result['verdict']);
        self::assertCount(3, $result['consensus']);
    }

    public function testEnginesAgreeButOursDivergesFails(): void
    {
        // 600 / 10 000 = 6 % AE — well above OURS_FUZZ_GEOMETRY (0.5 %)
        // but below OURS_FUZZ_TEXT (5 %)… actually 6 % > 5 % too, so
        // both budgets fail here.
        $ours = $this->makeDiffyPng(600);
        $renders = [
            'chromium' => $this->makeSolidPng(),
            'firefox' => $this->makeSolidPng(),
        ];
        $result = (new ConsensusScorer())->score($ours, $renders);
        self::assertSame(ConsensusVerdict::Fail, $result['verdict']);
        self::assertStringContainsString('diverges', $result['reason']);
    }

    public function testEnginesAgreeAndSmallOursDriftPassesTextBudget(): void
    {
        // 300 / 10 000 = 3 % AE — above geometry budget (0.5 %), within
        // text budget (5 %). Geometry call FAILS, text call PASSES.
        $ours = $this->makeDiffyPng(300);
        $renders = [
            'chromium' => $this->makeSolidPng(),
            'firefox' => $this->makeSolidPng(),
        ];
        $geo = (new ConsensusScorer())->score($ours, $renders, ConsensusScorer::OURS_FUZZ_GEOMETRY);
        $txt = (new ConsensusScorer())->score($ours, $renders, ConsensusScorer::OURS_FUZZ_TEXT);
        self::assertSame(ConsensusVerdict::Fail, $geo['verdict']);
        self::assertSame(ConsensusVerdict::Pass, $txt['verdict']);
    }

    public function testBrowsersDisagreeSkipsTheTest(): void
    {
        // Each browser is 300 px different from the others — 3 % AE,
        // above BROWSER_AGREE_FUZZ (2 %), so no two agree. Verdict is
        // SKIP_DISAGREE and ours is never even compared.
        $renders = [
            'chromium' => $this->makeSolidPng(),
            'firefox' => $this->makeDiffyPng(300),
            'webkit' => $this->makeDiffyPng(600),
        ];
        $ours = $this->makeSolidPng();
        $result = (new ConsensusScorer())->score($ours, $renders);
        self::assertSame(ConsensusVerdict::SkipDisagree, $result['verdict']);
        self::assertSame([], $result['ours'], 'ours should not be compared when browsers disagree');
        self::assertStringContainsString('disagree', $result['reason']);
    }

    public function testTwoEnginesAgreeAndThirdDivergesConsensusIsTheTwo(): void
    {
        // Chromium + Firefox identical; WebKit drifts ~3 % (above
        // BROWSER_AGREE_FUZZ). Consensus is {chromium, firefox};
        // ours is judged against just those two.
        $solid = $this->makeSolidPng();
        $solid2 = $this->makeSolidPng();
        $drift = $this->makeDiffyPng(300);
        $ours = $this->makeSolidPng();
        $result = (new ConsensusScorer())->score($ours, [
            'chromium' => $solid,
            'firefox' => $solid2,
            'webkit' => $drift,
        ]);
        self::assertSame(ConsensusVerdict::Pass, $result['verdict']);
        self::assertEqualsCanonicalizing(
            ['chromium', 'firefox'],
            $result['consensus'],
        );
        self::assertArrayNotHasKey('webkit', $result['ours']);
    }

    public function testOneEngineYieldsInsufficientEngines(): void
    {
        $result = (new ConsensusScorer())->score(
            $this->makeSolidPng(),
            ['chromium' => $this->makeSolidPng()],
        );
        self::assertSame(ConsensusVerdict::InsufficientEngines, $result['verdict']);
        self::assertStringContainsString('only one engine', $result['reason']);
    }

    public function testZeroEnginesYieldsInsufficientEngines(): void
    {
        $result = (new ConsensusScorer())->score($this->makeSolidPng(), []);
        self::assertSame(ConsensusVerdict::InsufficientEngines, $result['verdict']);
    }

    /**
     * Build a solid-white 100×100 PNG.
     */
    private function makeSolidPng(): string
    {
        $img = imagecreatetruecolor(self::SIZE, self::SIZE);
        self::assertNotFalse($img);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $path = tempnam(sys_get_temp_dir(), 'consensus_test_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * Solid-white 100×100 PNG with `$diffPixels` random pixels flipped
     * to black. Picks the pixels deterministically by walking row-major
     * so we get a stable AE count.
     */
    private function makeDiffyPng(int $diffPixels): string
    {
        $img = imagecreatetruecolor(self::SIZE, self::SIZE);
        self::assertNotFalse($img);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $black = imagecolorallocate($img, 0, 0, 0);
        $painted = 0;
        for ($y = 0; $y < self::SIZE && $painted < $diffPixels; $y++) {
            for ($x = 0; $x < self::SIZE && $painted < $diffPixels; $x++) {
                imagesetpixel($img, $x, $y, $black);
                $painted++;
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'consensus_diffy_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);
        $this->tempFiles[] = $path;
        return $path;
    }
}
