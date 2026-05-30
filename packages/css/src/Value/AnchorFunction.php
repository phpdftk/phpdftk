<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `anchor([<anchor-element>] <anchor-side> [, <fallback>])` per
 * CSS Anchor Positioning 1 §6. Used as a value for the inset
 * properties (top / right / bottom / left, plus the logical
 * equivalents) to position the box relative to a named anchor.
 *
 *   top: anchor(--my bottom)
 *   left: anchor(--my right, 50%)
 *   top: anchor(bottom)           — implicit anchor reference
 *
 * Stored parser-side. The layout engine resolves the anchor
 * reference + side at positioning time once the anchor's PDF-
 * space rect is known.
 */
final readonly class AnchorFunction extends Value
{
    public function __construct(
        /**
         * `<dashed-ident>` anchor name, e.g. `--my-anchor`. Null
         * when the author omitted it (CSS Anchor Positioning 1
         * §6 — the engine consults `position-anchor` to fill in
         * the implicit reference).
         */
        public ?string $anchorName,
        /**
         * The `<anchor-side>` — Keyword (top / bottom / left /
         * right / start / end / center / self-start / self-end /
         * inside / outside) or Percentage.
         */
        public Value $side,
        /**
         * Optional fallback when the anchor reference can't be
         * resolved at layout time.
         */
        public ?Value $fallback = null,
    ) {}

    public function toCss(): string
    {
        $parts = [];
        if ($this->anchorName !== null) {
            $parts[] = $this->anchorName;
        }
        $parts[] = $this->side->toCss();
        $head = 'anchor(' . implode(' ', $parts);
        return $this->fallback !== null
            ? $head . ', ' . $this->fallback->toCss() . ')'
            : $head . ')';
    }
}
