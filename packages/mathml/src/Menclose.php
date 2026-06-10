<?php

declare(strict_types=1);

namespace Phpdftk\Mathml;

/**
 * `<menclose>` - wraps a child group with one or more notation
 * decorations (MathML 3 §3.3.9, retained as a presentation legacy
 * outside MathML Core but still widely used by content generators
 * and reflected in the WPT corpus).
 *
 * The `notation` attribute is a space-separated list of keywords;
 * the painter draws each keyword's decoration on top of the painted
 * content. v1 painter recognises the strokeable subset:
 *
 *   - `box`               - rectangle around the content
 *   - `roundedbox`        - same path as box for v1 (rounded corners
 *                           are a follow-up)
 *   - `longdiv`           - top edge + left edge (open-rectangle)
 *   - `actuarial`         - top edge + right edge
 *   - `horizontalstrike`  - horizontal line through the middle
 *   - `verticalstrike`    - vertical line through the middle
 *   - `updiagonalstrike`  - diagonal from bottom-left to top-right
 *   - `downdiagonalstrike`- diagonal from top-left to bottom-right
 *   - `top` / `bottom`    - single top or bottom edge
 *   - `left` / `right`    - single left or right edge
 *
 * Unrecognised notations (e.g. `radical`, `circle`, `madruwb`,
 * `phasorangle`) silently no-op for v1; content still renders.
 *
 * When `notation` is absent or empty, the spec default is `longdiv`;
 * the painter applies that fallback so author intent is visible.
 */
final class Menclose extends Element
{
    public function __construct()
    {
        parent::__construct('menclose');
    }

    /**
     * Parsed list of notation keywords (lower-case, deduplicated,
     * preserves first-occurrence order). Returns ['longdiv'] when
     * the attribute is absent or empty (spec default).
     *
     * @return list<string>
     */
    public function notations(): array
    {
        $raw = $this->attributes['notation'] ?? null;
        if ($raw === null) {
            return ['longdiv'];
        }
        $tokens = preg_split('/\s+/', strtolower(trim($raw))) ?: [];
        $seen = [];
        $out = [];
        foreach ($tokens as $tok) {
            if ($tok === '' || isset($seen[$tok])) {
                continue;
            }
            $seen[$tok] = true;
            $out[] = $tok;
        }
        return $out === [] ? ['longdiv'] : $out;
    }
}
