<?php

declare(strict_types=1);

namespace Phpdftk\ResourceLoader;

/**
 * Detects the MIME type of a binary blob by looking at the first
 * few bytes — the WHATWG mime-sniff spec equivalent for the formats
 * phpdftk's pipeline actually consumes. The HTTP `Content-Type`
 * response header is treated as a hint, not authoritative; servers
 * routinely mislabel images.
 *
 * v1 (4F.1) covers:
 *
 *   - image/png       89 50 4E 47 0D 0A 1A 0A   (8 bytes)
 *   - image/jpeg      FF D8 FF                  (3 bytes)
 *   - image/gif       'GIF87a' | 'GIF89a'       (6 bytes)
 *   - image/webp      'RIFF' .... 'WEBP'        (12 bytes)
 *   - image/tiff      'II' 2A 00 | 'MM' 00 2A   (4 bytes)
 *   - image/bmp       'BM'                      (2 bytes)
 *   - image/svg+xml   XML/SVG textual sniff
 *
 * Returns `application/octet-stream` when no signature matches —
 * safe default that downstream image-format-aware code can reject.
 */
final class MimeSniffer
{
    /**
     * The default WHATWG-compatible result when nothing matches. The
     * caller decides whether to accept opaque bytes (`<image>` href
     * pointing at an unknown format → SVG 2 §12.6 "no image
     * available" outcome) or surface an error.
     */
    public const FALLBACK = 'application/octet-stream';

    /**
     * Inspect the first few bytes of `$bytes` and return the
     * sniffed MIME type. The caller should pass at least 16 bytes
     * for robust detection; shorter payloads fall through to the
     * fallback type.
     */
    public function sniff(string $bytes): string
    {
        $length = strlen($bytes);
        if ($length < 2) {
            return self::FALLBACK;
        }

        // Order matters — PNG / JPEG / GIF / WebP / TIFF / BMP all
        // have unambiguous magic bytes; SVG is a textual fall-
        // through and checked last.

        if ($length >= 8 && substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'image/png';
        }

        if ($length >= 3 && substr($bytes, 0, 3) === "\xff\xd8\xff") {
            return 'image/jpeg';
        }

        if ($length >= 6) {
            $first6 = substr($bytes, 0, 6);
            if ($first6 === 'GIF87a' || $first6 === 'GIF89a') {
                return 'image/gif';
            }
        }

        if (
            $length >= 12
            && substr($bytes, 0, 4) === 'RIFF'
            && substr($bytes, 8, 4) === 'WEBP'
        ) {
            return 'image/webp';
        }

        if ($length >= 4) {
            $first4 = substr($bytes, 0, 4);
            if ($first4 === "II*\x00" || $first4 === "MM\x00*") {
                return 'image/tiff';
            }
        }

        if (substr($bytes, 0, 2) === 'BM') {
            return 'image/bmp';
        }

        if (self::looksLikeSvg($bytes)) {
            return 'image/svg+xml';
        }

        return self::FALLBACK;
    }

    /**
     * Textual SVG detection — skip any UTF-8 / UTF-16 BOM and
     * leading whitespace, then look for `<?xml` or `<svg` near the
     * start of the document. Doesn't validate that the document is
     * well-formed XML; that's the parser's job.
     */
    private static function looksLikeSvg(string $bytes): bool
    {
        // Strip BOMs if present.
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        } elseif (str_starts_with($bytes, "\xFF\xFE") || str_starts_with($bytes, "\xFE\xFF")) {
            // UTF-16 BOM. Quick path: decode to ASCII roughly by
            // dropping every other byte. Good enough for the magic-
            // bytes check; full Unicode parsing is the SVG parser's
            // job.
            $bytes = preg_replace('/\x00/', '', substr($bytes, 2)) ?? '';
        }

        // Strip leading whitespace.
        $trimmed = ltrim($bytes);
        if ($trimmed === '') {
            return false;
        }

        // Look for the XML declaration or the SVG root in the first
        // ~256 bytes. Real SVGs may have a DOCTYPE between the XML
        // declaration and the root; we scan ahead a bit.
        $window = substr($trimmed, 0, 256);
        if (str_starts_with($window, '<?xml')) {
            // Has XML declaration — look for `<svg` somewhere in
            // the window (likely the root or just after a doctype).
            // Match `<svg ` (with attribute) or `<svg>` (no
            // attributes) or `<svg xmlns…`.
            return preg_match('/<svg(\s|>)/i', $window) === 1;
        }
        if (str_starts_with($window, '<svg')) {
            // Bare SVG without XML declaration.
            $afterSvg = substr($window, 4, 1);
            return $afterSvg === '' || $afterSvg === ' ' || $afterSvg === '>' || $afterSvg === "\t" || $afterSvg === "\n" || $afterSvg === "\r";
        }
        return false;
    }
}
