<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Filter;

/**
 * SVG 2 Filter Effects §15.7 — `<feFlood flood-color flood-opacity>`.
 * Produces a solid-colour rectangle filling the primitive subregion.
 * Together with feComposite this implements paint-source generation.
 */
final class FeFlood extends FilterPrimitive
{
    public function __construct()
    {
        parent::__construct('feFlood');
    }

    /**
     * `flood-color` accepts any CSS colour name / hex / rgb / etc.
     * The raw string is returned; the consumer parses it via the
     * shared CSS colour parser.
     */
    public function floodColor(): string
    {
        return $this->getAttribute('flood-color') ?? 'black';
    }

    public function floodOpacity(): float
    {
        $v = $this->getAttribute('flood-opacity');
        if ($v === null) {
            return 1.0;
        }
        return max(0.0, min(1.0, (float) $v));
    }
}
