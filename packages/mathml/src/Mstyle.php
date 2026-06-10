<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mstyle>` — style-attribute container (MathML Core §3.5.1).
 *
 * Acts like a transparent `<mrow>` for content but can override the
 * cascade's `displaystyle` and `scriptlevel` for its descendants.
 * The painter spawns a child context with the overrides applied,
 * paints children there, then drops back.
 *
 * Supported attributes for v1:
 *
 *   - `displaystyle="true|false"` — explicit override.
 *   - `scriptlevel="N"`           — set absolute level (N >= 0).
 *   - `scriptlevel="+N"`          — increment by N.
 *   - `scriptlevel="-N"`          — decrement by N (floor 0).
 *
 * Other historical mstyle attributes (mathcolor, mathbackground,
 * fontfamily, etc.) round-trip through the parser but the v1
 * painter ignores them.
 */
final class Mstyle extends Element
{
    public function __construct()
    {
        parent::__construct('mstyle');
    }

    /**
     * Explicit `displaystyle` override. Returns null when the
     * attribute is absent so the painter falls back to the
     * surrounding context.
     */
    public function displaystyle(): ?bool
    {
        $raw = $this->attributes['displaystyle'] ?? null;
        if ($raw === null) {
            return null;
        }
        return match (strtolower(trim($raw))) {
            'true'  => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * Parsed `scriptlevel`. The shape distinguishes absolute from
     * relative overrides so the painter can apply the cascade rule
     * correctly:
     *
     *   ['absolute', 2]   — `scriptlevel="2"` sets level to 2.
     *   ['relative', 1]   — `scriptlevel="+1"` increments by 1.
     *   ['relative', -2]  — `scriptlevel="-2"` decrements by 2.
     *   null              — attribute absent.
     *
     * @return ?array{0: 'absolute'|'relative', 1: int}
     */
    public function scriptlevel(): ?array
    {
        $raw = $this->attributes['scriptlevel'] ?? null;
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        // Relative forms start with explicit +/- and a digit.
        if (preg_match('/^([+-])(\d+)$/', $trimmed, $m)) {
            $value = (int) $m[2];
            return ['relative', $m[1] === '-' ? -$value : $value];
        }
        // Absolute form: a non-negative integer with no sign.
        if (preg_match('/^\d+$/', $trimmed)) {
            return ['absolute', (int) $trimmed];
        }
        return null;
    }
}
