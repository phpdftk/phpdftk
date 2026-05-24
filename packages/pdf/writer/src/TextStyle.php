<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Per-call text styling override for {@see Pdf::addText()}.
 *
 * Every property is nullable; null means "inherit from the document
 * theme". Pass a TextStyle to override font / size / color / weight /
 * alignment for a single `addText` call without touching the document's
 * default font state.
 *
 * Example:
 *   $pdf->addText('Normal body text.');
 *   $pdf->addText('Red emphasis.', new TextStyle(color: [1, 0, 0]));
 */
final class TextStyle
{
    /**
     * @param string|null              $family    e.g. 'Helvetica', 'Times', 'Courier'
     * @param float|null               $size      point size
     * @param bool|null                $bold
     * @param bool|null                $italic
     * @param array{float,float,float}|null $color RGB 0–1
     * @param Alignment|null           $alignment
     * @param string|null              $link      URI; when set, the rendered text becomes a clickable link.
     * @param bool                     $underline Draw a horizontal line below the baseline of each rendered line.
     * @param bool                     $strikethrough Draw a horizontal line through the middle of each rendered line.
     */
    public function __construct(
        public readonly ?string $family = null,
        public readonly ?float $size = null,
        public readonly ?bool $bold = null,
        public readonly ?bool $italic = null,
        public readonly ?array $color = null,
        public readonly ?Alignment $alignment = null,
        public readonly ?string $link = null,
        public readonly bool $underline = false,
        public readonly bool $strikethrough = false,
    ) {}
}
