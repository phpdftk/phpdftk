<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `anchor-size([<anchor-element>] <anchor-size>, [<fallback>])`
 * per CSS Anchor Positioning 1 §7. Used to size the box relative
 * to a named anchor's width / height / inline-size / block-size.
 *
 *   width: anchor-size(--card width)
 *   height: anchor-size(--card height, 200px)
 */
final readonly class AnchorSizeFunction extends Value
{
    public function __construct(
        public ?string $anchorName,
        public Value $dimension,
        public ?Value $fallback = null,
    ) {}

    public function toCss(): string
    {
        $parts = [];
        if ($this->anchorName !== null) {
            $parts[] = $this->anchorName;
        }
        $parts[] = $this->dimension->toCss();
        $head = 'anchor-size(' . implode(' ', $parts);
        return $this->fallback !== null
            ? $head . ', ' . $this->fallback->toCss() . ')'
            : $head . ')';
    }
}
