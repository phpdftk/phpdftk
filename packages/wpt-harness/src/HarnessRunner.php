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

        // In-scope: render the test through phpdftk, rasterise via
        // Ghostscript, visually-diff against the WPT reference.
        return $this->runRendered($testId);
    }

    /**
     * Render an in-scope test, rasterise the resulting PDF, and
     * visually-diff against its WPT reference. Tests without a
     * `*-ref.{png,html,xht,svg}` sibling are reported as Skipped
     * — the harness can't know what "pass" means without one.
     */
    private function runRendered(string $testId): TestResult
    {
        $rootAbs = realpath($this->wptRoot);
        if ($rootAbs === false) {
            return new TestResult(
                testId: $testId,
                status: TestStatus::HarnessError,
                diffScore: 0.0,
                reason: "wpt root not accessible: $this->wptRoot",
                diffArtefactPath: null,
                renderMicros: 0.0,
            );
        }
        $testPath = $this->resolveTestFile($rootAbs, $testId);
        if ($testPath === null) {
            return new TestResult(
                testId: $testId,
                status: TestStatus::HarnessError,
                diffScore: 0.0,
                reason: 'test file not found for ID',
                diffArtefactPath: null,
                renderMicros: 0.0,
            );
        }
        $refPath = $this->locateReference($testPath);
        if ($refPath === null) {
            return new TestResult(
                testId: $testId,
                status: TestStatus::Skipped,
                diffScore: 0.0,
                reason: 'no -ref.{png,html,xht,svg} sibling found',
                diffArtefactPath: null,
                renderMicros: 0.0,
            );
        }

        $start = hrtime(true);
        try {
            $renderedPng = $this->renderToPng($testPath);
        } catch (\Throwable $e) {
            return new TestResult(
                testId: $testId,
                status: TestStatus::Fail,
                diffScore: 1.0,
                reason: 'render failed: ' . $e->getMessage(),
                diffArtefactPath: null,
                renderMicros: (hrtime(true) - $start) / 1000.0,
            );
        }
        $renderMicros = (hrtime(true) - $start) / 1000.0;

        try {
            $refPng = str_ends_with(strtolower($refPath), '.png')
                ? $refPath
                : $this->renderToPng($refPath);
        } catch (\Throwable $e) {
            @unlink($renderedPng);
            return new TestResult(
                testId: $testId,
                status: TestStatus::HarnessError,
                diffScore: 1.0,
                reason: 'reference render failed: ' . $e->getMessage(),
                diffArtefactPath: null,
                renderMicros: $renderMicros,
            );
        }

        $fuzzy = $this->parseFuzzyMeta($testPath);
        $diff = $this->scorer->diff($renderedPng, $refPng, $fuzzy['maxPixels']);
        @unlink($renderedPng);
        if ($refPng !== $refPath) {
            @unlink($refPng);
        }

        return new TestResult(
            testId: $testId,
            status: $diff['passed'] ? TestStatus::Pass : TestStatus::Fail,
            diffScore: $diff['score'],
            reason: $diff['reason'],
            diffArtefactPath: $diff['diffImage'] ?? null,
            renderMicros: $renderMicros,
        );
    }

    /**
     * Parse the WPT `<meta name="fuzzy">` annotation from a test file.
     *
     * Format (CSS-WG convention):
     *   maxDifference=A-B; totalPixels=C-D
     *   maxDifference=A-B;totalPixels=C-D
     *   A-B;C-D                              (positional shorthand)
     *
     * Both ranges are inclusive bounds; the *upper* bound is the one
     * the renderer must respect. We surface the upper-bound pixel
     * count to the Scorer so tests with relaxed tolerances (e.g.
     * `totalPixels=0-127500`) pass when our renderer is within
     * spec-allowed difference but not pixel-perfect.
     *
     * Returns `['maxPixels' => null]` when no annotation is present;
     * `null` tells the Scorer to use its default threshold.
     *
     * @return array{maxPixels: int|null}
     */
    private function parseFuzzyMeta(string $testPath): array
    {
        $head = @file_get_contents($testPath, false, null, 0, 64 * 1024);
        if ($head === false || $head === '') {
            return ['maxPixels' => null];
        }
        if (preg_match(
            '~<meta\s+[^>]*?name\s*=\s*["\']fuzzy["\']\s+[^>]*?content\s*=\s*["\']([^"\']+)["\']~i',
            $head,
            $m,
        ) !== 1) {
            return ['maxPixels' => null];
        }
        $content = trim($m[1]);
        // Look for `totalPixels=<lo>-<hi>` first; fall back to the last
        // semicolon-separated range in positional shorthand.
        if (preg_match('~totalPixels\s*=\s*\d+\s*-\s*(\d+)~i', $content, $m2) === 1) {
            return ['maxPixels' => (int) $m2[1]];
        }
        $parts = array_map('trim', explode(';', $content));
        if (count($parts) >= 2 && preg_match('~^\d+\s*-\s*(\d+)$~', $parts[1], $m3) === 1) {
            return ['maxPixels' => (int) $m3[1]];
        }
        return ['maxPixels' => null];
    }

    private function resolveTestFile(string $rootAbs, string $testId): ?string
    {
        foreach (self::TEST_EXTENSIONS as $ext) {
            $candidate = $rootAbs . '/' . $testId . '.' . $ext;
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Locate the reference rendering for a WPT reftest. WPT supports
     * two conventions:
     *
     *  1. **Filename**: a sibling `<stem>-ref.{png,html,xht,svg}`
     *     file. Simple and unambiguous; PNG wins when both exist
     *     since it short-circuits a re-render.
     *
     *  2. **`<link rel="match" href="…">`** inside the test's
     *     `<head>`. The actual WPT corpus uses this for the
     *     majority of tests — the reference often lives in a
     *     sibling directory, sometimes with a name unrelated to
     *     the test stem.
     *
     * `rel="mismatch"` is the negative variant and is skipped here
     * — the harness doesn't yet implement "must not match"
     * semantics (a follow-up).
     */
    private function locateReference(string $testPath): ?string
    {
        $info = pathinfo($testPath);
        $dir = $info['dirname'] ?? '.';
        $stem = $info['filename'] ?? '';
        $candidates = [
            $dir . '/' . $stem . '-ref.png',
            $dir . '/' . $stem . '-ref.html',
            $dir . '/' . $stem . '-ref.xht',
            $dir . '/' . $stem . '-ref.svg',
        ];
        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                return $cand;
            }
        }
        return $this->locateLinkRelMatchReference($testPath);
    }

    /**
     * Parse `<link rel="match" href="…">` from the head of a
     * `.html` / `.xht` / `.svg` test file and resolve the href to
     * an on-disk path relative to the test. Returns the first
     * matching reference; `rel="mismatch"` is intentionally
     * ignored.
     *
     * Read is bounded to the first 64 KB so a malformed test
     * can't stall the harness — WPT's `<head>` always sits in
     * the first few hundred bytes anyway.
     */
    private function locateLinkRelMatchReference(string $testPath): ?string
    {
        $head = @file_get_contents($testPath, false, null, 0, 64 * 1024);
        if ($head === false || $head === '') {
            return null;
        }
        // Match either attribute order (rel-first or href-first) by trying
        // two patterns rather than alternation — keeps PHPStan happy and
        // makes the failure mode obvious.
        $relFirst = '~<link\s+[^>]*?rel\s*=\s*["\']match["\']\s+[^>]*?href\s*=\s*["\']([^"\']+)["\']~i';
        $hrefFirst = '~<link\s+[^>]*?href\s*=\s*["\']([^"\']+)["\']\s+[^>]*?rel\s*=\s*["\']match["\']~i';
        $href = null;
        if (preg_match($relFirst, $head, $matches) === 1) {
            $href = $matches[1];
        } elseif (preg_match($hrefFirst, $head, $matches) === 1) {
            $href = $matches[1];
        }
        if ($href === null) {
            return null;
        }
        $dir = dirname($testPath);
        $resolved = str_starts_with($href, '/')
            ? $this->wptRoot . $href
            : $dir . '/' . $href;
        $real = realpath($resolved);
        return ($real !== false && is_file($real)) ? $real : null;
    }

    /**
     * Render a single test file through the phpdftk pipeline and
     * rasterise the first page of the resulting PDF.
     */
    private function renderToPng(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $pdfBytes = $ext === 'svg'
            ? $this->renderSvgToPdf($path)
            : $this->renderHtmlToPdf($path);
        $pdfPath = tempnam(sys_get_temp_dir(), 'wpt_pdf_') . '.pdf';
        file_put_contents($pdfPath, $pdfBytes);
        try {
            return $this->rasteriser->rasterise($pdfPath, 0);
        } finally {
            @unlink($pdfPath);
        }
    }

    private function renderHtmlToPdf(string $path): string
    {
        if (!class_exists('Phpdftk\\HtmlToPdf\\Renderer')) {
            throw new \RuntimeException('phpdftk/html-to-pdf not installed');
        }
        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException("could not read test file: $path");
        }
        // Sandbox to the WPT corpus root so refs in `reference/`
        // subdirs can resolve `../support/img.png` siblings of the
        // test directory. baseDir alone is too tight — the default
        // ResourceLoader sandbox is the same as baseDir, which
        // rejects any `..` walk.
        $renderer = new \Phpdftk\HtmlToPdf\Renderer(
            (new \Phpdftk\HtmlToPdf\RendererOptions())
                ->withBaseDir(dirname($path))
                ->withSandboxRoot($this->wptRoot),
        );
        $result = $renderer->render($html);
        return $result->writer->toBytes();
    }

    private function renderSvgToPdf(string $path): string
    {
        if (!class_exists('Phpdftk\\SvgToPdf\\SvgRenderer')
            || !class_exists('Phpdftk\\Svg\\Parser')
            || !class_exists('Phpdftk\\Pdf\\Writer\\PdfWriter')
        ) {
            throw new \RuntimeException('svg-to-pdf renderer stack not installed');
        }
        $svgSource = file_get_contents($path);
        if ($svgSource === false) {
            throw new \RuntimeException("could not read test file: $path");
        }
        $writer = new \Phpdftk\Pdf\Writer\PdfWriter();
        $page = $writer->addPage();
        $svgDoc = (new \Phpdftk\Svg\Parser())->parse($svgSource);
        $renderer = new \Phpdftk\SvgToPdf\SvgRenderer($page, $writer);
        $renderer->draw($svgDoc, x: 0, y: 0);
        return $writer->toBytes();
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
