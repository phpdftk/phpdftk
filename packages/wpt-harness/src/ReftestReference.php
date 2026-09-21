<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * One resolved entry from a reftest's reference list: an on-disk
 * path plus the relation the test declared against it.
 *
 * WPT keeps the same pair — `List[Tuple[ref_url, ref_type]]` from
 * `sourcefile.py::references` — and carries it all the way into
 * `check_pass`, because a reference means nothing without knowing
 * whether the test wants to look like it or unlike it.
 */
final readonly class ReftestReference
{
    public function __construct(
        public string $path,
        public ReferenceRelation $relation,
    ) {}
}
