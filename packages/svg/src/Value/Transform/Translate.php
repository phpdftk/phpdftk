<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Transform;

use Phpdftk\Svg\Value\TransformFunction;

/**
 * `translate(tx)` or `translate(tx, ty)`. `ty` defaults to 0 per SVG 2 §8.4.
 */
final class Translate implements TransformFunction
{
    public function __construct(
        public readonly float $tx,
        public readonly float $ty = 0.0,
    ) {}

    public function toMatrix(): array
    {
        return [1.0, 0.0, 0.0, 1.0, $this->tx, $this->ty];
    }
}
