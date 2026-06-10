<?php

declare(strict_types=1);

namespace Phpdftk\FontParser;

/**
 * Per-glyph corner kern table. Up to four corner-specific kern
 * tables; each corner is null when the font supplies no kerning
 * for that corner.
 *
 * Each corner table is stored as a {@see MathKern} - a list of
 * (correctionHeight, kernValue) breakpoints. The painter looks up
 * the kern at a given sub/sup Y offset by finding the lowest
 * breakpoint whose correctionHeight exceeds the offset.
 */
final readonly class MathKernRecord
{
    public function __construct(
        public ?MathKern $topRight = null,
        public ?MathKern $topLeft = null,
        public ?MathKern $bottomRight = null,
        public ?MathKern $bottomLeft = null,
    ) {}
}
