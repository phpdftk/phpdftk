<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Text;

use Phpdftk\Svg\Element;

/**
 * Shared base for elements that carry SVG 2 §11.6 text positioning
 * attributes (`<text>` and `<tspan>`). Each of `x`, `y`, `dx`, `dy`, and
 * `rotate` accepts a list of numbers — one value per glyph — so the
 * accessors return a `list<float>` even when the attribute is a single
 * number. An absent attribute → empty list.
 *
 * `textLength` and `lengthAdjust` round out the v1 subset; SVG 2's
 * `<textPath>` is deliberately out of scope (the parser falls back to
 * `GenericElement`).
 */
abstract class TextPositioningElement extends Element
{
    /** @return list<float> */
    public function x(): array
    {
        return self::parseNumberList($this->attributes['x'] ?? null);
    }

    /** @return list<float> */
    public function y(): array
    {
        return self::parseNumberList($this->attributes['y'] ?? null);
    }

    /** @return list<float> */
    public function dx(): array
    {
        return self::parseNumberList($this->attributes['dx'] ?? null);
    }

    /** @return list<float> */
    public function dy(): array
    {
        return self::parseNumberList($this->attributes['dy'] ?? null);
    }

    /**
     * Per-glyph rotation in degrees (SVG 2 §11.6). One value applies to all
     * subsequent glyphs; multiple values apply per-glyph, last value
     * persisting for trailing glyphs.
     *
     * @return list<float>
     */
    public function rotate(): array
    {
        return self::parseNumberList($this->attributes['rotate'] ?? null);
    }

    /**
     * `textLength` — the user-space length the text should occupy. SVG 2
     * §11.7. Null when absent; values are non-negative lengths.
     */
    public function textLength(): ?float
    {
        $raw = $this->attributes['textLength'] ?? null;
        if ($raw === null) {
            return null;
        }
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $raw, $m) !== 1) {
            return null;
        }
        $value = (float) $m[1];
        return $value < 0.0 ? null : $value;
    }

    /**
     * `lengthAdjust` — `spacing` (default) stretches inter-glyph gaps;
     * `spacingAndGlyphs` also stretches each glyph. SVG 2 §11.7.
     */
    public function lengthAdjust(): ?string
    {
        $raw = $this->attributes['lengthAdjust'] ?? null;
        if ($raw === null) {
            return null;
        }
        $value = trim($raw);
        return match ($value) {
            'spacing', 'spacingAndGlyphs' => $value,
            default => null,
        };
    }

    /**
     * @return list<float>
     */
    private static function parseNumberList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        if (preg_match_all('/[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $raw, $m) === false) {
            return [];
        }
        return array_values(array_map(static fn(string $v): float => (float) $v, $m[0]));
    }
}
