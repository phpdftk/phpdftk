<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

use Phpdftk\Filesystem\LocalFilesystem;

/**
 * WPT test classifier — maps each test identifier to its scope status
 * per `docs/spec/out-of-scope.md` and the Phase 4 substrate readiness
 * matrix.
 *
 * The manifest is the authoritative answer to: "does this test count
 * toward the in-scope pass rate?" Possible outcomes:
 *
 *  - `OutOfScope`        — surface listed in the permanent out-of-scope
 *                          ledger. Skipped at runtime; excluded from
 *                          both numerator and denominator.
 *  - `PendingSubstrate`  — surface is in-scope but the substrate
 *                          dependency (4C raster, 4D shaping, 4E
 *                          color, 4F resource loader, 4G paged
 *                          media) hasn't shipped. Skipped at
 *                          runtime; tracked separately in the
 *                          dashboard so callers can see "N tests
 *                          blocked on 4C".
 *  - `null` (in-scope)   — no rule matched; the runner renders the
 *                          test and scores it `Pass` / `Fail` based
 *                          on the visual diff.
 *
 * Rule storage. Rules live in JSON files under
 * `packages/wpt-harness/manifest/`:
 *
 *   _global.json   Cross-cutting rules (network APIs, sensors,
 *                  workers, etc.) — loaded first.
 *   css.json       CSS-module-specific rules.
 *   html.json      HTML-spec-section-specific rules.
 *   svg.json       SVG-2-section-specific rules.
 *
 * First-match wins so narrower rules in per-spec files can override
 * broader rules in `_global.json`. Within a file, out-of-scope
 * rules win over pending-substrate when both match.
 *
 * Glob syntax. Test IDs are POSIX-style paths (`css/css-color/lab-001`).
 * Patterns use shell-glob conventions extended for cross-directory
 * matching:
 *
 *   *     matches any sequence of non-separator chars (single
 *         segment)
 *   **    matches any sequence including separators (recursive)
 *
 * All other regex metacharacters are escaped literally so authors
 * can write `at-page-*` without worrying about the `-` or `()`.
 */
final class Manifest
{
    /**
     * @param list<array{glob: string, reason: string}> $outOfScopeRules
     * @param list<array{glob: string, reason: string, phase?: string}> $pendingSubstrateRules
     */
    public function __construct(
        private readonly array $outOfScopeRules = [],
        private readonly array $pendingSubstrateRules = [],
    ) {}

    /**
     * Classify a single test by ID. Returns `null` when the test is
     * in-scope (no rule matched — the runner is expected to render
     * and score it). Returns a verdict array when a rule matched.
     *
     * @return array{status: TestStatus, reason: string, phase?: string}|null
     */
    public function classify(string $testId): ?array
    {
        foreach ($this->outOfScopeRules as $rule) {
            if (self::matches($rule['glob'], $testId)) {
                return [
                    'status' => TestStatus::OutOfScope,
                    'reason' => $rule['reason'],
                ];
            }
        }
        foreach ($this->pendingSubstrateRules as $rule) {
            if (self::matches($rule['glob'], $testId)) {
                $verdict = [
                    'status' => TestStatus::PendingSubstrate,
                    'reason' => $rule['reason'],
                ];
                if (isset($rule['phase'])) {
                    $verdict['phase'] = $rule['phase'];
                }
                return $verdict;
            }
        }
        return null;
    }

    /**
     * Load the manifest from a rule directory. `_global.json` is
     * loaded first (if present) so narrower per-spec files can
     * override broader cross-cutting rules. Within a directory, all
     * `*.json` files except those starting with `_` (other than
     * `_global.json` itself) are merged in alphabetical order.
     *
     * Each JSON file may declare `out-of-scope` and / or
     * `pending-substrate` arrays of `{glob, reason, phase?}` objects.
     * Invalid entries are silently skipped — the manifest is meant
     * to be tolerant of comments and additional metadata so authors
     * can keep notes alongside the rules.
     */
    public static function loadFromDirectory(string $manifestDir): self
    {
        $files = glob(rtrim($manifestDir, '/') . '/*.json');
        if ($files === false || $files === []) {
            return new self();
        }
        // `_global.json` first, others alphabetically. Any other
        // `_*.json` is treated as a private include and skipped so
        // authors can stash drafts under `_wip.json` without
        // affecting classification.
        usort($files, static function (string $a, string $b): int {
            $aIsGlobal = basename($a) === '_global.json';
            $bIsGlobal = basename($b) === '_global.json';
            if ($aIsGlobal !== $bIsGlobal) {
                return $aIsGlobal ? -1 : 1;
            }
            return strcmp(basename($a), basename($b));
        });

        $outOfScope = [];
        $pendingSubstrate = [];
        foreach ($files as $file) {
            $name = basename($file);
            if ($name !== '_global.json' && str_starts_with($name, '_')) {
                continue;
            }
            $data = self::loadJsonFile($file);
            if ($data === null) {
                continue;
            }
            foreach (self::normaliseRules($data['out-of-scope'] ?? null) as $rule) {
                $outOfScope[] = ['glob' => $rule['glob'], 'reason' => $rule['reason']];
            }
            foreach (self::normaliseRules($data['pending-substrate'] ?? null) as $rule) {
                $entry = ['glob' => $rule['glob'], 'reason' => $rule['reason']];
                if (isset($rule['phase'])) {
                    $entry['phase'] = $rule['phase'];
                }
                $pendingSubstrate[] = $entry;
            }
        }
        return new self($outOfScope, $pendingSubstrate);
    }

    /**
     * Read-only access to the rule tables. Used by harness tests +
     * the `wpt classify` CLI to introspect the loaded manifest.
     *
     * @return list<array{glob: string, reason: string}>
     */
    public function outOfScopeRules(): array
    {
        return $this->outOfScopeRules;
    }

    /**
     * @return list<array{glob: string, reason: string, phase?: string}>
     */
    public function pendingSubstrateRules(): array
    {
        return $this->pendingSubstrateRules;
    }

    /**
     * Match a single test ID against a glob pattern.
     *
     *  - `*`  matches any sequence of non-`/` characters
     *  - `**` matches any sequence including `/`
     *  - All other regex metacharacters are escaped literally.
     */
    public static function matches(string $glob, string $testId): bool
    {
        $regex = self::globToRegex($glob);
        return preg_match($regex, $testId) === 1;
    }

    private static function globToRegex(string $glob): string
    {
        $regex = '';
        $length = strlen($glob);
        $i = 0;
        while ($i < $length) {
            $char = $glob[$i];
            if ($char === '*') {
                if ($i + 1 < $length && $glob[$i + 1] === '*') {
                    $regex .= '.*';
                    $i += 2;
                    continue;
                }
                $regex .= '[^/]*';
                $i++;
                continue;
            }
            // Escape regex metacharacters — preg_quote handles all of
            // them. We can't use preg_quote on the whole glob because
            // it would escape `*` too.
            $regex .= preg_quote($char, '#');
            $i++;
        }
        return '#^' . $regex . '$#';
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadJsonFile(string $path): ?array
    {
        try {
            $contents = LocalFilesystem::readFile($path, 'WPT manifest file');
        } catch (\Throwable) {
            return null;
        }
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return null;
        }
        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Coerce a JSON `out-of-scope` / `pending-substrate` array into
     * the canonical rule shape. Drops malformed entries silently.
     *
     * @param mixed $raw
     * @return list<array{glob: string, reason: string, phase?: string}>
     */
    private static function normaliseRules(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $rules = [];
        foreach ($raw as $entry) {
            if (
                !is_array($entry)
                || !isset($entry['glob'], $entry['reason'])
                || !is_string($entry['glob'])
                || !is_string($entry['reason'])
            ) {
                continue;
            }
            $rule = ['glob' => $entry['glob'], 'reason' => $entry['reason']];
            if (isset($entry['phase']) && is_string($entry['phase'])) {
                $rule['phase'] = $entry['phase'];
            }
            $rules[] = $rule;
        }
        return $rules;
    }
}
