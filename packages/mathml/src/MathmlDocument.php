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

    /**
     * Explicit `displaystyle` override on the root (Core §3.1.6).
     * When present this wins over the default derived from
     * `display="block"`. Returns null when absent.
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
     * Initial `scriptlevel` on the root (Core §3.1.6). Only the
     * absolute non-negative integer form is meaningful here -
     * relative `+N` / `-N` make no sense at the root since there
     * is no surrounding level to apply against. Returns null when
     * absent, malformed, or relative.
     */
    public function scriptlevel(): ?int
    {
        $raw = $this->attributes['scriptlevel'] ?? null;
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if (!preg_match('/^\d+$/', $trimmed)) {
            return null;
        }
        return (int) $trimmed;
    }
}
