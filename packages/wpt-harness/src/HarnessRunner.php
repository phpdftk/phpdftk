<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * End-to-end WPT test runner — walks the corpus under
 * `vendor-data/wpt/`, classifies each test against the
 * {@see Manifest}, renders the in-scope tests through
 * `phpdftk/html-to-pdf` (or `phpdftk/svg-to-pdf` for SVG tests),
 * rasterises via {@see Rasteriser}, diffs via {@see Scorer}, and
 * emits a per-test {@see TestResult} ledger.
 *
 * Phase 4A.1.
 *
 * Walker shape:
 *
 *   foreach (glob(WPT_ROOT . '/[a-z0-9]*\/**\/*-001.html') as $testHtml):
 *     $testId = self::testIdFromPath($testHtml);
 *     $verdict = $manifest->classify($testId);
 *     if ($verdict['status'] !== TestStatus::Skipped):
 *         $results[] = new TestResult($testId, $verdict['status'], ...);
 *         continue;
 *     $renderedPdf = $renderer->render($testHtml);
 *     $renderedPng = $rasteriser->rasterise($renderedPdf);
 *     $score = $scorer->diff($renderedPng, $refImageFor($testId));
 *     $results[] = new TestResult(...);
 *
 * Phase 4A.1 implements the walker; 4A.2 / 4A.3 / 4A.4 are the
 * collaborators it delegates to.
 */
final class HarnessRunner
{
    public function __construct(
        private readonly Manifest $manifest,
        private readonly Rasteriser $rasteriser,
        private readonly Scorer $scorer,
        private readonly string $wptRoot,
    ) {}

    /**
     * Run the full harness corpus. Returns one {@see TestResult} per
     * test that was either rendered or explicitly classified.
     *
     * Phase 4A.1 implements this.
     *
     * @param string|null $filter Glob to restrict the corpus
     *                            (`css/css-transforms/*`, etc.).
     * @return list<TestResult>
     */
    public function run(?string $filter = null): array
    {
        unset($filter);
        throw new \RuntimeException('4A.1 not yet implemented');
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
}
