<?php

declare(strict_types=1);

namespace Phpdftk\Raster\Encoder;

use Phpdftk\Raster\RasterSurface;

/**
 * Encode a {@see RasterSurface} to a binary format ready for
 * embedding in a PDF or saving to disk. Implementations:
 *
 *   - `PngEncoder`        PNG bytes (4C.5)
 *   - `JpegEncoder`       JPEG bytes for opaque surfaces (future)
 *   - `RawEncoder`        flat RGBA bytes for tests / debugging
 *
 * The PDF wrapping (turning the encoded bytes into an Image XObject
 * with the right `/Filter` + `/ColorSpace` + `/BitsPerComponent`
 * entries) is the translator's job, not the encoder's.
 */
interface EncoderInterface
{
    /**
     * Return the encoded bytes. Implementations choose how to
     * handle alpha — PNG carries it directly; JPEG would need to
     * composite over a background.
     */
    public function encode(RasterSurface $surface): string;
}
