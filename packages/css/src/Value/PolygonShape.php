<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `polygon([<fill-rule>]? <length-percentage> <length-percentage>,
 *          [<length-percentage> <length-percentage>]*)` per CSS
 * Shapes 1 §3.4. Defines an arbitrary polygon by its vertices.
 *
 * `<fill-rule>` is `nonzero` (default) or `evenodd`.
 */
final readonly class PolygonShape extends BasicShape
{
    /**
     * @param string $fillRule         'nonzero' (default) or 'evenodd'.
     * @param list<array{Value, Value}> $vertices Each entry is `[x, y]`.
     */
    public function __construct(
        public string $fillRule,
        public array $vertices,
    ) {}

    public function toCss(): string
    {
        $head = $this->fillRule === 'nonzero' ? '' : $this->fillRule . ', ';
        $verts = implode(', ', array_map(
            static fn(array $v): string => $v[0]->toCss() . ' ' . $v[1]->toCss(),
            $this->vertices,
        ));
        return 'polygon(' . $head . $verts . ')';
    }
}
