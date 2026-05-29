<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Shape;

use Phpdftk\Svg\Element;

/**
 * SVG `<rect>` element per SVG 2 §10.4. Stores attributes as raw strings
 * and parses them on demand — see `x()` / `y()` / `width()` / `height()`
 * / `rx()` / `ry()` for the typed accessors.
 *
 * Length parsing keeps the value + unit; unitless numbers (the common case
 * for `viewBox`-relative content) come back as a float with `unit = null`.
 */
final class Rect extends Element
{
    public function __construct()
    {
        parent::__construct('rect');
    }

    /** `x` attribute parsed as length; null/missing → 0 per spec. */
    public function x(): float
    {
        return $this->parseLengthOrZero('x');
    }

    /** `y` attribute parsed as length; null/missing → 0 per spec. */
    public function y(): float
    {
        return $this->parseLengthOrZero('y');
    }

    public function width(): float
    {
        return $this->parseLengthOrZero('width');
    }

    public function height(): float
    {
        return $this->parseLengthOrZero('height');
    }

    /**
     * `rx` (corner-radius x). Returns null when neither `rx` nor `ry` is
     * set so the caller can distinguish "no rounding" from "rounded with
     * radius 0" — semantically the same paint, but useful for downstream
     * code that wants to preserve the author's intent.
     */
    public function rx(): ?float
    {
        if ($this->hasAttribute('rx')) {
            return $this->parseLengthOrZero('rx');
        }
        if ($this->hasAttribute('ry')) {
            return $this->parseLengthOrZero('ry');
        }
        return null;
    }

    public function ry(): ?float
    {
        if ($this->hasAttribute('ry')) {
            return $this->parseLengthOrZero('ry');
        }
        if ($this->hasAttribute('rx')) {
            return $this->parseLengthOrZero('rx');
        }
        return null;
    }

    private function parseLengthOrZero(string $attr): float
    {
        $raw = $this->getAttribute($attr);
        if ($raw === null) {
            return 0.0;
        }
        // SVG length grammar: optional sign, number (with optional
        // fraction and exponent), optional unit (px/pt/mm/cm/in/pc/em
        // /ex/%). Anything that isn't a valid number prefix returns
        // 0.0 — the spec treats invalid lengths as the initial value.
        if (preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $raw, $m) !== 1) {
            return 0.0;
        }
        return (float) $m[1];
    }
}
