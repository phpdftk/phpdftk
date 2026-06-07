<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Cross-browser PDF oracle scorer (CSS Color 4 §17 / docs/plans/cross-
 * browser-oracle.md). Given rasterised PNGs of our PDF plus one or more
 * browser-engine PDFs, computes pairwise pixel-AE diffs and renders a
 * verdict per the three-way consensus rule:
 *
 *  - If at least two browser engines agree (within
 *    {@see self::BROWSER_AGREE_FUZZ}), the test is judged. They form
 *    the *consensus set*.
 *  - If fewer than two engines agree, return SKIP_DISAGREE — we can't
 *    judge ours when the browsers themselves disagree (treated as a
 *    browser-bug zone, not a test failure).
 *  - When the consensus set is established, our render must be within
 *    {@see self::OURS_FUZZ_GEOMETRY} (or the looser
 *    {@see self::OURS_FUZZ_TEXT} for text-heavy fixtures) of every
 *    engine in the set. Disagreement with any consensus engine fails.
 *
 * When only two engines are available (typical for Linux runners where
 * `webkit` doesn't ship), the rule collapses to "the two must agree";
 * one engine alone yields INSUFFICIENT_ENGINES.
 *
 * The scorer reuses {@see Scorer::diff()} for the underlying ImageMagick
 * `compare -metric AE` pass.
 */
final class ConsensusScorer
{
    /**
     * Browser-vs-browser agreement budget. Captures anti-aliasing,
     * subpixel rounding, system font hinting. Tighten after Phase B
     * empirical data; this initial value lets us classify obvious
     * "engines disagree" cases without flagging routine drift.
     */
    public const BROWSER_AGREE_FUZZ = 0.02; // 2% pixel AE

    /**
     * Ours-vs-consensus budget for fixtures that are pure shapes /
     * colours (no text). The print-options contract eliminates page-
     * extent drift; what's left is rasterisation noise.
     */
    public const OURS_FUZZ_GEOMETRY = 0.005; // 0.5%

    /**
     * Ours-vs-consensus budget for fixtures that contain text. Looser
     * because system font rasterisers disagree on hinting / subpixel
     * positioning even when the layout matches.
     */
    public const OURS_FUZZ_TEXT = 0.05; // 5%

    public function __construct(
        private readonly Scorer $scorer = new Scorer(),
    ) {}

    /**
     * Score `$oursPng` against the supplied engine renders.
     *
     * `$engines` keys are engine identifiers (`chromium`, `firefox`,
     * `webkit`); values are PNG paths. Any engine may be missing
     * (typical: `webkit` on Linux runners). Two or more engines must
     * be present for a verdict; one yields INSUFFICIENT_ENGINES.
     *
     * `$fuzzBudget` picks the ours-vs-consensus budget; pass either
     * {@see self::OURS_FUZZ_GEOMETRY} or {@see self::OURS_FUZZ_TEXT}.
     *
     * @param array<string, string> $engines
     *
     * @return array{
     *   verdict: ConsensusVerdict,
     *   reason: string,
     *   consensus: list<string>,
     *   pairs: array<string, array<string, float>>,
     *   ours: array<string, float>,
     * }
     */
    public function score(
        string $oursPng,
        array $engines,
        float $fuzzBudget = self::OURS_FUZZ_GEOMETRY,
    ): array {
        $names = array_keys($engines);
        sort($names);

        if (count($names) < 2) {
            return [
                'verdict' => ConsensusVerdict::InsufficientEngines,
                'reason' => count($names) === 1
                    ? "only one engine ($names[0]); need two to form consensus"
                    : 'no engines supplied; need at least two',
                'consensus' => [],
                'pairs' => [],
                'ours' => [],
            ];
        }

        // Pairwise browser agreements. Half-matrix to avoid duplicate
        // compare calls; access via `pairs[a][b]` after the loop.
        $pairs = [];
        foreach ($names as $i => $a) {
            $pairs[$a] = $pairs[$a] ?? [];
            for ($j = $i + 1; $j < count($names); $j++) {
                $b = $names[$j];
                $pairs[$b] = $pairs[$b] ?? [];
                $score = $this->compareScore($engines[$a], $engines[$b]);
                $pairs[$a][$b] = $score;
                $pairs[$b][$a] = $score;
            }
        }

        // Build consensus set: engines whose pairwise AE with every
        // other consensus member is within BROWSER_AGREE_FUZZ. Start
        // with the engine that has the most under-budget neighbours.
        $consensus = self::pickConsensus($names, $pairs, self::BROWSER_AGREE_FUZZ);
        if (count($consensus) < 2) {
            return [
                'verdict' => ConsensusVerdict::SkipDisagree,
                'reason' => self::describeDisagreement($names, $pairs),
                'consensus' => $consensus,
                'pairs' => $pairs,
                'ours' => [],
            ];
        }

        // Ours vs each consensus engine.
        $ours = [];
        $worst = 0.0;
        $worstEngine = $consensus[0];
        foreach ($consensus as $engine) {
            $score = $this->compareScore($oursPng, $engines[$engine]);
            $ours[$engine] = $score;
            if ($score > $worst) {
                $worst = $score;
                $worstEngine = $engine;
            }
        }
        if ($worst <= $fuzzBudget) {
            return [
                'verdict' => ConsensusVerdict::Pass,
                'reason' => sprintf(
                    'ours agrees with %s within %.2f%% (worst: %s at %.3f%%)',
                    implode(' + ', $consensus),
                    $fuzzBudget * 100.0,
                    $worstEngine,
                    $worst * 100.0,
                ),
                'consensus' => $consensus,
                'pairs' => $pairs,
                'ours' => $ours,
            ];
        }

        return [
            'verdict' => ConsensusVerdict::Fail,
            'reason' => sprintf(
                'ours diverges from %s consensus at %s (%.3f%% AE > %.2f%% budget)',
                implode(' + ', $consensus),
                $worstEngine,
                $worst * 100.0,
                $fuzzBudget * 100.0,
            ),
            'consensus' => $consensus,
            'pairs' => $pairs,
            'ours' => $ours,
        ];
    }

    /**
     * Run a single `compare -metric AE` against two PNG paths and
     * return the AE score in [0, 1].
     */
    private function compareScore(string $a, string $b): float
    {
        $result = $this->scorer->diff($a, $b);
        if (is_string($result['diffImage'] ?? null) && is_file($result['diffImage'])) {
            @unlink($result['diffImage']);
        }
        return $result['score'];
    }

    /**
     * Pick the largest subset of engines where every pair is within
     * `$budget`. Greedy: start from the densest neighbourhood and
     * accept additions that don't break agreement.
     *
     * @param list<string> $names
     * @param array<string, array<string, float>> $pairs
     * @return list<string>
     */
    private static function pickConsensus(array $names, array $pairs, float $budget): array
    {
        // Score each engine by how many under-budget neighbours it has.
        $neighbourhoods = [];
        foreach ($names as $name) {
            $count = 0;
            foreach ($pairs[$name] ?? [] as $score) {
                if ($score <= $budget) {
                    $count++;
                }
            }
            $neighbourhoods[$name] = $count;
        }
        arsort($neighbourhoods);
        $consensus = [];
        foreach (array_keys($neighbourhoods) as $candidate) {
            $ok = true;
            foreach ($consensus as $member) {
                if (($pairs[$candidate][$member] ?? PHP_FLOAT_MAX) > $budget) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $consensus[] = $candidate;
            }
        }
        return $consensus;
    }

    /**
     * Build a human-readable "browsers disagree" reason that names
     * the worst-offending pair.
     *
     * @param list<string> $names
     * @param array<string, array<string, float>> $pairs
     */
    private static function describeDisagreement(array $names, array $pairs): string
    {
        $worst = 0.0;
        $worstA = '';
        $worstB = '';
        foreach ($names as $i => $a) {
            for ($j = $i + 1; $j < count($names); $j++) {
                $b = $names[$j];
                $score = $pairs[$a][$b] ?? 0.0;
                if ($score > $worst) {
                    $worst = $score;
                    $worstA = $a;
                    $worstB = $b;
                }
            }
        }
        return sprintf(
            'browsers disagree (worst pair: %s vs %s at %.3f%% AE > %.2f%% budget)',
            $worstA,
            $worstB,
            $worst * 100.0,
            self::BROWSER_AGREE_FUZZ * 100.0,
        );
    }
}
