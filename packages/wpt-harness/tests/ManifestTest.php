<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\Manifest;
use Phpdftk\WptHarness\TestStatus;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4A.4 — Manifest classifier. Verifies the glob → regex
 * translation, the out-of-scope / pending-substrate / in-scope
 * verdict shape, and the rule-file loader's tolerance of malformed
 * input.
 */
final class ManifestTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Glob matching
    // -----------------------------------------------------------------------

    public function testSingleStarMatchesWithinASegment(): void
    {
        // `*` doesn't cross `/` boundaries.
        self::assertTrue(Manifest::matches('css/css-color/lab-*', 'css/css-color/lab-001'));
        self::assertTrue(Manifest::matches('css/css-color/lab-*', 'css/css-color/lab-many-words'));
        self::assertFalse(Manifest::matches('css/css-color/lab-*', 'css/css-color/oklab-001'));
        self::assertFalse(Manifest::matches('css/css-color/lab-*', 'css/css-color/lab/subdir/001'));
    }

    public function testDoubleStarMatchesAcrossSegments(): void
    {
        self::assertTrue(Manifest::matches('css/css-scroll-snap/**', 'css/css-scroll-snap/x'));
        self::assertTrue(Manifest::matches('css/css-scroll-snap/**', 'css/css-scroll-snap/a/b/c-001'));
        self::assertFalse(Manifest::matches('css/css-scroll-snap/**', 'css/css-other/x'));
    }

    public function testLeadingDoubleStarMatchesAnyPrefix(): void
    {
        self::assertTrue(Manifest::matches('**/historical/**', 'css/css-color/historical/legacy'));
        self::assertTrue(Manifest::matches('**/historical/**', 'svg/historical/foo'));
        self::assertFalse(Manifest::matches('**/historical/**', 'css/css-color/not-historical-001'));
    }

    public function testRegexMetacharactersAreEscaped(): void
    {
        // Test author writes `(legacy)` literally — should match
        // exactly that string, not be interpreted as a regex group.
        self::assertTrue(Manifest::matches('css/foo/bar(legacy)', 'css/foo/bar(legacy)'));
        self::assertFalse(Manifest::matches('css/foo/bar(legacy)', 'css/foo/barlegacy'));

        // `.` and `+` are literal too.
        self::assertTrue(Manifest::matches('css/file.html', 'css/file.html'));
        self::assertFalse(Manifest::matches('css/file.html', 'css/fileXhtml'));
    }

    public function testAnchorsAreApplied(): void
    {
        // Pattern is anchored at both ends — `lab-*` doesn't match
        // when there's a prefix or suffix.
        self::assertFalse(Manifest::matches('lab-*', 'oklab-001'));
        self::assertFalse(Manifest::matches('lab-*', 'lab-001-trailing/extra'));
    }

    // -----------------------------------------------------------------------
    // classify()
    // -----------------------------------------------------------------------

    public function testInScopeTestReturnsNull(): void
    {
        $manifest = new Manifest(
            outOfScopeRules: [['glob' => 'css/css-scroll-snap/**', 'reason' => 'no scroll']],
            pendingSubstrateRules: [],
        );
        self::assertNull($manifest->classify('css/css-color/srgb-001'));
    }

    public function testOutOfScopeRuleReturnsVerdictAndReason(): void
    {
        $manifest = new Manifest(
            outOfScopeRules: [['glob' => 'css/css-scroll-snap/**', 'reason' => 'no scroll in PDF']],
        );
        $verdict = $manifest->classify('css/css-scroll-snap/snap-001');
        self::assertIsArray($verdict);
        self::assertSame(TestStatus::OutOfScope, $verdict['status']);
        self::assertSame('no scroll in PDF', $verdict['reason']);
    }

    public function testPendingSubstrateRuleReturnsVerdictWithPhase(): void
    {
        $manifest = new Manifest(
            outOfScopeRules: [],
            pendingSubstrateRules: [[
                'glob' => 'css/filter-effects/**',
                'reason' => 'needs 4C raster',
                'phase' => '4C',
            ]],
        );
        $verdict = $manifest->classify('css/filter-effects/blur-001');
        self::assertIsArray($verdict);
        self::assertSame(TestStatus::PendingSubstrate, $verdict['status']);
        self::assertSame('needs 4C raster', $verdict['reason']);
        self::assertSame('4C', $verdict['phase']);
    }

    public function testOutOfScopeWinsOverPendingSubstrate(): void
    {
        // Both rule tables match — out-of-scope is checked first so
        // it takes precedence.
        $manifest = new Manifest(
            outOfScopeRules: [['glob' => 'css/**', 'reason' => 'no CSS']],
            pendingSubstrateRules: [['glob' => 'css/filter-effects/**', 'reason' => 'needs 4C', 'phase' => '4C']],
        );
        $verdict = $manifest->classify('css/filter-effects/blur-001');
        self::assertIsArray($verdict);
        self::assertSame(TestStatus::OutOfScope, $verdict['status']);
    }

    public function testFirstMatchingRuleWins(): void
    {
        // Two out-of-scope rules both match — the first one in the
        // list wins, including its reason string.
        $manifest = new Manifest(
            outOfScopeRules: [
                ['glob' => 'css/css-color/lab-*', 'reason' => 'specific reason'],
                ['glob' => 'css/css-color/**', 'reason' => 'broad reason'],
            ],
        );
        $verdict = $manifest->classify('css/css-color/lab-001');
        self::assertIsArray($verdict);
        self::assertSame('specific reason', $verdict['reason']);
    }

    public function testPendingSubstrateWithoutPhaseOmitsPhaseKey(): void
    {
        $manifest = new Manifest(
            pendingSubstrateRules: [['glob' => 'foo/**', 'reason' => 'needs work']],
        );
        $verdict = $manifest->classify('foo/x');
        self::assertIsArray($verdict);
        self::assertArrayNotHasKey('phase', $verdict);
    }

    // -----------------------------------------------------------------------
    // loadFromDirectory()
    // -----------------------------------------------------------------------

    public function testLoadFromMissingDirectoryReturnsEmptyManifest(): void
    {
        $manifest = Manifest::loadFromDirectory(sys_get_temp_dir() . '/wpt-harness-nonexistent-' . uniqid());
        self::assertSame([], $manifest->outOfScopeRules());
        self::assertSame([], $manifest->pendingSubstrateRules());
    }

    public function testLoadFromDirectoryParsesJsonRuleFiles(): void
    {
        $dir = $this->makeFixtureDir();
        try {
            file_put_contents($dir . '/_global.json', json_encode([
                'out-of-scope' => [
                    ['glob' => 'fetch/**', 'reason' => 'no network'],
                ],
                'pending-substrate' => [
                    ['glob' => 'css/filter-effects/**', 'reason' => 'needs 4C', 'phase' => '4C'],
                ],
            ]));
            $manifest = Manifest::loadFromDirectory($dir);
            self::assertCount(1, $manifest->outOfScopeRules());
            self::assertCount(1, $manifest->pendingSubstrateRules());
            self::assertSame('fetch/**', $manifest->outOfScopeRules()[0]['glob']);
            self::assertSame('4C', $manifest->pendingSubstrateRules()[0]['phase'] ?? null);
        } finally {
            $this->cleanFixtureDir($dir);
        }
    }

    public function testLoadFromDirectoryLoadsGlobalFirstThenAlphabetical(): void
    {
        // _global.json should come first regardless of alphabetical
        // order, so its rules are checked before per-spec ones.
        $dir = $this->makeFixtureDir();
        try {
            file_put_contents($dir . '/zzz.json', json_encode([
                'out-of-scope' => [['glob' => 'zzz/**', 'reason' => 'z']],
            ]));
            file_put_contents($dir . '/_global.json', json_encode([
                'out-of-scope' => [['glob' => 'aaa/**', 'reason' => 'global']],
            ]));
            file_put_contents($dir . '/css.json', json_encode([
                'out-of-scope' => [['glob' => 'css/**', 'reason' => 'css']],
            ]));
            $manifest = Manifest::loadFromDirectory($dir);
            $globs = array_map(static fn(array $r) => $r['glob'], $manifest->outOfScopeRules());
            self::assertSame(['aaa/**', 'css/**', 'zzz/**'], $globs);
        } finally {
            $this->cleanFixtureDir($dir);
        }
    }

    public function testLoadFromDirectorySkipsPrivateUnderscoreFiles(): void
    {
        // `_wip.json` is a draft scratch file — skipped. Only
        // `_global.json` gets the special treatment.
        $dir = $this->makeFixtureDir();
        try {
            file_put_contents($dir . '/_wip.json', json_encode([
                'out-of-scope' => [['glob' => 'draft/**', 'reason' => 'draft']],
            ]));
            file_put_contents($dir . '/css.json', json_encode([
                'out-of-scope' => [['glob' => 'css/**', 'reason' => 'css']],
            ]));
            $manifest = Manifest::loadFromDirectory($dir);
            self::assertCount(1, $manifest->outOfScopeRules());
            self::assertSame('css/**', $manifest->outOfScopeRules()[0]['glob']);
        } finally {
            $this->cleanFixtureDir($dir);
        }
    }

    public function testLoadFromDirectoryTolerantToMalformedJson(): void
    {
        $dir = $this->makeFixtureDir();
        try {
            file_put_contents($dir . '/bad.json', '{ this is not valid');
            file_put_contents($dir . '/good.json', json_encode([
                'out-of-scope' => [['glob' => 'good/**', 'reason' => 'fine']],
            ]));
            $manifest = Manifest::loadFromDirectory($dir);
            // The bad file is silently skipped; the good one loads.
            self::assertCount(1, $manifest->outOfScopeRules());
        } finally {
            $this->cleanFixtureDir($dir);
        }
    }

    public function testLoadFromDirectoryDropsMalformedRuleEntries(): void
    {
        $dir = $this->makeFixtureDir();
        try {
            file_put_contents($dir . '/rules.json', json_encode([
                'out-of-scope' => [
                    ['glob' => 'fine/**', 'reason' => 'ok'],
                    ['glob' => 'missing-reason/**'],
                    ['no-glob' => 'x', 'reason' => 'oops'],
                    'not-an-object',
                ],
                '_comment' => 'authors can stash notes here',
            ]));
            $manifest = Manifest::loadFromDirectory($dir);
            self::assertCount(1, $manifest->outOfScopeRules());
        } finally {
            $this->cleanFixtureDir($dir);
        }
    }

    // -----------------------------------------------------------------------
    // Integration with the shipped rule files
    // -----------------------------------------------------------------------

    public function testShippedRuleFilesLoadCleanly(): void
    {
        $manifestDir = __DIR__ . '/../manifest';
        $manifest = Manifest::loadFromDirectory($manifestDir);

        // We shipped 3+ files with substantive rule sets; the loader
        // should expose them.
        self::assertGreaterThan(40, count($manifest->outOfScopeRules()));
        self::assertGreaterThan(10, count($manifest->pendingSubstrateRules()));
    }

    public function testShippedRulesClassifyKnownOutOfScopePaths(): void
    {
        $manifest = Manifest::loadFromDirectory(__DIR__ . '/../manifest');

        // Spot-check the contract from docs/spec/out-of-scope.md.
        foreach ([
            'fetch/api/basic',
            'webrtc/RTCPeerConnection-constructor',
            'webaudio/the-audiocontext-interface/audiocontext-001',
            'IndexedDB/idbtransaction-oncomplete',
            'webgl/conformance/glsl/misc/shader-with-array-of-structs-001',
            'webxr/getInputSources_emulated',
            'css/css-scroll-snap/snap-001',
            'css/css-will-change/will-change-001',
            'html/dom/events/EventTarget',
            'svg/interact/struct-cursor-01-b',
        ] as $testId) {
            $verdict = $manifest->classify($testId);
            self::assertNotNull($verdict, "expected $testId to be classified");
            self::assertSame(TestStatus::OutOfScope, $verdict['status'], "expected $testId to be OutOfScope");
        }
    }

    public function testShippedRulesClassifyKnownPendingSubstrate(): void
    {
        $manifest = Manifest::loadFromDirectory(__DIR__ . '/../manifest');

        foreach ([
            'css/filter-effects/filter-blur-001' => '4C',
            'css/css-color/relative-color-001' => '4E',
            'css/css-fonts/at-font-face-src-url' => '4F',
            'css/css-page/at-page-margin' => '4G',
            'svg/filters/filter-displacement-map-01' => '4C',
        ] as $testId => $expectedPhase) {
            $verdict = $manifest->classify($testId);
            self::assertNotNull($verdict, "expected $testId to be classified");
            self::assertSame(TestStatus::PendingSubstrate, $verdict['status']);
            self::assertSame($expectedPhase, $verdict['phase'] ?? null, "expected $testId to be phase $expectedPhase");
        }
    }

    public function testShippedRulesLeaveCommonCssInScope(): void
    {
        $manifest = Manifest::loadFromDirectory(__DIR__ . '/../manifest');

        // Plain in-scope CSS — no rule should match. The runner
        // will need to render and score these.
        foreach ([
            'css/css-transforms/transform-2d-translate-001',
            'css/css-flexbox/flex-direction-001',
            'css/css-grid/grid-template-areas-001',
            'css/css-backgrounds/background-color-001',
            'html/semantics/grouping-content/the-p-element-001',
            'svg/painting/fill-001',
        ] as $testId) {
            self::assertNull(
                $manifest->classify($testId),
                "expected $testId to be in-scope (no rule match)",
            );
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeFixtureDir(): string
    {
        $dir = sys_get_temp_dir() . '/wpt-harness-test-' . uniqid();
        mkdir($dir);
        return $dir;
    }

    private function cleanFixtureDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
