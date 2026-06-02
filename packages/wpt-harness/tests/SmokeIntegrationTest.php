<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\HarnessRunner;
use Phpdftk\WptHarness\Manifest;
use Phpdftk\WptHarness\Rasteriser;
use Phpdftk\WptHarness\ReportPublisher;
use Phpdftk\WptHarness\Scorer;
use Phpdftk\WptHarness\TestStatus;
use PHPUnit\Framework\TestCase;

/**
 * Runs the bundled smoke-fixture set through the full pipeline
 * (render → rasterise → diff). Acts as a CI guardrail that the
 * harness machinery keeps working even when the WPT submodule
 * isn't populated.
 *
 * Each smoke fixture sits next to a `-ref.html` or `-ref.svg`
 * sibling that should render identically to the test (within the
 * default 1% fuzz). When any of them regresses, the harness has
 * broken — either the renderer no longer matches its own
 * reference, or the rasterise / score path is sick.
 *
 * Skips entirely if Ghostscript / ImageMagick aren't available
 * (e.g. on a developer machine without them installed) so the
 * regular `composer test` run stays portable.
 */
final class SmokeIntegrationTest extends TestCase
{
    private string $smokeRoot;

    protected function setUp(): void
    {
        $this->smokeRoot = realpath(__DIR__ . '/../fixtures/smoke') ?: '';
        if ($this->smokeRoot === '') {
            self::markTestSkipped('smoke fixtures directory missing');
        }
        $rasteriser = new Rasteriser();
        $scorer = new Scorer();
        if (!$rasteriser->isAvailable()) {
            self::markTestSkipped('Ghostscript (`gs`) not installed');
        }
        if (!$scorer->isAvailable()) {
            self::markTestSkipped('ImageMagick `compare` not installed');
        }
    }

    public function testAllSmokeFixturesPass(): void
    {
        $runner = new HarnessRunner(
            new Manifest(),
            new Rasteriser(),
            new Scorer(),
            $this->smokeRoot,
        );
        $results = $runner->run();
        self::assertNotEmpty(
            $results,
            'expected smoke fixtures to discover at least one test',
        );
        $agg = ReportPublisher::aggregate($results);

        // Every smoke test should be in-scope (no manifest rules
        // match) and pass perceptually against its reference. Surface
        // a useful diff in the failure message instead of a bare
        // counts mismatch.
        $failed = array_filter(
            $results,
            static fn($r) => $r->status === TestStatus::Fail,
        );
        $failedIds = array_map(
            static fn($r) => sprintf('%s (score %.4f, %s)', $r->testId, $r->diffScore, $r->reason ?? 'no reason'),
            $failed,
        );
        self::assertSame(
            [],
            $failedIds,
            'smoke fixtures regressed — at least one renders differently from its own reference',
        );
        self::assertGreaterThan(0, $agg['inScopeTotal']);
        self::assertSame(1.0, $agg['inScopePassRate']);
    }
}
