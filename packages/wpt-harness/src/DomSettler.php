<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

use Phpdftk\Filesystem\LocalFilesystem;

/**
 * Settles a WPT `class="reftest-wait"` fixture's JavaScript before
 * the static PHP renderer sees it.
 *
 * ## What and why
 *
 * Many WPT reftests use the `reftest-wait` convention to delay the
 * screenshot until a piece of test JavaScript has finished running.
 * Typical work: load `@font-face` WOFFs via the FontFace API, measure
 * the resulting layout via `getBoundingClientRect`, shift an element
 * into a known pixel position via inline `style="left: …"`, then
 * remove the `reftest-wait` class to signal "ready".
 *
 * Our PHP renderer is a static-document pipeline - there is no
 * notion of time, no event loop, no `requestAnimationFrame`. Without
 * JS, the test's setup work never runs, and the rendered output
 * diverges from what the reference expected.
 *
 * The DomSettler bridges that gap by:
 *
 *   1. Detecting `class="reftest-wait"` on the test fixture.
 *   2. Shelling out to {@see scripts/cross-browser/settle-dom.mjs},
 *      which launches headless Chromium (via the same Playwright
 *      install the cross-browser oracle uses), loads the test, and
 *      waits for the class to clear.
 *   3. Capturing the post-JS HTML via Playwright's `page.content()`.
 *   4. Caching the settled HTML on disk, keyed by `sha256(testBytes
 *      + playwright version)`, so identical fixtures only pay the
 *      browser-launch cost once.
 *   5. Returning the settled HTML for the renderer to consume.
 *
 * Fixtures without `class="reftest-wait"` skip settling entirely -
 * the harness reads them as-is, exactly like before this class
 * existed.
 *
 * ## CSS animations + transitions are paused at t=0
 *
 * The settler script injects a stylesheet that zeroes
 * `animation-duration` / `animation-delay` / `transition-duration`
 * / `transition-delay` on every element BEFORE the test's own
 * scripts run. This keeps the captured DOM aligned with our static-
 * renderer's "no time" semantics:
 *
 *   - CSS animations evaluate at their `0%` keyframe (initial
 *     value) and stay there - the settler never sees a mid- or
 *     end-state.
 *   - CSS transitions apply target values instantly, never tweens.
 *
 * Tests that depend on observing an *animated* end-state (rare in
 * WPT reftests - most use animation only for visibility / pacing)
 * will diverge from the unmodified browser behaviour. This is the
 * intended trade-off: the settler models the same "t=0" semantics
 * the renderer applies.
 *
 * ## When the settler is unavailable
 *
 * The settler depends on Playwright + Chromium being installed
 * (the same prerequisites as the cross-browser oracle's
 * `scripts/cross-browser/render.mjs`). When unavailable - missing
 * `node`, missing Playwright, missing browser - the
 * {@see maybeSettle()} method gracefully returns the original
 * fixture bytes without trying to settle. The harness still
 * renders the test, just from its pre-JS source. Authors who care
 * about reftest-wait coverage configure Playwright per
 * `scripts/bootstrap-cross-browser.sh`.
 */
final class DomSettler
{
    public function __construct(
        /**
         * Path to {@see scripts/cross-browser/settle-dom.mjs}.
         * Configurable so tests can stub the script and the
         * harness can locate it relative to the repo root.
         */
        private readonly string $scriptPath,
        /**
         * Cache directory for settled-HTML payloads. Keyed by
         * `sha256(testBytes + playwrightVersion)`. Created lazily
         * when the first settle attempt succeeds.
         */
        private readonly string $cacheDir,
        /**
         * Root directory passed to the settler script as
         * `--corpus-root=`. Mirrors how the PHP ResourceLoader
         * resolves `/`-prefixed URLs against the corpus root.
         */
        private readonly string $corpusRoot,
        /**
         * Node binary to invoke. Defaults to `node` on PATH.
         */
        private readonly string $nodeBinary = 'node',
        /**
         * Timeout for the settler to wait for the `reftest-wait`
         * class to clear, in milliseconds.
         */
        private readonly int $timeoutMs = 5000,
    ) {}

    /**
     * Detect `class="reftest-wait"` on the test fixture's `<html>`
     * element. Conservative regex match (the class attribute on
     * the first `<html>` element) - false positives on tests that
     * just *mention* the class string elsewhere are accepted; the
     * worst case is one wasted settle round.
     */
    public function needsSettling(string $fixtureBytes): bool
    {
        // Match `<html ... class="...reftest-wait...">` covering
        // both single- and double-quoted attribute values plus the
        // multi-class case.
        return preg_match(
            '/<html\b[^>]*\bclass\s*=\s*["\']\s*(?:[^"\']+\s+)?reftest-wait\b/i',
            $fixtureBytes,
        ) === 1;
    }

    /**
     * Returns the settled HTML for `$fixturePath` when settling is
     * applicable and successful, or null when:
     *
     *   - The fixture doesn't carry `class="reftest-wait"`.
     *   - The settler script isn't available or fails to run.
     *   - The browser launch / navigation errors out.
     *
     * Callers should fall back to reading the original fixture
     * bytes on null.
     */
    public function maybeSettle(string $fixturePath, string $fixtureBytes): ?string
    {
        if (!$this->needsSettling($fixtureBytes)) {
            return null;
        }
        $cacheKey = $this->cacheKeyFor($fixtureBytes);
        $cachePath = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.html';
        if (is_file($cachePath)) {
            return LocalFilesystem::readFile($cachePath, 'settled-DOM cache');
        }
        $settled = $this->settle($fixturePath);
        if ($settled === null) {
            return null;
        }
        LocalFilesystem::writeFile($cachePath, $settled, createDirectories: true);
        return $settled;
    }

    /**
     * Shell out to the settler script and return the captured HTML
     * on success, or null on any failure.
     */
    private function settle(string $fixturePath): ?string
    {
        if (!is_file($this->scriptPath)) {
            return null;
        }
        $command = [
            $this->nodeBinary,
            $this->scriptPath,
            $fixturePath,
            '--corpus-root=' . $this->corpusRoot,
            '--timeout=' . $this->timeoutMs,
        ];
        $cmdLine = implode(' ', array_map('escapeshellarg', $command));
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmdLine, $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || $stdout === false || $stdout === '') {
            return null;
        }
        return $stdout;
    }

    /**
     * Build the cache key for `$fixtureBytes`. The settled output
     * depends on the fixture content plus the Playwright /
     * Chromium versions in the environment - a cache miss when
     * those tools update is the intended behaviour. We can't read
     * the Playwright version from PHP cheaply, so the script
     * itself bakes its version into the output via the data-source
     * marker; we just hash the source bytes here and rely on a
     * manual cache flush after a Playwright bump.
     */
    private function cacheKeyFor(string $fixtureBytes): string
    {
        return hash('sha256', $fixtureBytes);
    }
}
