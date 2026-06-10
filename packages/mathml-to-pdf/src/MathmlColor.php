<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\Color\RgbColor;

/**
 * Parse a CSS color value (hex, named, or `rgb()` form) into a
 * {@see RgbColor}. Used for `mathcolor` and `mathbackground`
 * attribute values on MathML elements.
 *
 * Scope:
 *
 *   - `#rgb` (4-bit per channel, expanded to 8-bit)
 *   - `#rrggbb` (8-bit per channel)
 *   - `rgb(R, G, B)` with integer 0-255 or percent forms
 *   - A curated subset of CSS named colors (the basic 16 plus
 *     the ones MathML default styling needs, like `salmon`)
 *
 * Out of scope for v1: `hsl()`, `rgba()`/`hsla()` (no alpha in
 * PDF DeviceRGB without ExtGState), `lab()`/`lch()`, system
 * colors, `currentcolor`, `transparent`.
 */
final class MathmlColor
{
    /**
     * Parse a CSS color string. Returns null for empty, invalid,
     * or unsupported forms so the caller can fall back to its
     * default (typically: keep the current color).
     */
    public static function parse(string $value): ?RgbColor
    {
        $trimmed = strtolower(trim($value));
        if ($trimmed === '') {
            return null;
        }
        if (str_starts_with($trimmed, '#')) {
            return self::parseHex(substr($trimmed, 1));
        }
        if (str_starts_with($trimmed, 'rgb(') && str_ends_with($trimmed, ')')) {
            return self::parseRgbFunction(
                substr($trimmed, 4, -1),
            );
        }
        return self::NAMED_COLORS[$trimmed] ?? null
            ? self::fromIntTriple(self::NAMED_COLORS[$trimmed])
            : null;
    }

    private static function parseHex(string $hex): ?RgbColor
    {
        if (preg_match('/^[0-9a-f]{3}$/', $hex) === 1) {
            return self::fromIntTriple([
                hexdec($hex[0]) * 17,
                hexdec($hex[1]) * 17,
                hexdec($hex[2]) * 17,
            ]);
        }
        if (preg_match('/^[0-9a-f]{6}$/', $hex) === 1) {
            return self::fromIntTriple([
                (int) hexdec(substr($hex, 0, 2)),
                (int) hexdec(substr($hex, 2, 2)),
                (int) hexdec(substr($hex, 4, 2)),
            ]);
        }
        return null;
    }

    private static function parseRgbFunction(string $body): ?RgbColor
    {
        $parts = array_map('trim', explode(',', $body));
        if (count($parts) !== 3) {
            return null;
        }
        $rgb = [];
        foreach ($parts as $part) {
            if (str_ends_with($part, '%')) {
                $n = (float) substr($part, 0, -1);
                if ($n < 0.0 || $n > 100.0) {
                    return null;
                }
                $rgb[] = (int) round($n / 100.0 * 255.0);
                continue;
            }
            if (!preg_match('/^-?\d+$/', $part)) {
                return null;
            }
            $n = (int) $part;
            if ($n < 0 || $n > 255) {
                return null;
            }
            $rgb[] = $n;
        }
        return self::fromIntTriple($rgb);
    }

    /**
     * @param array{0: int, 1: int, 2: int}|list<int> $rgb
     */
    private static function fromIntTriple(array $rgb): RgbColor
    {
        return RgbColor::fromInt($rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * Curated named-color map. Covers the basic 16 CSS named
     * colors plus a few extras MathML default styling references
     * (notably `salmon` for `<merror>`).
     *
     * @var array<string, array{0: int, 1: int, 2: int}>
     */
    private const array NAMED_COLORS = [
        'black'   => [0, 0, 0],
        'silver'  => [192, 192, 192],
        'gray'    => [128, 128, 128],
        'white'   => [255, 255, 255],
        'maroon'  => [128, 0, 0],
        'red'     => [255, 0, 0],
        'purple'  => [128, 0, 128],
        'fuchsia' => [255, 0, 255],
        'green'   => [0, 128, 0],
        'lime'    => [0, 255, 0],
        'olive'   => [128, 128, 0],
        'yellow'  => [255, 255, 0],
        'navy'    => [0, 0, 128],
        'blue'    => [0, 0, 255],
        'teal'    => [0, 128, 128],
        'aqua'    => [0, 255, 255],
        'cyan'    => [0, 255, 255],
        'magenta' => [255, 0, 255],
        'orange'  => [255, 165, 0],
        'pink'    => [255, 192, 203],
        'salmon'  => [250, 128, 114],
        'lightsalmon'   => [255, 160, 122],
        'lightpink'     => [255, 182, 193],
        'gold'          => [255, 215, 0],
        'lightgray'     => [211, 211, 211],
        'lightgrey'     => [211, 211, 211],
        'darkred'       => [139, 0, 0],
        'darkblue'      => [0, 0, 139],
        'darkgreen'     => [0, 100, 0],
    ];
}
