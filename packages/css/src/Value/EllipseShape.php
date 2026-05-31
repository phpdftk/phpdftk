<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `ellipse([<shape-radius> <shape-radius>]? [at <position>]?)`
 * per CSS Shapes 1 §3.3. When both radii are omitted, defaults
 * to two `closest-side` keywords (one per axis).
 */
final readonly class EllipseShape extends BasicShape
{
    public function __construct(
        public ?Value $radiusX = null,
        public ?Value $radiusY = null,
        public ?Value $centerX = null,
        public ?Value $centerY = null,
    ) {}

    public function toCss(): string
    {
        $parts = [];
        if ($this->radiusX !== null && $this->radiusY !== null) {
            $parts[] = $this->radiusX->toCss();
            $parts[] = $this->radiusY->toCss();
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
        return 'ellipse(' . implode(' ', $parts) . ')';
    }
}
