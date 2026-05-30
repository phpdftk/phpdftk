<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * End-to-end WPT test runner — walks the corpus under `$wptRoot`,
 * classifies each test via {@see Manifest}, renders the in-scope
 * tests through `phpdftk/html-to-pdf` (or `phpdftk/svg-to-pdf` for
 * SVG tests), rasterises via {@see Rasteriser}, diffs via
 * {@see Scorer}, and emits a per-test {@see TestResult} ledger.
 *
 * Phase 4A.1 implements the walker + classification path. The
 * actual rendering / rasterisation / scoring lands in 4A.2 + 4A.3;
 * until those ship, in-scope tests are marked `Skipped` with a
 * reason explaining the missing substrate so the dashboard can
 * communicate progress accurately.
 *
 * Test discovery: recursive glob for HTML / XHT / SVG files under
 * `$wptRoot`. Files matching `*-ref.*` are treated as reference
 * renderings (skipped — they're the expected output for some
 * other test, not a test themselves).
 */
final class HarnessRunner
{
    /** @var list<string> File extensions recognised as test files. */
    private const TEST_EXTENSIONS = ['html', 'xht', 'xhtml', 'htm', 'svg'];

    public function __construct(
        private readonly Manifest $manifest,
        private readonly Rasteriser $rasteriser,
        private readonly Scorer $scorer,
        private readonly string $wptRoot,
    ) {}

    /**
     * Run the harness corpus. Returns one {@see TestResult} per
     * test that was either rendered or classified.
     *
     * `$filter` accepts the same glob syntax as the manifest rule
     * files (`*` matches within a segment, `**` matches across) —
     * tests whose ID doesn't match are excluded from the run.
     *
     * @return list<TestResult>
     */
    public function run(?string $filter = null): array
    {
        if (!is_dir($this->wptRoot)) {
            return [];
        }

        $results = [];
        foreach ($this->discoverTests($this->wptRoot) as $absolutePath) {
            $testId = $this->testIdFromPath($absolutePath);
            if ($testId === null) {
                continue;
            }
            if ($filter !== null && !Manifest::matches($filter, $testId)) {
                continue;
            }
            $results[] = $this->runOne($testId);
        }
        return $results;
    }

    /**
     * Classify a single test ID without scanning the filesystem.
     * Useful for `composer wpt classify <id>` and for tests of
     * this class.
     */
    public function runOne(string $testId): TestResult
    {
        $verdict = $this->manifest->classify($testId);
        if ($verdict !== null) {
            // Out-of-scope or pending-substrate — no render needed.
            return new TestResult(
                testId: $testId,
                status: $verdict['status'],
                diffScore: 0.0,
                reason: $verdict['reason'],
                diffArtefactPath: null,
                renderMicros: 0.0,
            );
        }

        // In-scope but the rasteriser / scorer haven't landed yet
        // (4A.2 / 4A.3). Mark as skipped with a reason so the
        // dashboard can communicate "blocked on 4A.2" rather than
        // surfacing as a silent pass.
        return new TestResult(
            testId: $testId,
            status: TestStatus::Skipped,
            diffScore: 0.0,
            reason: '4A.2 rasteriser + 4A.3 scorer not yet implemented',
            diffArtefactPath: null,
            renderMicros: 0.0,
        );
    }

    public function manifest(): Manifest
    {
        return $this->manifest;
    }

    public function rasteriser(): Rasteriser
    {
        return $this->rasteriser;
    }

    public function scorer(): Scorer
    {
        return $this->scorer;
    }

    public function wptRoot(): string
    {
        return $this->wptRoot;
    }

    /**
     * Recursively yield absolute paths to test files under the
     * given root. References (`*-ref.*`) are skipped — they're
     * targets for the diff scorer, not tests.
     *
     * @return iterable<string>
     */
    private function discoverTests(string $root): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $info) {
            assert($info instanceof \SplFileInfo);
            if (!$info->isFile()) {
                continue;
            }
            $extension = strtolower($info->getExtension());
            if (!in_array($extension, self::TEST_EXTENSIONS, true)) {
                continue;
            }
            $stem = $info->getBasename('.' . $info->getExtension());
            // `*-ref.html`, `*-ref.xht`, `*-notref.html` are
            // expected-rendering / negative-match siblings, not
            // tests themselves.
            if (str_ends_with($stem, '-ref') || str_ends_with($stem, '-notref')) {
                continue;
            }
            yield $info->getPathname();
        }
    }

    /**
     * Convert an absolute test-file path into a stable test ID —
     * the relative path under `$wptRoot`, minus the file
     * extension. POSIX-style separators so manifest globs match
     * cross-platform.
     */
    private function testIdFromPath(string $absolutePath): ?string
    {
        $rootAbs = realpath($this->wptRoot);
        $pathAbs = realpath($absolutePath);
        if ($rootAbs === false || $pathAbs === false) {
            return null;
        }
        if (!str_starts_with($pathAbs, $rootAbs)) {
            return null;
        }
        $relative = substr($pathAbs, strlen($rootAbs));
        $relative = ltrim($relative, '/\\');
        // Normalise to POSIX separators.
        $relative = str_replace('\\', '/', $relative);
        // Strip the extension.
        $dotPos = strrpos($relative, '.');
        if ($dotPos !== false) {
            $relative = substr($relative, 0, $dotPos);
        }
        return $relative;
    }
}
