<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * WPT test classifier — maps each test identifier to its scope status
 * per `docs/spec/out-of-scope.md` and the Phase 4 substrate readiness
 * matrix.
 *
 * The manifest is the authoritative answer to: "does this test count
 * toward the in-scope pass rate?" Three bucket destinations:
 *
 *  - `OutOfScope`        — surface listed in the permanent out-of-scope
 *                          ledger. The test directory or test ID
 *                          matches a rule.
 *  - `PendingSubstrate`  — surface is in-scope but the substrate
 *                          dependency (4C raster, 4D shaping, etc.)
 *                          hasn't shipped.
 *  - in-scope            — test runs through the renderer and gets
 *                          scored; result is `Pass` or `Fail`.
 *
 * Phase 4A.4 — populate the rule tables from the inventory + the
 * out-of-scope ledger. Rules are version-controlled at
 * `packages/wpt-harness/manifest/` (one YAML per top-level WPT
 * directory).
 */
final class Manifest
{
    /**
     * @param array<string, string> $outOfScopeRules         Glob → reason.
     *                                                       Matched against
     *                                                       the test ID.
     * @param array<string, string> $pendingSubstrateRules   Glob → substrate
     *                                                       sub-phase ID
     *                                                       (e.g. `4C`).
     */
    public function __construct(
        private readonly array $outOfScopeRules = [],
        private readonly array $pendingSubstrateRules = [],
    ) {}

    /**
     * Classify a single test by ID. Returns the bucket plus a reason
     * string for non-in-scope outcomes.
     *
     * Phase 4A.4 implements the glob matching.
     *
     * @return array{status: TestStatus, reason: string|null}
     */
    public function classify(string $testId): array
    {
        // Phase 4A.4 — match $testId against $outOfScopeRules first,
        // then $pendingSubstrateRules. First-match wins so authors
        // can override broad out-of-scope rules with narrower
        // in-scope exceptions.
        unset($testId);
        return ['status' => TestStatus::Skipped, 'reason' => '4A.4 not yet implemented'];
    }

    /**
     * Load the manifest from the rule directory under
     * `packages/wpt-harness/manifest/`. One YAML file per top-level
     * WPT directory (`css.yaml`, `html.yaml`, `svg.yaml`, …) plus a
     * `_global.yaml` for cross-cutting rules.
     *
     * Phase 4A.4 implements the loader.
     */
    public static function loadFromDirectory(string $manifestDir): self
    {
        unset($manifestDir);
        return new self();
    }

    /**
     * Read-only access to the rule tables. Used by harness tests +
     * the `wpt classify` CLI to introspect the loaded manifest.
     *
     * @return array<string, string>
     */
    public function outOfScopeRules(): array
    {
        return $this->outOfScopeRules;
    }

    /**
     * @return array<string, string>
     */
    public function pendingSubstrateRules(): array
    {
        return $this->pendingSubstrateRules;
    }
}
