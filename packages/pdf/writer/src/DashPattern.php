<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Dash pattern for stroked paths.
 *
 * Wraps the PDF dash array + phase for use with Page::drawLine()
 * and other stroked shape methods.
 */
final class DashPattern
{
    /**
     * @param float[] $pattern Alternating on/off lengths in points (e.g. [4, 2])
     * @param float   $phase  Offset into the pattern to start at
     */
    public function __construct(
        public readonly array $pattern,
        public readonly float $phase = 0,
    ) {}

    public static function solid(): self
    {
        return new self([], 0);
    }

    public static function dashed(float $on = 4, float $off = 4): self
    {
        return new self([$on, $off]);
    }

    public static function dotted(float $dot = 1, float $gap = 2): self
    {
        return new self([$dot, $gap]);
    }

    public static function dashDot(float $dash = 6, float $dot = 1, float $gap = 2): self
    {
        return new self([$dash, $gap, $dot, $gap]);
    }

    /**
     * @return array{0: float[], 1: float} For ContentStream::setDashPattern()
     */
    public function toOperatorArgs(): array
    {
        return [$this->pattern, $this->phase];
    }
}
