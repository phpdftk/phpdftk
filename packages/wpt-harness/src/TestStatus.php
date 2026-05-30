<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Per-test result status in the WPT harness ledger.
 *
 * Each WPT test runs through the harness and lands in exactly one of
 * these buckets per harness run. Only `Pass` and `Fail` count toward
 * the in-scope pass rate; `OutOfScope`, `PendingSubstrate`, and
 * `Skipped` are excluded from the denominator (see
 * `docs/spec/out-of-scope.md` for the contract).
 *
 * Phase 4A.4 wires this enum into the {@see Manifest} classifier.
 */
enum TestStatus: string
{
    /**
     * Visual diff against the WPT reference passed within tolerance.
     * Counts toward the in-scope pass rate.
     */
    case Pass = 'pass';

    /**
     * Visual diff exceeded tolerance, or the renderer crashed on the
     * input. Counts as a failure in the in-scope rate.
     */
    case Fail = 'fail';

    /**
     * Test exercises a surface listed in the out-of-scope ledger
     * (`docs/spec/out-of-scope.md`). Skipped at runtime; excluded
     * from both numerator and denominator of the pass rate.
     */
    case OutOfScope = 'out-of-scope';

    /**
     * Test exercises a feature whose Phase 4 substrate (4C raster,
     * 4D text shaping, 4E color engine, 4F resource loader, 4G paged
     * media) hasn't landed yet. Excluded from the pass rate but
     * reported separately so the dashboard can show "N tests blocked
     * on 4C".
     */
    case PendingSubstrate = 'pending-substrate';

    /**
     * Test is in-scope but the harness can't yet execute it
     * (unsupported WPT test type, missing reference image, etc.).
     * Excluded from the pass rate; tracked as a harness gap.
     */
    case Skipped = 'skipped';

    /**
     * Test crashed the harness itself (not the renderer). Counts as
     * a harness bug, not a renderer failure.
     */
    case HarnessError = 'harness-error';
}
