<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * Shell wrapper around `scripts/cross-browser/render.mjs` (Chromium and
 * WebKit) and `scripts/cross-browser/render-docker.sh` (Firefox via the
 * Docker image) for the cross-browser PDF oracle.
 *
 * The oracle is a slow, side-effecting operation — each render boots a
 * browser engine and emits a real PDF. We cache aggressively: keyed on
 * the SHA-256 of the test bytes plus engine identifier plus a "cache
 * generation" string (so bumping Playwright / WebKit / Firefox versions
 * invalidates everything in one move).
 *
 * On a cache hit we return the cached PDF immediately. On a miss we
 * shell out to the right CLI, store the PDF, and return its path.
 *
 * Engine availability is checked lazily — if `node` isn't on `PATH`
 * (Chromium) or `docker` isn't running (Firefox) or the Swift binary
 * isn't built (WebKit), the engine is reported as unavailable and the
 * runner skips it instead of failing the whole test.
 */
final class BrowserOracle
{
    public function __construct(
        /**
         * Absolute path to the repo's `scripts/cross-browser/` directory.
         * Defaults to a relative path resolved from this file's location.
         */
        private readonly string $scriptDir = __DIR__ . '/../../../scripts/cross-browser',
        /**
         * Cache directory for rendered PDFs. Gitignored. Lives under
         * `var/wpt/browser-cache/` by default; per-engine subdirs keep
         * the listing legible.
         */
        private readonly string $cacheDir = __DIR__ . '/../../../var/wpt/browser-cache',
        /**
         * Single-string knob to invalidate every cache entry at once.
         * Bumped when any browser version moves. Combined with the
         * test-bytes hash to form the cache key.
         */
        private readonly string $cacheGeneration = 'pw-1.49.1-ff-latest-wk-26.0',
        private readonly string $nodeBinary = 'node',
        private readonly string $dockerBinary = 'docker',
    ) {}

    /**
     * Render `$testPath` through `$engine` and return the path to the
     * resulting PDF. The path is owned by the cache — the caller MUST
     * NOT unlink it. Returns null when the engine isn't available on
     * this host; raises on a hard render failure (engine present but
     * the PDF generation went wrong).
     */
    public function render(string $engine, string $testPath): ?string
    {
        if (!is_file($testPath)) {
            throw new \RuntimeException("test fixture not found: $testPath");
        }
        if (!$this->isAvailable($engine)) {
            return null;
        }
        $cached = $this->cachedPath($engine, $testPath);
        if (is_file($cached)) {
            return $cached;
        }
        $this->ensureCacheDir($engine);
        $tmp = $cached . '.tmp.' . bin2hex(random_bytes(4));
        try {
            $this->dispatch($engine, $testPath, $tmp);
            if (!is_file($tmp) || filesize($tmp) === 0) {
                throw new \RuntimeException("$engine produced empty PDF for $testPath");
            }
            // Atomic publish: rename is atomic on POSIX so concurrent
            // callers don't race on a half-written cache file.
            rename($tmp, $cached);
            return $cached;
        } catch (\Throwable $err) {
            @unlink($tmp);
            throw $err;
        }
    }

    /**
     * Available-engine probe. Each engine has a different signal:
     *
     *  - `chromium` — node + render.mjs + Playwright's bundled Chromium.
     *    We check node exists; the Playwright install is a hard
     *    runtime error if it's missing, which is sensible.
     *  - `firefox`  — docker daemon must be reachable. The first call
     *    builds the image (~3 min); subsequent ones reuse it.
     *  - `webkit`   — `webkit-render` binary at the configured path
     *    (defaults to `/usr/local/bin/webkit-render`; override with
     *    the `WEBKIT_CLI` env in render.mjs).
     */
    public function isAvailable(string $engine): bool
    {
        return match ($engine) {
            'chromium' => $this->binaryAvailable($this->nodeBinary),
            'firefox' => $this->binaryAvailable($this->dockerBinary)
                && $this->dockerDaemonRunning(),
            'webkit' => is_file(
                getenv('WEBKIT_CLI') ?: '/usr/local/bin/webkit-render',
            ),
            default => false,
        };
    }

    /**
     * Return the cache key + final path for `(engine, testPath)`. Public
     * so callers (e.g. CI) can pre-warm the cache.
     */
    public function cachedPath(string $engine, string $testPath): string
    {
        $key = hash('sha256', (string) file_get_contents($testPath))
            . '-' . $this->cacheGeneration
            . '-' . $engine;
        return $this->cacheDir . '/' . $engine . '/' . substr($key, 0, 64) . '.pdf';
    }

    /**
     * For diagnostics: clear every cached PDF for one engine. CI uses
     * this when bumping browser versions.
     */
    public function clearCache(string $engine): void
    {
        $dir = $this->cacheDir . '/' . $engine;
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.pdf') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function dispatch(string $engine, string $testPath, string $outPath): void
    {
        switch ($engine) {
            case 'chromium':
                $cmd = sprintf(
                    '%s %s chromium %s --output=%s 2>&1',
                    escapeshellcmd($this->nodeBinary),
                    escapeshellarg($this->scriptDir . '/render.mjs'),
                    escapeshellarg($testPath),
                    escapeshellarg($outPath),
                );
                break;
            case 'webkit':
                $cmd = sprintf(
                    '%s %s webkit %s --output=%s 2>&1',
                    escapeshellcmd($this->nodeBinary),
                    escapeshellarg($this->scriptDir . '/render.mjs'),
                    escapeshellarg($testPath),
                    escapeshellarg($outPath),
                );
                break;
            case 'firefox':
                $cmd = sprintf(
                    '%s %s %s %s 2>&1',
                    escapeshellcmd($this->scriptDir . '/render-docker.sh'),
                    'firefox',
                    escapeshellarg($testPath),
                    escapeshellarg($outPath),
                );
                break;
            default:
                throw new \RuntimeException("unknown engine: $engine");
        }
        exec($cmd, $output, $status);
        if ($status !== 0) {
            $err = implode("\n", $output);
            throw new \RuntimeException("$engine render failed (exit $status): $err");
        }
    }

    private function binaryAvailable(string $binary): bool
    {
        $cmd = sprintf('command -v %s >/dev/null 2>&1', escapeshellcmd($binary));
        exec($cmd, $_, $status);
        return $status === 0;
    }

    private function dockerDaemonRunning(): bool
    {
        $cmd = sprintf('%s info >/dev/null 2>&1', escapeshellcmd($this->dockerBinary));
        exec($cmd, $_, $status);
        return $status === 0;
    }

    private function ensureCacheDir(string $engine): void
    {
        $dir = $this->cacheDir . '/' . $engine;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, recursive: true);
        }
    }
}
