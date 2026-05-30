<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Per-test result row written to the harness ledger
 * (`var/wpt/report.json`). One instance per WPT test per harness run.
 *
 * Phase 4A.5 serialises a collection of these into the published
 * dashboard at `docs/site/standards/spec-coverage/wpt-report.md`.
 */
final readonly class TestResult
{
    /**
     * @param string $testId          Stable WPT test identifier — the
     *                                relative path under
     *                                `vendor-data/wpt/`, minus the
     *                                file extension. Example:
     *                                `css/css-transforms/transform-2d-001`.
     * @param TestStatus $status      Where the test landed in the
     *                                {@see TestStatus} bucket
     *                                hierarchy.
     * @param float $diffScore        Perceptual diff score in
     *                                `[0.0, 1.0]`. `0.0` is byte-
     *                                identical to the reference;
     *                                `1.0` is "completely
     *                                different". Pass tolerance is
     *                                set by the harness config and
     *                                defaults to `0.01` per the WPT
     *                                reftest convention.
     * @param string|null $reason     Human-readable explanation when
     *                                the status is `OutOfScope`,
     *                                `PendingSubstrate`, or
     *                                `Skipped`. Null when status is
     *                                `Pass` or `Fail`.
     * @param string|null $diffArtefactPath Path under `var/wpt/diffs/`
     *                                where rendered.png + ref.png +
     *                                diff.png live. Null when no
     *                                diff was produced (out-of-scope
     *                                / skipped tests).
     * @param float $renderMicros     Wall-clock time the renderer
     *                                spent on this test, in
     *                                microseconds. Tracked so the
     *                                dashboard can surface
     *                                performance regressions
     *                                alongside conformance ones.
     */
    public function __construct(
        public string $testId,
        public TestStatus $status,
        public float $diffScore,
        public ?string $reason,
        public ?string $diffArtefactPath,
        public float $renderMicros,
    ) {}
}
