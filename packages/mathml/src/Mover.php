<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mover>` — element over the base (MathML Core §3.3.7).
 *
 * Exactly two element children: base then overscript.
 * `<mover>BASE OVER</mover>` renders the base with a smaller
 * overscript centred above. Canonical example: `<mover><mi>x</mi>
 * <mo>¯</mo></mover>` for x with an overline accent.
 *
 * The `accent` attribute hints whether the overscript is an accent
 * (combining mark) versus a limits-like operator (sup of an integral
 * with the base being ∫). Accents render flush against the base;
 * non-accents get a small gap. The v1 painter applies a uniform gap.
 */
final class Mover extends Element
{
    public function __construct()
    {
        parent::__construct('mover');
    }

    /**
     * `accent` per Core §3.3.7 — `true` when the overscript is an
     * accent. Null for absent / unrecognised so the painter applies
     * the spec default (false).
     */
    public function accent(): ?bool
    {
        return $this->parseBoolean('accent');
    }

    private function parseBoolean(string $attr): ?bool
    {
        $raw = $this->attributes[$attr] ?? null;
        if ($raw === null) {
            return null;
        }
        $value = strtolower(trim($raw));
        return match ($value) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }
}
