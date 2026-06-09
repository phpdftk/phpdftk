<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<munder>` — element under the base (MathML Core §3.3.7).
 *
 * Exactly two element children: base then underscript.
 * `<munder>BASE UNDER</munder>` renders the base with a smaller
 * underscript centred below.
 *
 * The `accentunder` attribute hints whether the under-script is an
 * accent (e.g. underbar). Accents don't get extra spacing relative
 * to the base; non-accent underscripts (limits-like) get a small
 * gap. The v1 painter applies a uniform gap regardless — accent-
 * specific spacing is a follow-up.
 */
final class Munder extends Element
{
    public function __construct()
    {
        parent::__construct('munder');
    }

    /**
     * `accentunder` per Core §3.3.7 — `true` when the underscript is
     * an accent. Null for absent / unrecognised so the painter
     * applies the spec default (false).
     */
    public function accentunder(): ?bool
    {
        return $this->parseBoolean('accentunder');
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
