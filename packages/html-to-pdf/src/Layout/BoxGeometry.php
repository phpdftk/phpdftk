<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Layout;

/**
 * Resolved geometry for a box after layout: the content rectangle plus
 * the surrounding margin / border / padding edges.
 *
 * Coordinates are in PDF user-space units (1pt = 1/72in), with the
 * top-left of the page box at (0, 0) and Y increasing downward — layout's
 * own convention. The painter flips Y when emitting to PDF's bottom-left
 * coordinate system.
 *
 * `x` / `y` mark the top-left of the **content** area. `width` / `height`
 * are the content-box dimensions. The other fields are the four edges of
 * margin, border, and padding, in CSS order (top, right, bottom, left).
 */
final class BoxGeometry
{
    public float $x = 0.0;
    public float $y = 0.0;
    public float $width = 0.0;
    public float $height = 0.0;
    public float $marginTop = 0.0;
    public float $marginRight = 0.0;
    public float $marginBottom = 0.0;
    public float $marginLeft = 0.0;
    public float $borderTop = 0.0;
    public float $borderRight = 0.0;
    public float $borderBottom = 0.0;
    public float $borderLeft = 0.0;
    public float $paddingTop = 0.0;
    public float $paddingRight = 0.0;
    public float $paddingBottom = 0.0;
    public float $paddingLeft = 0.0;

    /** Total horizontal space occupied by the box, including margins / borders / padding. */
    public function outerWidth(): float
    {
        return $this->marginLeft + $this->borderLeft + $this->paddingLeft
            + $this->width
            + $this->paddingRight + $this->borderRight + $this->marginRight;
    }

    public function outerHeight(): float
    {
        return $this->marginTop + $this->borderTop + $this->paddingTop
            + $this->height
            + $this->paddingBottom + $this->borderBottom + $this->marginBottom;
    }
}
