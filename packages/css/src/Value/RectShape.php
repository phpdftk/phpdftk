<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `rect(<top> <right> <bottom> <left> [round <border-radius>]?)`
 * per CSS Shapes 2 §4.5. Defines a clip rectangle by its
 * top / right / bottom / left edges measured from the reference
 * box origin (NOT inset distances like `inset()` uses).
 *
 *   clip-path: rect(0 100% 100% 0);
 *   clip-path: rect(10px 90% 90% 10px round 5px);
 *   clip-path: rect(0 auto 100px 0);     -- `auto` keyword
 */
final readonly class RectShape extends BasicShape
{
    /**
     * @param list<Value>  $edges       Top, right, bottom, left edges.
     *                                  Each is Length / Percentage /
     *                                  Keyword('auto').
     * @param ?list<Value> $borderRadius Optional rounded-corner values.
     */
    public function __construct(
        public array $edges,
        public ?array $borderRadius = null,
    ) {}

    public function toCss(): string
    {
        $edges = implode(' ', array_map(
            static fn(Value $v): string => $v->toCss(),
            $this->edges,
        ));
        if ($this->borderRadius === null) {
            return 'rect(' . $edges . ')';
        }
        $radius = implode(' ', array_map(
            static fn(Value $v): string => $v->toCss(),
            $this->borderRadius,
        ));
        return 'rect(' . $edges . ' round ' . $radius . ')';
    }
}
