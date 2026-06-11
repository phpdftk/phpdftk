<?php

declare(strict_types=1);

namespace Phpdftk\Filesystem;

/**
 * Centralised entry point for fetching the bytes of every URL-shaped
 * resource an html-to-pdf / svg-to-pdf render walks into — fonts via
 * `@font-face src: url(...)`, images via `<img src>` /
 * `background-image: url(...)`, stylesheets via `<link rel="stylesheet">`
 * / `@import`. Replaces the per-call-site data-URL + baseDir + realpath
 * boilerplate that had drifted into four near-identical resolvers.
 *
 * Phase-1 supported sources:
 *   - `data:<mime>[;base64],<payload>` URLs (MIME-validated when the
 *     caller supplies an allowlist)
 *   - Relative paths joined with the configured `$baseDir`
 *   - Absolute filesystem paths resolved under the same `$baseDir`
 *
 * All security gates documented in `docs/plans/html-and-svg.md`
 * apply uniformly: `realpath` escape rejection (no `..` walks out of
 * `baseDir`), stream-wrapper rejection (`php://`, `phar://`, etc.) via
 * the underlying `LocalFilesystem::assertLocalPath`, and explicit URL
 * scheme rejection (`http://`, `https://`, `ftp://`, ...) — remote
 * fetching lands in Phase 2 behind the same surface, with SSRF gates
 * added then.
 *
 * `data:` URLs are accepted unconditionally; the caller's allowlist
 * (`$allowedMimes`) is a *MIME match* check, not a security gate —
 * binary payloads still get the same treatment as the on-disk path.
 */
final readonly class ResourceLoader
{
    /**
     * @param ?string $baseDir Root used to resolve relative URLs.
     * @param ?string $sandboxRoot Broader sandbox boundary that the
     *   resolved path must remain under. Defaults to `$baseDir` —
     *   the historical behaviour, where a relative URL can't escape
     *   the resolution root. When set wider (e.g. the entire WPT
     *   test corpus while `$baseDir` is the individual test's dir),
     *   `../sibling-dir/x.png` resolves correctly.
     */
    public function __construct(
        public ?string $baseDir = null,
        public ?string $sandboxRoot = null,
    ) {}

    /**
     * Resolve `$url` to its raw bytes. Returns null when:
     *   - the URL doesn't match a Phase-1 supported scheme;
     *   - a relative path doesn't resolve under `$baseDir`;
     *   - `$allowedMimes` is non-empty and the resource's declared MIME
     *     isn't in the list (only enforced for `data:` URLs — disk paths
     *     have no transport-level MIME);
     *   - the underlying read throws (stream-wrapper, permissions, etc.).
     *
     * @param list<string>|null $allowedMimes Lower-case MIME types. When
     *     null, any data: MIME is accepted. When set, only those MIMEs
     *     pass through.
     */
    public function load(string $url, ?array $allowedMimes = null): ?string
    {
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, 'data:')) {
            return $this->decodeDataUrl($url, $allowedMimes);
        }
        $resolved = $this->resolveLocalPath($url);
        if ($resolved === null) {
            return null;
        }
        try {
            return LocalFilesystem::readFile($resolved);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve `$url` to a real filesystem path, or null when it can't
     * be confirmed safe under `$baseDir`. Useful when the caller needs
     * the path itself rather than the bytes — e.g. `PdfWriter::addImage`
     * accepts a path, not a buffer.
     *
     * Rejects non-`file://` URL schemes, paths that escape `$baseDir`
     * via `realpath`, and stream-wrapper paths via
     * {@see LocalFilesystem::assertLocalPath}.
     */
    public function resolveLocalPath(string $url): ?string
    {
        if ($this->baseDir === null) {
            return null;
        }
        if (preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*://~', $url) === 1) {
            return null;
        }
        // URLs starting with `/` follow the "document root" convention:
        // when a sandboxRoot is configured separately from baseDir, the
        // slash anchors to the sandbox (this matches the WPT corpus
        // layout where `/fonts/math/x.woff` refers to a file under the
        // corpus root, not a real absolute filesystem path). Without a
        // distinct sandbox, fall back to treating the leading `/` as a
        // real filesystem path - the legacy single-baseDir behaviour.
        if (str_starts_with($url, '/')) {
            $candidate = $this->sandboxRoot !== null
                && $this->sandboxRoot !== $this->baseDir
                ? rtrim($this->sandboxRoot, DIRECTORY_SEPARATOR) . $url
                : $url;
        } else {
            $candidate = $this->baseDir . DIRECTORY_SEPARATOR . $url;
        }
        $resolved = realpath($candidate);
        $sandbox = realpath($this->sandboxRoot ?? $this->baseDir);
        if ($resolved === false || $sandbox === false) {
            return null;
        }
        if (!str_starts_with($resolved, $sandbox . DIRECTORY_SEPARATOR)
            && $resolved !== $sandbox
        ) {
            return null;
        }
        try {
            LocalFilesystem::assertLocalPath($resolved);
        } catch (\Throwable) {
            return null;
        }
        return $resolved;
    }

    /**
     * Decode a `data:<mime>[;base64],<payload>` URL into raw bytes.
     * Honours both base64 and URL-encoded (rfc2397) payloads. When
     * `$allowedMimes` is non-empty, the declared MIME must match
     * exactly (case-insensitive); a payload claiming `image/png`
     * passes for an `['image/png']` allowlist but not for `['image/jpeg']`.
     *
     * @param list<string>|null $allowedMimes
     */
    private function decodeDataUrl(string $url, ?array $allowedMimes): ?string
    {
        // data:[<mime>][;params][;base64],<payload>
        $commaPos = strpos($url, ',');
        if ($commaPos === false) {
            return null;
        }
        $header = substr($url, 5, $commaPos - 5); // strip leading `data:`
        $payload = substr($url, $commaPos + 1);
        $isBase64 = false;
        $mime = '';
        if ($header !== '') {
            $parts = explode(';', $header);
            $mime = strtolower(trim($parts[0]));
            for ($i = 1; $i < count($parts); $i++) {
                if (strtolower(trim($parts[$i])) === 'base64') {
                    $isBase64 = true;
                }
            }
        }
        if ($allowedMimes !== null && $allowedMimes !== []) {
            $allowed = array_map('strtolower', $allowedMimes);
            if (!in_array($mime, $allowed, true)) {
                return null;
            }
        }
        if ($isBase64) {
            $decoded = base64_decode($payload, true);
            return $decoded === false ? null : $decoded;
        }
        return urldecode($payload);
    }
}
