<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

use Phpdftk\Svg\Value\Paint;
use Phpdftk\Svg\Value\Transform;

/**
 * An SVG element with a tag name, attributes, and a child list. Concrete
 * subclasses (`Shape\Rect`, `Group`, `SvgDocument`, …) add typed accessors
 * over the raw attribute strings stored here, so callers never have to
 * remember whether `cx` is a length or a number.
 *
 * Attribute names are case-sensitive per the SVG spec — `viewBox` and
 * `clipPathUnits` etc. keep their camelCase. The parser passes them
 * through verbatim.
 */
abstract class Element extends Node
{
    /** @var array<string, string> */
    public array $attributes = [];

    /** @var list<Node> */
    public array $children = [];

    public function __construct(public readonly string $localName) {}

    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function setAttribute(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function appendChild(Node $node): void
    {
        $node->parent = $this;
        $this->children[] = $node;
    }

    /** @return list<Element> elements with the given local name in document order. */
    public function findByTag(string $localName): array
    {
        $out = [];
        foreach ($this->children as $child) {
            if ($child instanceof Element) {
                if ($child->localName === $localName) {
                    $out[] = $child;
                }
                foreach ($child->findByTag($localName) as $nested) {
                    $out[] = $nested;
                }
            }
        }
        return $out;
    }

    /**
     * Read an SVG length attribute as a float, falling back to 0 when the
     * attribute is absent OR doesn't start with a parseable number — per
     * SVG 2's "invalid value → initial value" rule for `<length>`. Unit
     * suffixes (`px`, `pt`, `mm`, `%`, …) are tolerated and ignored.
     */
    protected function parseLengthOrZero(string $attr): float
    {
        $raw = $this->attributes[$attr] ?? null;
        if ($raw === null) {
            return 0.0;
        }
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $raw, $m) !== 1) {
            return 0.0;
        }
        return (float) $m[1];
    }

    /**
     * Parse the `transform` attribute per SVG 2 §8.4. Returns null when the
     * attribute is absent, empty, or malformed — SVG 2's "invalid →
     * ignored" semantics. Callers that want a hard error should call
     * `Transform::parse()` directly.
     */
    public function transform(): ?Transform
    {
        $raw = $this->attributes['transform'] ?? null;
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        try {
            return Transform::parse($raw);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Class names from the `class` attribute, whitespace-separated per
     * HTML / CSS conventions. Empty list when absent or empty.
     *
     * @return list<string>
     */
    public function classList(): array
    {
        $raw = $this->attributes['class'] ?? null;
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/\s+/', trim($raw)) ?: [];
        return array_values(array_filter($parts, static fn(string $c): bool => $c !== ''));
    }

    /**
     * Raw `style=""` attribute text, or null if absent. Parsing into
     * typed declarations happens in `Css\CssBridge`; sanitiser callers
     * can read the raw text without pulling in the CSS dependency.
     */
    public function inlineStyleText(): ?string
    {
        $raw = $this->attributes['style'] ?? null;
        if ($raw === null) {
            return null;
        }
        return trim($raw) === '' ? null : $raw;
    }

    /**
     * `fill` presentation attribute per SVG 2 §13.2. Null when absent or
     * malformed; the painter then applies inherited or initial values.
     */
    public function fill(): ?Paint
    {
        return $this->parsePaint('fill');
    }

    /**
     * `stroke` presentation attribute per SVG 2 §13.2. Default is `none`
     * per spec; absent here returns null so the painter can distinguish
     * "not set on this element" from "explicitly none".
     */
    public function stroke(): ?Paint
    {
        return $this->parsePaint('stroke');
    }

    /** `fill-opacity` — clamped to [0, 1] per SVG 2 §13.2. */
    public function fillOpacity(): ?float
    {
        return $this->parseClampedFraction('fill-opacity');
    }

    /** `stroke-opacity` — clamped to [0, 1]. */
    public function strokeOpacity(): ?float
    {
        return $this->parseClampedFraction('stroke-opacity');
    }

    /** Group `opacity` — clamped to [0, 1]. */
    public function opacity(): ?float
    {
        return $this->parseClampedFraction('opacity');
    }

    /**
     * `fill-rule` — one of `nonzero` or `evenodd`. Returns null for absent
     * or unrecognised values so the painter applies the initial value
     * (`nonzero`) rather than guessing.
     */
    public function fillRule(): ?string
    {
        $raw = $this->presentationOrStyle('fill-rule');
        if ($raw === null) {
            return null;
        }
        $value = strtolower(trim($raw));
        return match ($value) {
            'nonzero', 'evenodd' => $value,
            default => null,
        };
    }

    /** `stroke-width` — non-negative length, default `1`. Negative → null. */
    public function strokeWidth(): ?float
    {
        $raw = $this->presentationOrStyle('stroke-width');
        if ($raw === null) {
            return null;
        }
        $value = $this->parseNumberPrefix($raw);
        if ($value === null || $value < 0.0) {
            return null;
        }
        return $value;
    }

    /** `stroke-linecap` — one of `butt`, `round`, `square`. */
    public function strokeLinecap(): ?string
    {
        $raw = $this->presentationOrStyle('stroke-linecap');
        if ($raw === null) {
            return null;
        }
        $value = strtolower(trim($raw));
        return match ($value) {
            'butt', 'round', 'square' => $value,
            default => null,
        };
    }

    /**
     * `stroke-linejoin` — `miter`, `round`, `bevel`, plus SVG 2's
     * `miter-clip` and `arcs`.
     */
    public function strokeLinejoin(): ?string
    {
        $raw = $this->presentationOrStyle('stroke-linejoin');
        if ($raw === null) {
            return null;
        }
        $value = strtolower(trim($raw));
        return match ($value) {
            'miter', 'round', 'bevel', 'miter-clip', 'arcs' => $value,
            default => null,
        };
    }

    /** `stroke-miterlimit` — must be ≥ 1 per SVG 2 §13.4; otherwise null. */
    public function strokeMiterlimit(): ?float
    {
        $raw = $this->presentationOrStyle('stroke-miterlimit');
        if ($raw === null) {
            return null;
        }
        $value = $this->parseNumberPrefix($raw);
        if ($value === null || $value < 1.0) {
            return null;
        }
        return $value;
    }

    /**
     * `stroke-dasharray` — `none` or a list of lengths. Returns an empty
     * list for both null and `none` so the painter has a single
     * "no dashes" branch.
     *
     * @return list<float>
     */
    public function strokeDasharray(): array
    {
        $raw = $this->presentationOrStyle('stroke-dasharray');
        if ($raw === null) {
            return [];
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || strcasecmp($trimmed, 'none') === 0) {
            return [];
        }
        if (preg_match_all('/[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $trimmed, $m) === false) {
            return [];
        }
        $out = [];
        foreach ($m[0] as $token) {
            $n = (float) $token;
            if ($n < 0.0) {
                // SVG 2 §13.4: a single negative value invalidates the
                // entire list. Painter falls back to no dashes.
                return [];
            }
            $out[] = $n;
        }
        return $out;
    }

    /** `stroke-dashoffset` — any number; default `0`. */
    public function strokeDashoffset(): ?float
    {
        $raw = $this->presentationOrStyle('stroke-dashoffset');
        if ($raw === null) {
            return null;
        }
        return $this->parseNumberPrefix($raw);
    }

    /**
     * `font-family` — CSS Fonts 4 §3.2. A comma-separated prioritised list
     * of family names; each entry is trimmed and surrounding single or
     * double quotes are stripped (CSS reserves quotes for names containing
     * whitespace or reserved words). An absent attribute → empty list, so
     * the painter applies the inherited or default stack.
     *
     * @return list<string>
     */
    public function fontFamily(): array
    {
        $raw = $this->presentationOrStyle('font-family');
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $parts = explode(',', $raw);
        $out = [];
        foreach ($parts as $part) {
            $name = trim($part);
            if ($name === '') {
                continue;
            }
            if (strlen($name) >= 2) {
                $first = $name[0];
                $last = $name[strlen($name) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $name = substr($name, 1, -1);
                }
            }
            if ($name !== '') {
                $out[] = $name;
            }
        }
        return $out;
    }

    /**
     * `font-size` — CSS Fonts 4 §3.5. Length value; absolute keywords
     * (`small`, `large`, …) and percentages are deferred to the cascade
     * work in 3J.
     */
    public function fontSize(): ?float
    {
        $raw = $this->presentationOrStyle('font-size');
        if ($raw === null) {
            return null;
        }
        $value = $this->parseNumberPrefix($raw);
        if ($value === null || $value < 0.0) {
            return null;
        }
        return $value;
    }

    /**
     * `font-weight` — returned as a raw string (`normal`, `bold`,
     * `bolder`, `lighter`, or a numeric weight like `400`). The painter
     * normalises to the OpenType weight axis; the parser stays neutral.
     */
    public function fontWeight(): ?string
    {
        $raw = $this->presentationOrStyle('font-weight');
        if ($raw === null) {
            return null;
        }
        $value = trim($raw);
        return $value === '' ? null : $value;
    }

    /**
     * `font-style` — `normal`, `italic`, or `oblique`. Returns null for
     * absent and unrecognised values so the painter applies the inherited
     * or initial style.
     */
    public function fontStyle(): ?string
    {
        $raw = $this->presentationOrStyle('font-style');
        if ($raw === null) {
            return null;
        }
        $value = strtolower(trim($raw));
        return match ($value) {
            'normal', 'italic', 'oblique' => $value,
            default => null,
        };
    }

    private function parsePaint(string $attr): ?Paint
    {
        $raw = $this->presentationOrStyle($attr);
        if ($raw === null) {
            return null;
        }
        return Paint::parse($raw);
    }

    private function parseClampedFraction(string $attr): ?float
    {
        $raw = $this->presentationOrStyle($attr);
        if ($raw === null) {
            return null;
        }
        $value = $this->parseNumberPrefix($raw);
        if ($value === null) {
            return null;
        }
        return max(0.0, min(1.0, $value));
    }

    /**
     * Resolve a presentation attribute value, falling back to the
     * `style` attribute's CSS declaration of the same property when
     * the presentation form is absent. SVG 2 §6.1 defines this
     * equivalence: `fill="green"` and `style="fill: green"` are
     * interchangeable. The wpt-harness DOM settler projects
     * computed CSS values into inline `style` so external rules
     * reach this hook too.
     *
     * Block comments (the settler emits a `/* phpdftk-settle-dom *\/`
     * marker) are stripped before tokenising so the prefix doesn't
     * fool the property-name match.
     */
    protected function presentationOrStyle(string $property): ?string
    {
        $raw = $this->attributes[$property] ?? null;
        if ($raw !== null) {
            return $raw;
        }
        $style = $this->attributes['style'] ?? null;
        if ($style === null) {
            return null;
        }
        $cleaned = preg_replace('/\/\*.*?\*\//s', ' ', $style) ?? $style;
        $target = strtolower($property);
        foreach (explode(';', $cleaned) as $decl) {
            $colon = strpos($decl, ':');
            if ($colon === false) {
                continue;
            }
            $name = strtolower(trim(substr($decl, 0, $colon)));
            if ($name !== $target) {
                continue;
            }
            $value = trim(substr($decl, $colon + 1));
            return $value === '' ? null : $value;
        }
        return null;
    }

    private function parseNumberPrefix(string $raw): ?float
    {
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $raw, $m) !== 1) {
            return null;
        }
        return (float) $m[1];
    }

    /**
     * Parse the `points` attribute grammar (SVG 2 §9.7) — a list of `(x, y)`
     * coordinate pairs separated by comma-or-whitespace. Per spec, a malformed
     * tail (odd count, non-numeric value) terminates parsing; pairs read
     * before the error are kept.
     *
     * @return list<array{float, float}>
     */
    protected static function parsePoints(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $numberPattern = '[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?';
        if (preg_match_all('/' . $numberPattern . '/', $raw, $m) === false) {
            return [];
        }
        $values = $m[0];
        if ($values === []) {
            return [];
        }
        $pairs = [];
        $count = (int) (count($values) / 2) * 2;
        for ($i = 0; $i < $count; $i += 2) {
            $pairs[] = [(float) $values[$i], (float) $values[$i + 1]];
        }
        return $pairs;
    }
}
