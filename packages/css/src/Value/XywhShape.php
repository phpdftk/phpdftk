<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * `xywh(<x> <y> <width> <height> [round <border-radius>]?)` per
 * CSS Shapes 2 §4.6. Defines a clip rectangle by its origin +
 * size — easier than `rect()`'s edge-distance form when you know
 * the actual rectangle dimensions.
 *
 *   clip-path: xywh(0 0 100% 100%);
 *   clip-path: xywh(10px 10px 90% 80% round 5px);
 */
final readonly class XywhShape extends BasicShape
{
    /**
     * @param ?list<Value> $borderRadius Optional rounded-corner values.
     */
    public function __construct(
        public Value $x,
        public Value $y,
        public Value $width,
        public Value $height,
        public ?array $borderRadius = null,
    ) {}

    public function toCss(): string
    {
        $parts = sprintf(
            '%s %s %s %s',
            $this->x->toCss(),
            $this->y->toCss(),
            $this->width->toCss(),
            $this->height->toCss(),
        );
        if ($this->borderRadius === null) {
            return 'xywh(' . $parts . ')';
        }
        $radius = implode(' ', array_map(
            static fn(Value $v): string => $v->toCss(),
            $this->borderRadius,
        ));
        return 'xywh(' . $parts . ' round ' . $radius . ')';
    }
}
