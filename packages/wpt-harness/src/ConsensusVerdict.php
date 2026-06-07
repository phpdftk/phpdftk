<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Verdict returned by {@see ConsensusScorer::score()} for a single test.
 *
 * The cross-browser oracle classifies fixtures along two axes:
 * "did at least two browsers agree?" and "did we match that
 * consensus?" — these four cases cover the combinations.
 *
 * - {@see self::Pass}: at least two browsers agreed and our render
 *   matches them within budget. The test is green.
 * - {@see self::Fail}: at least two browsers agreed and our render
 *   diverges from them above budget. This is a real bug for us.
 * - {@see self::SkipDisagree}: the browsers don't agree among
 *   themselves. We can't judge ours fairly — we're either right or
 *   wrong relative to a single engine, which doesn't tell us anything.
 *   The test is skipped, not failed.
 * - {@see self::InsufficientEngines}: fewer than two browser renders
 *   supplied (e.g. WebKit missing on a Linux runner and only Chromium
 *   was rendered). No verdict possible.
 */
enum ConsensusVerdict: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case SkipDisagree = 'skip_disagree';
    case InsufficientEngines = 'insufficient_engines';
}
