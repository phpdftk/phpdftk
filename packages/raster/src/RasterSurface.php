<?php

declare(strict_types=1);

namespace Phpdftk\Raster;

use Phpdftk\Raster\Exception\RasterException;

/**
 * Mutable RGBA pixel buffer — the substrate everything else in this
 * package paints into.
 *
 * Storage. Pixels live in a flat byte string with 4 bytes per pixel
 * (R, G, B, A in that order), row-major (left-to-right, top-to-
 * bottom). A 1024×1024 surface is ~4 MB. For hot-loop filter
 * primitives, {@see buffer()} returns the raw string so callers can
 * iterate without per-pixel call overhead.
 *
 * Coordinate convention. Y points down (0 at the top), matching SVG
 * / CSS / WHATWG canvas. The exporter (4C.5) handles the SVG↔PDF
 * flip when emitting the PDF Image XObject.
 */
final class RasterSurface
{
    private string $buffer;

    public function __construct(
        public readonly int $width,
        public readonly int $height,
    ) {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'RasterSurface dimensions must be positive; got %d × %d',
                $width,
                $height,
            ));
        }
        // Initialise to fully-transparent black. `str_repeat` is
        // ~5x faster than a per-pixel zero loop on PHP 8.4.
        $this->buffer = str_repeat("\0\0\0\0", $width * $height);
    }

    /**
     * Read the pixel at `(x, y)`. Throws if out of bounds — callers
     * are expected to clip before reading; cheap silent fallback
     * would mask real bugs.
     */
    public function getPixel(int $x, int $y): RgbaPixel
    {
        $this->assertInBounds($x, $y);
        $offset = ($y * $this->width + $x) * 4;
        return new RgbaPixel(
            r: ord($this->buffer[$offset]),
            g: ord($this->buffer[$offset + 1]),
            b: ord($this->buffer[$offset + 2]),
            a: ord($this->buffer[$offset + 3]),
        );
    }

    /**
     * Write a pixel. Throws if out of bounds — same posture as
     * {@see getPixel}.
     */
    public function setPixel(int $x, int $y, RgbaPixel $pixel): void
    {
        $this->assertInBounds($x, $y);
        $offset = ($y * $this->width + $x) * 4;
        $this->buffer[$offset] = chr($pixel->r);
        $this->buffer[$offset + 1] = chr($pixel->g);
        $this->buffer[$offset + 2] = chr($pixel->b);
        $this->buffer[$offset + 3] = chr($pixel->a);
    }

    /**
     * Fill the entire surface with one colour. ~10× faster than a
     * per-pixel loop for large surfaces; useful for flood-fill
     * filter primitives + initial background.
     */
    public function clear(RgbaPixel $pixel): void
    {
        $row = str_repeat(
            chr($pixel->r) . chr($pixel->g) . chr($pixel->b) . chr($pixel->a),
            $this->width,
        );
        $this->buffer = str_repeat($row, $this->height);
    }

    /**
     * Raw byte access. The returned string is `width * height * 4`
     * bytes, row-major RGBA. Hot-loop filter implementations operate
     * on this directly to avoid per-pixel call overhead.
     *
     * The returned string is a copy (PHP string semantics) — modify
     * locally and call {@see setBuffer} to commit back.
     */
    public function buffer(): string
    {
        return $this->buffer;
    }

    /**
     * Replace the entire pixel buffer. The replacement must have
     * exactly `width * height * 4` bytes — anything else is a
     * programming error and throws.
     */
    public function setBuffer(string $bytes): void
    {
        $expected = $this->width * $this->height * 4;
        if (strlen($bytes) !== $expected) {
            throw new RasterException(sprintf(
                'Buffer size mismatch: expected %d bytes (%d × %d × 4), got %d',
                $expected,
                $this->width,
                $this->height,
                strlen($bytes),
            ));
        }
        $this->buffer = $bytes;
    }

    /**
     * Total byte size of the pixel buffer. Useful when budgeting
     * for cache eviction policy in the surface dedupe cache (4C.6).
     */
    public function byteSize(): int
    {
        return $this->width * $this->height * 4;
    }

    private function assertInBounds(int $x, int $y): void
    {
        if ($x < 0 || $x >= $this->width || $y < 0 || $y >= $this->height) {
            throw new RasterException(sprintf(
                'Pixel (%d, %d) out of bounds for %d × %d surface',
                $x,
                $y,
                $this->width,
                $this->height,
            ));
        }
    }
}
