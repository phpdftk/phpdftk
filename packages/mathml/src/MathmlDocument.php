<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * The root `<math>` element (MathML Core §3.1.1). Sibling of
 * {@see \Phpdftk\Svg\SvgDocument}.
 *
 * Carries `display`, `xmlns`, and the standard presentation
 * attributes. Painter consumes this as the entry point for rendering
 * a whole math expression.
 */
final class MathmlDocument extends Element
{
    public function __construct()
    {
        parent::__construct('math');
    }

    /**
     * `display` per Core §3.1.1 — `block` for displayed equations,
     * `inline` for inline math. Default is `inline` per spec; absent
     * returns null so the painter applies the default explicitly.
     */
    public function display(): ?string
    {
        $raw = $this->attributes['display'] ?? null;
        if ($raw === null) {
            return null;
        }
        $value = strtolower(trim($raw));
        return match ($value) {
            'block', 'inline' => $value,
            default => null,
        };
    }
}
