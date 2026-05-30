<?php

declare(strict_types=1);

namespace Phpdftk\Raster;

/**
 * One pixel of an RGBA raster surface. Components are 8-bit integers
 * in `[0, 255]`. Alpha follows the WHATWG canvas convention:
 * `0` = fully transparent, `255` = fully opaque.
 *
 * The constructor validates each component is in range. Out-of-range
 * values are clamped via {@see RgbaPixel::ofClamped} (preferred for
 * arithmetic results that may overshoot) or rejected via the regular
 * constructor (preferred for explicit caller-supplied values).
 */
final readonly class RgbaPixel
{
    public function __construct(
        public int $r,
        public int $g,
        public int $b,
        public int $a = 255,
    ) {
        if ($r < 0 || $r > 255 || $g < 0 || $g > 255 || $b < 0 || $b > 255 || $a < 0 || $a > 255) {
            throw new \InvalidArgumentException(sprintf(
                'RgbaPixel components must be in [0, 255]; got (%d, %d, %d, %d)',
                $r,
                $g,
                $b,
                $a,
            ));
        }
    }

    /**
     * Clamp each component to `[0, 255]`. Useful when constructing
     * from arithmetic results (filter outputs, blend computations)
     * that may temporarily overshoot.
     */
    public static function ofClamped(int $r, int $g, int $b, int $a = 255): self
    {
        return new self(
            r: max(0, min(255, $r)),
            g: max(0, min(255, $g)),
            b: max(0, min(255, $b)),
            a: max(0, min(255, $a)),
        );
    }

    /**
     * Fully-transparent black — the default for unset pixels in a
     * fresh {@see RasterSurface}.
     */
    public static function transparent(): self
    {
        return new self(0, 0, 0, 0);
    }
}
