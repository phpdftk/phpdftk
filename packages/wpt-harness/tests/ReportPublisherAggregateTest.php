<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\ReportPublisher;
use Phpdftk\WptHarness\TestResult;
use Phpdftk\WptHarness\TestStatus;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4A.0 — package scaffold sanity test. The only piece of real
 * logic in the scaffold is `ReportPublisher::aggregate`, which the
 * dashboard publisher (4A.5) and the PR-comment delta (4A.6) both
 * call. Verify the bucket counts and the in-scope pass rate
 * arithmetic now so the call sites can rely on it as we wire up the
 * rest of the harness.
 */
final class ReportPublisherAggregateTest extends TestCase
{
    private function row(string $id, TestStatus $status): TestResult
    {
        return new TestResult(
            testId: $id,
            status: $status,
            diffScore: 0.0,
            reason: null,
            diffArtefactPath: null,
            renderMicros: 0.0,
        );
    }

    public function testEmptyResultSetHasZeroPassRate(): void
    {
        $agg = ReportPublisher::aggregate([]);
        self::assertSame(0, $agg['inScopeTotal']);
        self::assertSame(0.0, $agg['inScopePassRate']);
    }

    public function testInScopePassRateIgnoresOutOfScopeAndPendingSubstrate(): void
    {
        // 2 passes + 1 fail in-scope → rate = 2/3.
        // 2 out-of-scope + 1 pending-substrate don't shift the rate.
        $results = [
            $this->row('a', TestStatus::Pass),
            $this->row('b', TestStatus::Pass),
            $this->row('c', TestStatus::Fail),
            $this->row('d', TestStatus::OutOfScope),
            $this->row('e', TestStatus::OutOfScope),
            $this->row('f', TestStatus::PendingSubstrate),
        ];
        $agg = ReportPublisher::aggregate($results);

        self::assertSame(2, $agg['pass']);
        self::assertSame(1, $agg['fail']);
        self::assertSame(2, $agg['outOfScope']);
        self::assertSame(1, $agg['pendingSubstrate']);
        self::assertSame(3, $agg['inScopeTotal']);
        self::assertEqualsWithDelta(2.0 / 3.0, $agg['inScopePassRate'], 1e-9);
    }

    public function testHarnessErrorsCountInTheirOwnBucketAndNotInPassRate(): void
    {
        // Harness errors are renderer-independent — they're harness
        // bugs, not spec failures. They get their own bucket and
        // don't pollute the in-scope numerator OR denominator.
        $results = [
            $this->row('a', TestStatus::Pass),
            $this->row('b', TestStatus::HarnessError),
        ];
        $agg = ReportPublisher::aggregate($results);

        self::assertSame(1, $agg['pass']);
        self::assertSame(0, $agg['fail']);
        self::assertSame(1, $agg['harnessError']);
        self::assertSame(1, $agg['inScopeTotal']);
        self::assertSame(1.0, $agg['inScopePassRate']);
    }

    public function testSkippedTestsExcludedFromPassRate(): void
    {
        // Skipped = in-scope but the harness couldn't execute (no
        // reference image, unsupported WPT test type, etc.). Counted
        // as a harness gap, not a spec failure.
        $results = [
            $this->row('a', TestStatus::Pass),
            $this->row('b', TestStatus::Skipped),
            $this->row('c', TestStatus::Skipped),
        ];
        $agg = ReportPublisher::aggregate($results);

        self::assertSame(1, $agg['pass']);
        self::assertSame(2, $agg['skipped']);
        self::assertSame(1, $agg['inScopeTotal']);
        self::assertSame(1.0, $agg['inScopePassRate']);
    }
}
