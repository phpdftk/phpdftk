<?php

declare(strict_types=1);

namespace Phpdftk\ImageMetadata;

use Phpdftk\Filesystem\LocalFilesystem;

/**
 * Extract intrinsic-size metadata from an SVG file's root `<svg>`
 * element without parsing the full document tree.
 *
 * Per CSS Images 3 §3 / SVG 2 §6.1, an `<svg>` element's intrinsic
 * dimensions come from a small set of root-element attributes:
 *
 *   - `width="..."` and `height="..."`: explicit intrinsic dimensions
 *     when the value is an absolute CSS length (or unitless = px).
 *     Percentages (`"50%"`) are not intrinsic and are reported as
 *     "no intrinsic value on that axis".
 *   - `viewBox="min-x min-y width height"`: defines an intrinsic
 *     aspect ratio = width / height, even when no `width`/`height`
 *     attributes are present.
 *   - When `width` and `viewBox` are both present (but `height` is
 *     absent), the height is derived as `width / ratio`. Vice versa
 *     for height-only + viewBox.
 *
 * What this parser does NOT do:
 *
 *   - Parse `<style>` blocks for `svg { width: ... }` (cascade
 *     dimensions are not "intrinsic" per CSS Images 3 §3.1).
 *   - Resolve `em` / `rem` / `vw` / `vh` units. Those depend on
 *     the embedding context; the consumer layout engine has the
 *     containing block to resolve them.
 *   - Validate the rest of the SVG content.
 *
 * The returned {@see ImageInfo} carries `format: 'svg'`. The `width`
 * and `height` fields hold the resolved intrinsic pixel dimensions
 * when both axes are known (either directly via attributes or
 * computed from one attribute + the viewBox ratio). When neither
 * intrinsic dimension can be resolved (e.g. `viewBox` only), both
 * are zero and {@see ImageInfo::$intrinsicRatio} carries the aspect
 * ratio so the layout can apply the CSS Images 3 §3.3 default
 * object size fallback.
 */
final class SvgParser
{
    public static function parseFile(string $path): ImageInfo
    {
        // Read enough bytes to capture a typical `<svg ...>` opening
        // tag. SVG roots are usually within the first few KB even
        // for complex documents; 32 KB is a generous ceiling.
        $data = LocalFilesystem::readPrefix($path, 32 * 1024, 'svg image');
        return self::parse($data);
    }

    public static function parse(string $data): ImageInfo
    {
        $rootAttrs = self::extractRootAttributes($data);
        if ($rootAttrs === null) {
            throw new \RuntimeException('Not an SVG document');
        }

        $width = self::parseLengthAttribute($rootAttrs['width'] ?? null);
        $height = self::parseLengthAttribute($rootAttrs['height'] ?? null);
        $viewBoxDims = self::parseViewBoxDimensions($rootAttrs['viewbox'] ?? null);
        $ratio = $viewBoxDims !== null ? $viewBoxDims[0] / $viewBoxDims[1] : null;

        // Apply the intrinsic-aspect-ratio rule: when one axis is
        // known and the viewBox supplies a ratio, derive the other.
        if ($width !== null && $height === null && $ratio !== null && $ratio > 0.0) {
            $height = $width / $ratio;
        } elseif ($height !== null && $width === null && $ratio !== null && $ratio > 0.0) {
            $width = $height * $ratio;
        }

        // Last-resort fallback: when no explicit width/height is given
        // but a viewBox is present, treat the viewBox dimensions as the
        // intrinsic pixel size. This is not strictly CSS Images 3
        // §5.2 — viewBox alone gives only a ratio — but it matches the
        // sizing the painter uses (Painter::intrinsicSvgSize) so the
        // BoxGenerator-sized layout box agrees with the painted SVG.
        if ($width === null && $height === null && $viewBoxDims !== null) {
            $width = $viewBoxDims[0];
            $height = $viewBoxDims[1];
        }

        // Compute the intrinsic ratio from explicit width/height when
        // viewBox didn't supply one. Keeps the ratio field populated
        // for the common explicit-dimensions case.
        if ($ratio === null && $width !== null && $height !== null && $height > 0.0) {
            $ratio = $width / $height;
        }

        return new ImageInfo(
            width: $width !== null ? (int) round($width) : 0,
            height: $height !== null ? (int) round($height) : 0,
            colorSpace: 'DeviceRGB',
            bitsPerComponent: 8,
            format: 'svg',
            intrinsicRatio: $ratio,
        );
    }

    /**
     * Pull the attributes off the root `<svg ...>` opening tag.
     *
     * Returns an associative array keyed by lowercased attribute
     * name, or null when the input doesn't look like an SVG (no
     * `<svg` token within the prefix).
     *
     * @return array<string, string>|null
     */
    private static function extractRootAttributes(string $data): ?array
    {
        // Skip an optional XML prolog and any leading processing
        // instructions / comments / doctype. The first `<svg` (case
        // insensitive, followed by whitespace, `>`, or `/`) starts
        // the root element.
        if (preg_match('/<svg\b([^>]*)>/i', $data, $m) !== 1) {
            return null;
        }
        $attrSegment = $m[1];

        $attrs = [];
        $pattern = '/([A-Za-z_:][\w:.\-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/';
        if (preg_match_all($pattern, $attrSegment, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));
                $attrs[$name] = $value;
            }
        }
        return $attrs;
    }

    /**
     * Parse an SVG length attribute (`width="100"`, `height="50px"`)
     * into pixels.
     *
     * Returns null when the value is missing, empty, a percentage
     * (intrinsic dims don't carry percentages), or in a unit this
     * parser doesn't resolve without a containing block (`em`,
     * `rem`, `vw`, `vh`). Absolute units we do resolve: unitless
     * (= px), `px`, `pt`, `pc`, `in`, `cm`, `mm`, `Q`.
     */
    private static function parseLengthAttribute(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || str_ends_with($trimmed, '%')) {
            return null;
        }
        if (preg_match('/^([+-]?\d+(?:\.\d+)?|[+-]?\.\d+)([a-zA-Z]*)$/', $trimmed, $m) !== 1) {
            return null;
        }
        $value = (float) $m[1];
        $unit = strtolower($m[2]);
        return match ($unit) {
            '', 'px' => $value,
            'pt' => $value * (96.0 / 72.0),
            'pc' => $value * (96.0 / 6.0),
            'in' => $value * 96.0,
            'cm' => $value * (96.0 / 2.54),
            'mm' => $value * (96.0 / 25.4),
            'q' => $value * (96.0 / 101.6),
            default => null,
        };
    }

    /**
     * Extract the (width, height) pair from a `viewBox` attribute
     * value (`"min-x min-y width height"`).
     *
     * Returns null when the attribute is missing, malformed, or
     * either dimension is non-positive.
     *
     * @return array{float, float}|null
     */
    private static function parseViewBoxDimensions(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $parts = preg_split('/[\s,]+/', trim($raw));
        if ($parts === false || count($parts) !== 4) {
            return null;
        }
        $w = (float) $parts[2];
        $h = (float) $parts[3];
        if ($w <= 0.0 || $h <= 0.0) {
            return null;
        }
        return [$w, $h];
    }
}
