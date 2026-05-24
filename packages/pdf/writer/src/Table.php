<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

/**
 * Tabular data placed via `Pdf::addTable()` (flow) or
 * `Writer\Page::drawTable()` (positioned).
 *
 * Cells are plain strings; multi-line content is produced
 * automatically by greedy word-wrapping at the column width.
 * For richer content (mixed fonts, images, nested tables), drop to
 * the writer's drawing API.
 *
 * Column widths are absolute points; if `$columnWidths` is null, the
 * renderer divides the available width equally across columns.
 */
final class Table
{
    /**
     * @param list<list<string>> $rows          Body rows; each row's length should match column count.
     * @param list<float>|null    $columnWidths Per-column width in points, or null for equal columns.
     * @param list<string>|null   $headerRow    Optional repeating header row.
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?array $columnWidths = null,
        public readonly ?array $headerRow = null,
    ) {}

    /**
     * Number of columns in the table — taken from the header row when
     * present, otherwise from the widest body row.
     */
    public function columnCount(): int
    {
        if ($this->headerRow !== null) {
            return count($this->headerRow);
        }
        $max = 0;
        foreach ($this->rows as $row) {
            $max = max($max, count($row));
        }
        return $max;
    }
}
