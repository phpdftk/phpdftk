<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.8 — `<feDisplacementMap>`. Uses one
 * input's pixel values to spatially offset another input. Common
 * pairing with feTurbulence to "wobble" content.
 *
 *   in / in2:               primary + displacement source
 *   scale:                  displacement magnitude (default 0)
 *   xChannelSelector:       R | G | B | A (default A)
 *   yChannelSelector:       R | G | B | A (default A)
 */
final class FeDisplacementMap extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feDisplacementMap');
    }

    public function in2(): ?string
    {
        return $this->getAttribute('in2');
    }

    public function scale(): float
    {
        return (float) ($this->getAttribute('scale') ?? 0);
    }

    public function xChannelSelector(): string
    {
        return $this->channel('xChannelSelector');
    }

    public function yChannelSelector(): string
    {
        return $this->channel('yChannelSelector');
    }

    private function channel(string $attr): string
    {
        $v = strtoupper($this->getAttribute($attr) ?? 'A');
        return in_array($v, ['R', 'G', 'B', 'A'], true) ? $v : 'A';
    }
}
