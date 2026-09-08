<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value;

/**
 * The `transform-origin` presentation attribute / CSS property for SVG
 * elements (CSS Transforms 1 §6).
 *
 * SVG elements have no associated CSS layout box, so their used initial
 * `transform-origin` is `0 0` rather than the `50% 50%` that applies to
 * CSS boxes — an element with no `transform-origin` pivots on its
 * reference box's origin instead of its centre.
 *
 * Everything here is an offset from that reference box: percentages
 * resolve against its size, lengths are a plain offset from its origin,
 * and position keywords name its edges. WHICH box that is comes from
 * `transform-box` (CSS Transforms 1 §7) and is the caller's business —
 * this class only resolves against the rectangle it is handed.
 *
 * Only the first two components are meaningful; a third (Z) component
 * is validated and dropped, as this renderer is 2D.
 */
final class TransformOrigin
{
    /**
     * @param list<array{keyword: ?string, value: float, percent: bool}> $components
     */
    private function __construct(private readonly array $components) {}

    /**
     * Parse the `transform-origin` grammar (CSS Transforms 1 §6):
     *
     *   [ left | center | right | top | bottom | <length-percentage> ]
     * | [ left | center | right | <length-percentage> ]
     *   [ top | center | bottom | <length-percentage> ] <length>?
     * | [ [ center | left | right ] && [ center | top | bottom ] ] <length>?
     *
     * The two-value form is ORDERED (X then Y) unless both components
     * are position keywords, in which case they may appear in either
     * order. So `top left` is valid but `top 100%` is not, and `left
     * left` / `top bottom` name the same axis twice and are not either.
     *
     * Returns null for anything that does not match, so the caller falls
     * back to the `0 0` initial rather than a partial reading — the
     * `svg-origin-relative-length-invalid-*` tests turn on exactly that.
     */
    public static function parse(string $raw): ?self
    {
        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];
        $components = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $component = self::parseComponent($part);
            if ($component === null) {
                return null;
            }
            $components[] = $component;
        }
        if ($components === [] || count($components) > 3) {
            return null;
        }
        if (count($components) > 1 && !self::axesAreAssignable($components)) {
            return null;
        }
        // A third component is the Z offset, which must be a plain
        // <length>. This renderer is 2D, so validate it and drop it.
        if (count($components) === 3
            && ($components[2]['keyword'] !== null || $components[2]['percent'])
        ) {
            return null;
        }
        return new self(array_slice($components, 0, 2));
    }

    /**
     * @param list<array{keyword: ?string, value: float, percent: bool}> $components
     */
    private static function axesAreAssignable(array $components): bool
    {
        $first = $components[0];
        $second = $components[1];
        $bothKeywords = $first['keyword'] !== null && $second['keyword'] !== null;
        if ($bothKeywords) {
            // Unordered form: valid as long as the pair does not name the
            // same axis twice. `center` is compatible with either.
            return !(self::isXKeyword($first) && self::isXKeyword($second))
                && !(self::isYKeyword($first) && self::isYKeyword($second));
        }
        // Ordered form: X component first, Y component second.
        return !self::isYKeyword($first) && !self::isXKeyword($second);
    }

    /**
     * @param array{keyword: ?string, value: float, percent: bool} $c
     */
    private static function isXKeyword(array $c): bool
    {
        return $c['keyword'] === 'left' || $c['keyword'] === 'right';
    }

    /**
     * @param array{keyword: ?string, value: float, percent: bool} $c
     */
    private static function isYKeyword(array $c): bool
    {
        return $c['keyword'] === 'top' || $c['keyword'] === 'bottom';
    }

    /**
     * @return array{keyword: ?string, value: float, percent: bool}|null
     */
    private static function parseComponent(string $part): ?array
    {
        $lower = strtolower($part);
        if (in_array($lower, ['left', 'right', 'top', 'bottom', 'center'], true)) {
            return ['keyword' => $lower, 'value' => 0.0, 'percent' => false];
        }
        if (preg_match(
            '/^([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s*(%|[a-zA-Z]*)$/',
            $part,
            $m,
        ) !== 1) {
            return null;
        }
        if ($m[2] === '%') {
            return ['keyword' => null, 'value' => (float) $m[1], 'percent' => true];
        }
        return [
            'keyword' => null,
            'value' => (float) $m[1] * self::unitScale($m[2]),
            'percent' => false,
        ];
    }

    /**
     * Resolve to a user-space pivot point against the element's object
     * bounding box.
     *
     * Keywords bind by AXIS IDENTITY, not by order (the `<position>`
     * grammar): `left`/`right` name X and `top`/`bottom` name Y in
     * either order, while `center`, lengths and percentages fill the
     * remaining axes positionally, X first. A single specified
     * component leaves the other axis at `center`.
     *
     * @return array{float, float}
     */
    public function resolve(float $bx, float $by, float $bw, float $bh): array
    {
        $x = null;
        $y = null;
        $positional = [];
        foreach ($this->components as $c) {
            switch ($c['keyword']) {
                case 'left':   $x = $bx;
                    continue 2;
                case 'right':  $x = $bx + $bw;
                    continue 2;
                case 'top':    $y = $by;
                    continue 2;
                case 'bottom': $y = $by + $bh;
                    continue 2;
                default:
                    $positional[] = $c;
            }
        }
        foreach ($positional as $c) {
            if ($x === null) {
                $x = self::component($c, $bx, $bw);
            } elseif ($y === null) {
                $y = self::component($c, $by, $bh);
            }
        }
        return [$x ?? $bx + $bw / 2.0, $y ?? $by + $bh / 2.0];
    }

    /**
     * @param array{keyword: ?string, value: float, percent: bool} $c
     */
    private static function component(array $c, float $origin, float $extent): float
    {
        if ($c['keyword'] === 'center') {
            return $origin + $extent / 2.0;
        }
        return $origin + ($c['percent'] ? $c['value'] / 100.0 * $extent : $c['value']);
    }

    /**
     * Absolute CSS unit → user units (CSS Values 4 §6.2, 96dpi). A bare
     * number is a user-unit length in the presentation-attribute form.
     */
    private static function unitScale(string $unit): float
    {
        return match (strtolower($unit)) {
            'cm' => 96.0 / 2.54,
            'mm' => 96.0 / 25.4,
            'q' => 96.0 / 101.6,
            'in' => 96.0,
            'pt' => 96.0 / 72.0,
            'pc' => 16.0,
            default => 1.0,
        };
    }
}
