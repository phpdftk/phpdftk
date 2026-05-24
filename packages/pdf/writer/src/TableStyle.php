<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Styling for {@see Table} rendering. All fields have sensible defaults
 * so `new TableStyle()` produces a readable table.
 *
 * The body font / size are inherited from the surrounding `Pdf`
 * context (or from `Theme` for `Writer\Page::drawTable()`). To override
 * locally, pass a per-call style.
 */
final class TableStyle
{
    /**
     * @param float                        $cellPadding     Inside each cell, in points.
     * @param array{float,float,float}     $borderColor     RGB 0-1; only used when `$borderWidth > 0`.
     * @param float                        $borderWidth     0 disables borders entirely.
     * @param array{float,float,float}|null $headerBgColor  Header row fill; null = no header background.
     * @param bool                         $headerBold      Use the bold font variant for the header row.
     * @param list<Alignment>              $cellAlignments  Per-column alignment; missing entries default to Left.
     */
    public function __construct(
        public readonly float $cellPadding = 4.0,
        public readonly array $borderColor = [0.7, 0.7, 0.7],
        public readonly float $borderWidth = 0.5,
        public readonly ?array $headerBgColor = [0.93, 0.93, 0.93],
        public readonly bool $headerBold = true,
        public readonly array $cellAlignments = [],
    ) {}

    public function alignmentFor(int $columnIndex): Alignment
    {
        return $this->cellAlignments[$columnIndex] ?? Alignment::Left;
    }
}
