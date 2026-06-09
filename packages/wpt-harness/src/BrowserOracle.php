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
    /**
     * Engine → port offset for the daemon-base URL. The full URL is
     * `${daemonBase}${offset}/render`, so `--daemon-base=http://127.0.0.1:910`
     * routes to 9101 / 9102 / 9103. Keep in sync with the port
     * declarations in compose.yaml.
     */
    private const ENGINE_PORTS = [
        'chromium' => 1,
        'firefox' => 2,
        'webkit' => 3,
    ];

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
        /**
         * Base URL of the engine daemon cluster. When set, every render
         * goes through HTTP POST to `${daemonBase}${portOffset}/render`
         * instead of forking `node render.mjs`. Null = legacy one-shot
         * fork mode (used by dev hosts that don't want to bring up the
         * compose stack).
         *
         * Example: `--daemon-base=http://127.0.0.1:910` → chromium
         * runs on :9101, firefox on :9102, webkit on :9103.
         */
        private readonly ?string $daemonBase = null,
        /**
         * When daemon-mode is on, fixture paths arrive as absolute
         * host paths (e.g. `/Users/x/repo/vendor-data/wpt/foo.html`)
         * but the daemon needs the in-container view (`/wpt/foo.html`).
         * We rewrite the prefix via `hostWptRoot` → `daemonWptRoot`.
         * Auto-resolved from `vendor-data/wpt` when null.
         */
        private readonly ?string $hostWptRoot = null,
        private readonly string $daemonWptRoot = '/wpt',
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
     *  - `firefox`  — on macOS hosts, the system Firefox.app is used
     *    directly (`--screenshot` path in render.mjs because
     *    `--print-to-pdf` hangs the SWGL compositor on arm64). On
     *    everything else, the docker daemon must be reachable so
     *    render-docker.sh can drop into the Linux container.
     *  - `webkit`   — `webkit-render` binary at the configured path
     *    (defaults to `/usr/local/bin/webkit-render`; override with
     *    the `WEBKIT_CLI` env in render.mjs).
     */
    public function isAvailable(string $engine): bool
    {
        if ($this->daemonBase !== null) {
            return $this->daemonReady($engine);
        }
        return match ($engine) {
            'chromium' => $this->binaryAvailable($this->nodeBinary),
            'firefox' => $this->firefoxAvailable(),
            'webkit' => is_file(
                getenv('WEBKIT_CLI') ?: '/usr/local/bin/webkit-render',
            ),
            default => false,
        };
    }

    /**
     * Probe `${daemonBase}${port}/status` and treat a 200 with
     * `ready: true` as available. Short timeout — if the daemon isn't
     * up we want the sweep to skip the engine, not stall.
     */
    private function daemonReady(string $engine): bool
    {
        $url = $this->daemonUrlFor($engine, '/status');
        if ($url === null) {
            return false;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 2000,
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_FAILONERROR => false,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !is_string($body)) {
            return false;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) && ($decoded['ready'] ?? false) === true;
    }

    private function daemonUrlFor(string $engine, string $path): ?string
    {
        if ($this->daemonBase === null) {
            return null;
        }
        $offset = self::ENGINE_PORTS[$engine] ?? null;
        if ($offset === null) {
            return null;
        }
        return rtrim($this->daemonBase, '/') . $offset . $path;
    }

    private function firefoxAvailable(): bool
    {
        if ($this->macFirefoxPath() !== null) {
            return $this->binaryAvailable($this->nodeBinary);
        }
        return $this->binaryAvailable($this->dockerBinary)
            && $this->dockerDaemonRunning();
    }

    private function macFirefoxPath(): ?string
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return null;
        }
        $envBin = getenv('FIREFOX_CLI');
        if (is_string($envBin) && $envBin !== '' && is_file($envBin)) {
            return $envBin;
        }
        $app = '/Applications/Firefox.app/Contents/MacOS/firefox';
        return is_file($app) ? $app : null;
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
        if ($this->daemonBase !== null) {
            $this->dispatchDaemon($engine, $testPath, $outPath);
            return;
        }
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
                $macFf = $this->macFirefoxPath();
                if ($macFf !== null) {
                    // macOS hosts run Firefox natively through render.mjs;
                    // render-docker.sh is the Linux fallback.
                    $env = 'FIREFOX_CLI=' . escapeshellarg($macFf) . ' ';
                    $cmd = $env . sprintf(
                        '%s %s firefox %s --output=%s 2>&1',
                        escapeshellcmd($this->nodeBinary),
                        escapeshellarg($this->scriptDir . '/render.mjs'),
                        escapeshellarg($testPath),
                        escapeshellarg($outPath),
                    );
                    break;
                }
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

    /**
     * Post the fixture to the engine daemon and write the returned
     * PDF bytes to `$outPath`. The daemon expects fixture paths under
     * its own WPT root (mounted as `/wpt/` in the canonical compose
     * setup); we translate the host path before sending.
     *
     * The daemon also writes its own atomic cache copy keyed on
     * `cache_key`. The calling render() then renames `$outPath` into
     * the same final path — both writes land on the same file with
     * identical bytes, which is harmless duplication that we accept
     * to keep both sides of the cache symmetric.
     */
    private function dispatchDaemon(string $engine, string $testPath, string $outPath): void
    {
        $url = $this->daemonUrlFor($engine, '/render');
        if ($url === null) {
            throw new \RuntimeException("daemon URL unavailable for engine $engine");
        }
        $payload = json_encode([
            'fixture' => $this->translateFixturePath($testPath),
            'cache_key' => $this->cacheKey($engine, $testPath),
            'viewport' => ['width' => 816, 'height' => 1056],
            'timeout_ms' => 60000,
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new \RuntimeException("failed to encode daemon request for $testPath");
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['content-type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 90, // > daemon's 60s render budget, < forever
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException("$engine daemon request failed: $err");
        }
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException(
                "$engine daemon returned non-JSON (HTTP $code): " . substr((string) $body, 0, 200),
            );
        }
        if ($code !== 200) {
            $msg = $decoded['error'] ?? 'unknown daemon error';
            throw new \RuntimeException("$engine daemon HTTP $code: $msg");
        }
        $b64 = $decoded['pdf_bytes_base64'] ?? null;
        if (!is_string($b64) || $b64 === '') {
            throw new \RuntimeException("$engine daemon returned no PDF bytes");
        }
        $bytes = base64_decode($b64, strict: true);
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException("$engine daemon returned malformed base64");
        }
        file_put_contents($outPath, $bytes);
    }

    /**
     * Translate a host fixture path into the daemon-visible path. The
     * daemon sees the WPT corpus at `daemonWptRoot` (default `/wpt`);
     * the host sees it wherever `hostWptRoot` resolves to (default
     * `vendor-data/wpt` relative to the repo).
     */
    private function translateFixturePath(string $hostPath): string
    {
        $hostRoot = $this->hostWptRoot ?? realpath(__DIR__ . '/../../../vendor-data/wpt');
        if (!is_string($hostRoot) || $hostRoot === '') {
            return $hostPath;
        }
        $real = realpath($hostPath) ?: $hostPath;
        if (str_starts_with($real, $hostRoot . '/')) {
            return $this->daemonWptRoot . substr($real, strlen($hostRoot));
        }
        return $hostPath;
    }

    /**
     * First-64-hex-chars of the cache key (matches the substring
     * pulled in `cachedPath()`). Daemons receive this as `cache_key`
     * in the request body and use it directly as the cache filename
     * stem.
     */
    private function cacheKey(string $engine, string $testPath): string
    {
        $key = hash('sha256', (string) file_get_contents($testPath))
            . '-' . $this->cacheGeneration
            . '-' . $engine;
        return substr($key, 0, 64);
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
