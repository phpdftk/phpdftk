<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.9 — `<feImage href preserveAspectRatio>`.
 * Loads an external image (or references an in-document element by
 * fragment) and uses it as the primitive's output. The legacy
 * `xlink:href` attribute is also recognised.
 */
final class FeImage extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feImage');
    }

    public function href(): ?string
    {
        return $this->getAttribute('href')
            ?? $this->getAttribute('xlink:href');
    }

    public function preserveAspectRatio(): ?string
    {
        return $this->getAttribute('preserveAspectRatio');
    }

    /**
     * `crossorigin` is the standard fetch CORS hint. For server-
     * side print rendering the value is informational; we surface
     * it on the typed accessor for tooling consumers.
     */
    public function crossOrigin(): ?string
    {
        return $this->getAttribute('crossorigin');
    }
}
