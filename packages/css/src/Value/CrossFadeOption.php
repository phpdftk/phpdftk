<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * One `[<percentage>? <image>]` entry inside {@see CrossFade}.
 * The percentage is optional; the renderer distributes the
 * remaining weight equally across unlabeled entries.
 */
final readonly class CrossFadeOption
{
    public function __construct(
        public Value $image,
        public ?float $percent = null,
    ) {}

    public function toCss(): string
    {
        if ($this->percent !== null) {
            return rtrim(rtrim(sprintf('%.4f', $this->percent), '0'), '.') . '% ' . $this->image->toCss();
        }
        return $this->image->toCss();
    }
}
