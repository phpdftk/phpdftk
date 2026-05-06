<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Document-wide styling defaults for the high-level {@see Pdf} builder.
 *
 * Holds the page margins, body-text defaults (font, size, color, line
 * height, paragraph spacing) and per-level heading styles (H1–H6).
 * Individual {@see Pdf::addText()} calls can override parts of the
 * theme via {@see TextStyle}; headings inherit from the theme's
 * heading map.
 *
 * Themes are immutable. Use the `with*` helpers to derive a modified
 * theme without mutating the original.
 */
final class Theme
{
    /**
     * @param float $margin                 uniform page margin (points)
     * @param string $family                body font family (Helvetica / Times / Courier)
     * @param float $fontSize               body size
     * @param array{float,float,float} $color body RGB (0–1)
     * @param float $lineHeight             multiplier of font size
     * @param float $paragraphSpacing       gap between paragraphs (points)
     * @param array<int, array{size: float, bold: bool, spaceAbove: float, spaceBelow: float}> $headings
     *        map from level (1..6) to heading style
     */
    public function __construct(
        public readonly float $margin = 72.0,
        public readonly string $family = 'Helvetica',
        public readonly float $fontSize = 11.0,
        public readonly array $color = [0.0, 0.0, 0.0],
        public readonly float $lineHeight = 1.2,
        public readonly float $paragraphSpacing = 6.0,
        public readonly array $headings = [
            1 => ['size' => 24.0, 'bold' => true, 'spaceAbove' => 18.0, 'spaceBelow' => 10.0],
            2 => ['size' => 20.0, 'bold' => true, 'spaceAbove' => 14.0, 'spaceBelow' => 8.0],
            3 => ['size' => 16.0, 'bold' => true, 'spaceAbove' => 12.0, 'spaceBelow' => 6.0],
            4 => ['size' => 14.0, 'bold' => true, 'spaceAbove' => 10.0, 'spaceBelow' => 5.0],
            5 => ['size' => 12.0, 'bold' => true, 'spaceAbove' => 8.0,  'spaceBelow' => 4.0],
            6 => ['size' => 11.0, 'bold' => true, 'spaceAbove' => 6.0,  'spaceBelow' => 3.0],
        ],
    ) {}

    public function withFont(string $family, float $size): self
    {
        return new self(
            margin: $this->margin,
            family: $family,
            fontSize: $size,
            color: $this->color,
            lineHeight: $this->lineHeight,
            paragraphSpacing: $this->paragraphSpacing,
            headings: $this->headings,
        );
    }

    /** @param array{float,float,float} $color */
    public function withColor(array $color): self
    {
        return new self(
            margin: $this->margin,
            family: $this->family,
            fontSize: $this->fontSize,
            color: $color,
            lineHeight: $this->lineHeight,
            paragraphSpacing: $this->paragraphSpacing,
            headings: $this->headings,
        );
    }

    public function withMargin(float $margin): self
    {
        return new self(
            margin: $margin,
            family: $this->family,
            fontSize: $this->fontSize,
            color: $this->color,
            lineHeight: $this->lineHeight,
            paragraphSpacing: $this->paragraphSpacing,
            headings: $this->headings,
        );
    }

    /**
     * @return array{size: float, bold: bool, spaceAbove: float, spaceBelow: float}
     */
    public function heading(int $level): array
    {
        if (!isset($this->headings[$level])) {
            throw new \InvalidArgumentException("No heading style for level $level (expected 1–6)");
        }
        return $this->headings[$level];
    }
}
