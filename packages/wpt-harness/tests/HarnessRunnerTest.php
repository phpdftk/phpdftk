<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\HarnessRunner;
use Phpdftk\WptHarness\Manifest;
use Phpdftk\WptHarness\Rasteriser;
use Phpdftk\WptHarness\Scorer;
use Phpdftk\WptHarness\TestStatus;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4A.1 — HarnessRunner directory walk + classification path.
 * Tests build a small fixture directory mirroring WPT's layout
 * (`css/`, `html/`, `svg/` subdirs) and verify the walker:
 *
 *  - Discovers `*.html`, `*.svg`, `*.xht` test files
 *  - Skips `*-ref.html` / `*-notref.html` reference siblings
 *  - Produces stable POSIX test IDs (relative path minus extension)
 *  - Dispatches each ID through Manifest::classify
 *  - Returns the right TestStatus per the manifest verdict
 *
 * Real WPT corpus runs land in CI once the submodule is added.
 */
final class HarnessRunnerTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        $this->fixtureRoot = sys_get_temp_dir() . '/wpt-harness-runner-' . uniqid();
        mkdir($this->fixtureRoot);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->fixtureRoot);
    }

    private function fixture(string $relativePath, string $contents = '<!doctype html><title>fixture</title>'): void
    {
        $path = $this->fixtureRoot . '/' . $relativePath;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }
        file_put_contents($path, $contents);
    }

    private function rrmdir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            if (is_dir($full)) {
                $this->rrmdir($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }

    private function runner(Manifest $manifest): HarnessRunner
    {
        return new HarnessRunner(
            $manifest,
            new Rasteriser(),
            new Scorer(),
            $this->fixtureRoot,
        );
    }

    // -----------------------------------------------------------------------
    // Test discovery
    // -----------------------------------------------------------------------

    public function testDiscoversHtmlSvgXhtFiles(): void
    {
        $this->fixture('css/css-color/lab-001.html');
        $this->fixture('html/dom/section-001.html');
        $this->fixture('svg/painting/fill-001.svg');
        $this->fixture('css/css-layout/grid-001.xht');

        $results = $this->runner(new Manifest())->run();
        $ids = array_map(static fn($r) => $r->testId, $results);
        sort($ids);

        self::assertSame([
            'css/css-color/lab-001',
            'css/css-layout/grid-001',
            'html/dom/section-001',
            'svg/painting/fill-001',
        ], $ids);
    }

    public function testIgnoresRefAndNotrefSiblings(): void
    {
        $this->fixture('css/transform-001.html');
        $this->fixture('css/transform-001-ref.html');
        $this->fixture('css/transform-001-notref.html');

        $results = $this->runner(new Manifest())->run();
        $ids = array_map(static fn($r) => $r->testId, $results);

        // Only the test file itself, not the reference siblings.
        self::assertSame(['css/transform-001'], $ids);
    }

    public function testIgnoresUnrelatedExtensions(): void
    {
        $this->fixture('css/test.html');
        $this->fixture('css/notes.txt');
        $this->fixture('css/script.js');
        $this->fixture('css/style.css');
        $this->fixture('css/meta.json');

        $results = $this->runner(new Manifest())->run();
        $ids = array_map(static fn($r) => $r->testId, $results);

        self::assertSame(['css/test'], $ids);
    }

    public function testMissingRootReturnsEmpty(): void
    {
        $runner = new HarnessRunner(
            new Manifest(),
            new Rasteriser(),
            new Scorer(),
            '/nonexistent/path/' . uniqid(),
        );
        self::assertSame([], $runner->run());
    }

    // -----------------------------------------------------------------------
    // Classification dispatch
    // -----------------------------------------------------------------------

    public function testOutOfScopeRulesProduceOutOfScopeResult(): void
    {
        $this->fixture('css/css-scroll-snap/snap-001.html');

        $manifest = new Manifest(
            outOfScopeRules: [['glob' => 'css/css-scroll-snap/**', 'reason' => 'no scroll']],
        );
        $results = $this->runner($manifest)->run();

        self::assertCount(1, $results);
        self::assertSame(TestStatus::OutOfScope, $results[0]->status);
        self::assertSame('no scroll', $results[0]->reason);
    }

    public function testPendingSubstrateRulesProducePendingSubstrateResult(): void
    {
        $this->fixture('css/filter-effects/blur-001.html');

        $manifest = new Manifest(
            pendingSubstrateRules: [['glob' => 'css/filter-effects/**', 'reason' => 'needs 4C', 'phase' => '4C']],
        );
        $results = $this->runner($manifest)->run();

        self::assertCount(1, $results);
        self::assertSame(TestStatus::PendingSubstrate, $results[0]->status);
        self::assertSame('needs 4C', $results[0]->reason);
    }

    public function testUnclassifiedInScopeTestSkippedWithReason(): void
    {
        $this->fixture('css/css-color/srgb-001.html');

        // Empty manifest — nothing matches → in-scope.
        $results = $this->runner(new Manifest())->run();

        self::assertCount(1, $results);
        // 4A.1 marks in-scope tests as Skipped until 4A.2 + 4A.3
        // (rasteriser + scorer) land.
        self::assertSame(TestStatus::Skipped, $results[0]->status);
        self::assertNotNull($results[0]->reason);
        self::assertStringContainsString('4A.2', $results[0]->reason);
    }

    // -----------------------------------------------------------------------
    // Filter
    // -----------------------------------------------------------------------

    public function testFilterRestrictsCorpus(): void
    {
        $this->fixture('css/css-color/lab-001.html');
        $this->fixture('css/css-transforms/transform-001.html');
        $this->fixture('svg/painting/fill-001.svg');

        $results = $this->runner(new Manifest())->run(filter: 'css/css-color/**');
        $ids = array_map(static fn($r) => $r->testId, $results);

        self::assertSame(['css/css-color/lab-001'], $ids);
    }

    public function testFilterSupportsRecursiveGlob(): void
    {
        $this->fixture('css/nested/deep/sub/test-001.html');
        $this->fixture('html/other/test-001.html');

        $results = $this->runner(new Manifest())->run(filter: 'css/**');
        $ids = array_map(static fn($r) => $r->testId, $results);

        self::assertSame(['css/nested/deep/sub/test-001'], $ids);
    }

    public function testFilterSupportsSingleStarWithinSegment(): void
    {
        $this->fixture('css/test-001.html');
        $this->fixture('css/other-001.html');
        $this->fixture('css/sub/test-001.html');

        $results = $this->runner(new Manifest())->run(filter: 'css/test-*');
        $ids = array_map(static fn($r) => $r->testId, $results);

        // `*` doesn't cross `/`, so the nested one is excluded.
        self::assertSame(['css/test-001'], $ids);
    }

    // -----------------------------------------------------------------------
    // runOne() shortcut
    // -----------------------------------------------------------------------

    public function testRunOneDoesNotTouchFilesystem(): void
    {
        // No fixture written — runOne() classifies by ID alone.
        $manifest = new Manifest(
            outOfScopeRules: [['glob' => 'css/css-scroll-snap/**', 'reason' => 'no scroll']],
        );
        $result = $this->runner($manifest)->runOne('css/css-scroll-snap/snap-001');

        self::assertSame(TestStatus::OutOfScope, $result->status);
        self::assertSame('css/css-scroll-snap/snap-001', $result->testId);
    }
}
