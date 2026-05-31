<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `circle([<shape-radius>] [at <position>]?)` per CSS Shapes 1
 * §3.2. The radius defaults to `closest-side` when omitted; the
 * position defaults to `center` (50% 50%) when omitted.
 */
final readonly class CircleShape extends BasicShape
{
    public function __construct(
        /**
         * Radius — Length / Percentage / Keyword('closest-side') /
         * Keyword('farthest-side'). Null = `closest-side`
         * default.
         */
        public ?Value $radius = null,
        /**
         * Center x position. Null = 50% default.
         */
        public ?Value $centerX = null,
        /**
         * Center y position. Null = 50% default.
         */
        public ?Value $centerY = null,
    ) {}

    public function toCss(): string
    {
        $parts = [];
        if ($this->radius !== null) {
            $parts[] = $this->radius->toCss();
        }
        if ($this->centerX !== null || $this->centerY !== null) {
            $at = 'at';
            if ($this->centerX !== null) {
                $at .= ' ' . $this->centerX->toCss();
            }
            if ($this->centerY !== null) {
                $at .= ' ' . $this->centerY->toCss();
            }
            $parts[] = $at;
        }
        return 'circle(' . implode(' ', $parts) . ')';
    }
}
