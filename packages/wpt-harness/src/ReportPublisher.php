<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Serialises a `TestResult[]` ledger into the published artefacts:
 *
 *   - `var/wpt/report.json`   — machine-readable, full per-test rows
 *   - `var/wpt/summary.md`    — human-readable summary (counts per
 *                               bucket, top failure clusters, deltas
 *                               vs prior run)
 *   - `docs/site/standards/spec-coverage/wpt-report.md` — published
 *                                                        dashboard
 *
 * Phase 4A.5 implements all three writers. The JSON ledger is the
 * input to Phase 4A.6 (PR comment delta) and to the spec inventory
 * regenerator (`composer spec-status`).
 */
final class ReportPublisher
{
    /**
     * @param list<TestResult> $results
     */
    public function publish(array $results, string $outputDir): void
    {
        unset($results, $outputDir);
        throw new \RuntimeException('4A.5 not yet implemented');
    }

    /**
     * Aggregate the bucket counts for a result set. Used by both
     * publish() and the summary writer.
     *
     * @param list<TestResult> $results
     * @return array{
     *     pass: int,
     *     fail: int,
     *     outOfScope: int,
     *     pendingSubstrate: int,
     *     skipped: int,
     *     harnessError: int,
     *     inScopeTotal: int,
     *     inScopePassRate: float
     * }
     */
    public static function aggregate(array $results): array
    {
        $counts = [
            'pass' => 0,
            'fail' => 0,
            'outOfScope' => 0,
            'pendingSubstrate' => 0,
            'skipped' => 0,
            'harnessError' => 0,
        ];
        foreach ($results as $result) {
            $counts[match ($result->status) {
                TestStatus::Pass => 'pass',
                TestStatus::Fail => 'fail',
                TestStatus::OutOfScope => 'outOfScope',
                TestStatus::PendingSubstrate => 'pendingSubstrate',
                TestStatus::Skipped => 'skipped',
                TestStatus::HarnessError => 'harnessError',
            }]++;
        }
        $inScopeTotal = $counts['pass'] + $counts['fail'];
        // PHP `int / int` returns int when divisible — force float so
        // the rate is always a float in [0.0, 1.0].
        $inScopePassRate = $inScopeTotal > 0 ? (float) $counts['pass'] / $inScopeTotal : 0.0;
        return [
            ...$counts,
            'inScopeTotal' => $inScopeTotal,
            'inScopePassRate' => $inScopePassRate,
        ];
    }
}
