<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Orchestrator for the cross-browser PDF oracle. For each test fixture:
 *
 *   1. Render the test through ours.
 *   2. Render the test through each available browser engine (Chromium,
 *      Firefox, WebKit), backed by {@see BrowserOracle}'s file cache.
 *   3. Rasterise every PDF to PNG via {@see Rasteriser}.
 *   4. Hand the PNGs to {@see ConsensusScorer} for a verdict.
 *
 * The result includes the consensus verdict, per-engine PNG paths
 * (for diagnostics), reasoning string, and timing data. CLI callers
 * format it for humans; CI callers serialise it for downstream
 * comparison.
 */
final class CrossBrowserRunner
{
    private const ALL_ENGINES = ['chromium', 'firefox', 'webkit'];

    /** @var list<string> */
    private readonly array $resolvedEngines;

    public function __construct(
        private readonly BrowserOracle $oracle = new BrowserOracle(),
        private readonly Rasteriser $rasteriser = new Rasteriser(),
        private readonly ConsensusScorer $scorer = new ConsensusScorer(),
        /**
         * Engines to attempt for each test. Order doesn't matter; an
         * unavailable engine is skipped silently and noted in the
         * result. Defaults to all three Phase-A engines; overridable
         * via the `PHPDFTK_CROSS_BROWSER_ENGINES` env var (comma-
         * separated). Useful for skipping Firefox-via-Docker on
         * macOS hosts where Rosetta blocks Firefox's software
         * renderer (Phase A2 finding) — set
         * `PHPDFTK_CROSS_BROWSER_ENGINES=chromium,webkit` and the
         * harness skips Firefox entirely instead of paying the 60-s
         * Docker timeout per fixture.
         *
         * @var list<string>
         */
        ?array $engines = null,
        /**
         * Pass {@see ConsensusScorer::OURS_FUZZ_GEOMETRY} for
         * pure-shape fixtures, {@see ConsensusScorer::OURS_FUZZ_TEXT}
         * for text-bearing fixtures, or supply per-test overrides via
         * the runner's per-call argument.
         */
        private readonly float $defaultFuzzBudget = ConsensusScorer::OURS_FUZZ_GEOMETRY,
    ) {
        $envOverride = getenv('PHPDFTK_CROSS_BROWSER_ENGINES');
        if ($engines === null && is_string($envOverride) && $envOverride !== '') {
            $engines = array_values(array_filter(
                array_map('trim', explode(',', $envOverride)),
                static fn(string $name) => in_array($name, self::ALL_ENGINES, true),
            ));
        }
        $this->resolvedEngines = $engines ?? self::ALL_ENGINES;
    }

    /**
     * Run the oracle on a single test fixture. Returns an associative
     * result the CLI / CI layer can format.
     *
     * `$fuzzBudget` overrides {@see self::$defaultFuzzBudget} for this
     * test only — pass null to use the default.
     *
     * @return array{
     *   testId: string,
     *   verdict: ConsensusVerdict,
     *   reason: string,
     *   consensus: list<string>,
     *   ourPng: string,
     *   enginePngs: array<string, string>,
     *   engineMissing: list<string>,
     *   pairs: array<string, array<string, float>>,
     *   ours: array<string, float>,
     *   renderMicros: float,
     * }
     */
    public function runOne(
        string $testId,
        string $testPath,
        ?float $fuzzBudget = null,
    ): array {
        $budget = $fuzzBudget ?? $this->defaultFuzzBudget;
        $start = hrtime(true);

        // Render ours via the in-process PHP renderer, then rasterise.
        $oursPdf = $this->renderOurs($testPath);
        $ourPng = $this->rasteriser->rasterise($oursPdf);
        @unlink($oursPdf);

        $enginePngs = [];
        $engineMissing = [];
        foreach ($this->resolvedEngines as $engine) {
            try {
                $pdf = $this->oracle->render($engine, $testPath);
            } catch (\Throwable $err) {
                $engineMissing[] = $engine;
                continue;
            }
            if ($pdf === null) {
                $engineMissing[] = $engine;
                continue;
            }
            try {
                $enginePngs[$engine] = $this->rasteriser->rasterise($pdf);
            } catch (\Throwable $err) {
                $engineMissing[] = $engine;
            }
        }

        $score = $this->scorer->score($ourPng, $enginePngs, $budget);
        $renderMicros = (hrtime(true) - $start) / 1000.0;

        return [
            'testId' => $testId,
            'verdict' => $score['verdict'],
            'reason' => $score['reason'],
            'consensus' => $score['consensus'],
            'ourPng' => $ourPng,
            'enginePngs' => $enginePngs,
            'engineMissing' => $engineMissing,
            'pairs' => $score['pairs'],
            'ours' => $score['ours'],
            'renderMicros' => $renderMicros,
        ];
    }

    /**
     * Render a fixture through our PHP renderer to a temp PDF; returns
     * the path. Caller unlinks. We don't go through `runOne()`'s
     * cache because our renderer is in-process and cheap to re-run;
     * caching the engine outputs is where the win is.
     */
    private function renderOurs(string $testPath): string
    {
        $html = file_get_contents($testPath);
        if ($html === false) {
            throw new \RuntimeException("could not read fixture: $testPath");
        }
        $opts = (new RendererOptions())->withBaseDir(dirname($testPath));
        $renderer = new Renderer($opts);
        $writer = new PdfWriter();
        $renderer->renderInto($writer, $html);
        $tmpPath = tempnam(sys_get_temp_dir(), 'xb_ours_') . '.pdf';
        file_put_contents($tmpPath, $writer->toBytes());
        return $tmpPath;
    }

    /**
     * Clean up rasterised PNGs from a result. Call after consuming the
     * artefacts; the engine PDFs in the oracle cache are NOT removed.
     */
    public function cleanupResult(array $result): void
    {
        foreach (array_merge([$result['ourPng']], array_values($result['enginePngs'])) as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
