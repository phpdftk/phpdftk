<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.14 — `<feComponentTransfer>` applies
 * per-channel transfer functions to the input. Contains up to
 * four child elements (`feFuncR`, `feFuncG`, `feFuncB`, `feFuncA`)
 * each defining the transfer for one channel.
 */
final class FeComponentTransfer extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feComponentTransfer');
    }
}
