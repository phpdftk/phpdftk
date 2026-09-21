<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * The relation a WPT reftest declares between itself and one of its
 * references.
 *
 * WPT's manifest generator reads exactly two `<link>` relations and
 * maps them onto the two comparison operators
 * (`tools/manifest/sourcefile.py::references`):
 *
 *     rel_map = {"match": "==", "mismatch": "!="}
 *
 * The case values here are those operators, so a serialised relation
 * reads the same way WPT's own manifest writes it.
 *
 * A `mismatch` reference is not a weaker assertion than a `match`
 * one, it is the opposite assertion: the test must render
 * *differently* from the reference. That makes mismatch fixtures
 * uniquely valuable to this harness, which renders both sides with
 * its own engine — an unimplemented feature blanks the test and the
 * reference alike, and two blank pages are identical, so a mismatch
 * fixture correctly FAILS where a match fixture would falsely pass.
 */
enum ReferenceRelation: string
{
    case Match = '==';
    case Mismatch = '!=';

    /**
     * Turn the frame comparison into a verdict for this relation.
     *
     * `$framesMatched` is "the two screenshots are equal within
     * whatever tolerance applies" — wptrunner's `equal` local in
     * `executors/base.py::check_pass`, which then returns
     * `False if relation == "==" else True` on inequality. The
     * tolerance question is settled before this is called; all that
     * happens here is the sign.
     */
    public function satisfiedBy(bool $framesMatched): bool
    {
        return match ($this) {
            self::Match => $framesMatched,
            self::Mismatch => !$framesMatched,
        };
    }
}
