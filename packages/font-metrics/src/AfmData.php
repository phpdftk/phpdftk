<?php

declare(strict_types=1);

namespace Phpdftk\FontMetrics;

final class AfmData
{
    /**
     * @param array<string,int> $widths Glyph name => width in 1/1000 em
     * @param array{float,float,float,float} $fontBBox [llx,lly,urx,ury]
     */
    public function __construct(
        public readonly float $ascender,
        public readonly float $descender,
        public readonly float $capHeight,
        public readonly float $xHeight,
        public readonly float $italicAngle,
        public readonly float $stemV,
        public readonly float $missingWidth,
        public readonly array $fontBBox,
        public readonly array $widths,
    ) {}

    public function getWidth(string $glyphName): int
    {
        return $this->widths[$glyphName] ?? (int) $this->missingWidth;
    }
}
