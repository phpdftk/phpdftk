<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<maction>` — interactive math expression (MathML Core §3.6.1).
 *
 * In MathML Core, `<maction>` no longer carries interactive
 * semantics (toggle, statusline, tooltip…); the spec reduces it
 * to a passthrough that renders the child indicated by the
 * `selection` attribute, defaulting to the first child. Authors
 * still ship `<maction>` in legacy content so the painter has to
 * know how to walk it.
 *
 * Attributes consulted:
 *
 *   - `selection="N"` — 1-based index of the child to render.
 *     Out-of-range or non-numeric values fall back to 1.
 *
 * The `actiontype` attribute is preserved for round-trip but
 * the v1 painter ignores it - no PDF analogue for "toggle" /
 * "tooltip" in a static document.
 */
final class Maction extends Element
{
    public function __construct()
    {
        parent::__construct('maction');
    }

    /**
     * 1-based index of the child to render. Returns 1 when the
     * `selection` attribute is missing, empty, non-numeric, or
     * not a positive integer. The painter is responsible for
     * clamping against the actual child count.
     */
    public function selection(): int
    {
        $raw = $this->attributes['selection'] ?? null;
        if ($raw === null) {
            return 1;
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || !preg_match('/^\d+$/', $trimmed)) {
            return 1;
        }
        $value = (int) $trimmed;
        return $value >= 1 ? $value : 1;
    }
}
