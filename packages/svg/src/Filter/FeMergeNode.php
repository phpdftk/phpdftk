<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

use Phpdftk\Svg\Element;

/**
 * SVG 2 Filter Effects §15.11 — `<feMergeNode in>` child of
 * `<feMerge>`. Each node names one input layer to stack into
 * the merge result.
 */
final class FeMergeNode extends Element
{
    public function __construct()
    {
        parent::__construct('feMergeNode');
    }

    public function in(): ?string
    {
        return $this->getAttribute('in');
    }
}
