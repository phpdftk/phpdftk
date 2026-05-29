<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

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
