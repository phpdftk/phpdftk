<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<mo>` — operator / fence / separator token (MathML Core §3.2.5).
 *
 * `<mo>` is the most attribute-heavy token: `form` (prefix/infix/
 * postfix), `lspace`/`rspace` (operator spacing), `stretchy`,
 * `largeop`, `movablelimits`. The Translator looks up defaults in
 * the MathML Operator Dictionary; the parser stays neutral and
 * preserves whatever attributes the author wrote.
 */
final class Mo extends Element
{
    public function __construct()
    {
        parent::__construct('mo');
    }

    /**
     * `form` per Core §3.2.5 — `prefix`, `infix`, or `postfix`. Null
     * for absent or unrecognised values so the painter can fall back
     * to position-based heuristics (first child → prefix, last →
     * postfix, otherwise infix).
     */
    public function form(): ?string
    {
        $raw = $this->attributes['form'] ?? null;
        if ($raw === null) {
            return null;
        }
        $value = strtolower(trim($raw));
        return match ($value) {
            'prefix', 'infix', 'postfix' => $value,
            default => null,
        };
    }

    /**
     * `stretchy` per Core §3.2.5 — controls whether the operator
     * grows to fit its surroundings (parentheses, radicals).
     * Defaults to false; painter treats null as the default.
     */
    public function stretchy(): ?bool
    {
        return $this->parseBool('stretchy');
    }

    /** `largeop` per Core §3.2.5 — controls oversize forms (∫, ∑). */
    public function largeop(): ?bool
    {
        return $this->parseBool('largeop');
    }

    /** `movablelimits` per Core §3.2.5 — limits as scripts vs above/below. */
    public function movablelimits(): ?bool
    {
        return $this->parseBool('movablelimits');
    }

    private function parseBool(string $attr): ?bool
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
