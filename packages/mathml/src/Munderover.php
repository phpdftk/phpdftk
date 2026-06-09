<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<munderover>` — combined under-and-overscript (MathML Core §3.3.7).
 *
 * Exactly three element children in document order: `BASE UNDER OVER`.
 * Width = `max(baseWidth, underWidth, overWidth)`. Both scripts
 * centred horizontally over/under the base.
 *
 * Combines `accentunder` and `accent` attribute semantics from the
 * single-side variants ({@see Munder}, {@see Mover}).
 */
final class Munderover extends Element
{
    public function __construct()
    {
        parent::__construct('munderover');
    }

    /** See {@see Mover::accent()}. */
    public function accent(): ?bool
    {
        return $this->parseBoolean('accent');
    }

    /** See {@see Munder::accentunder()}. */
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
