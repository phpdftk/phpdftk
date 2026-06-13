<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * `display: table-column` / `display: table-column-group`. CSS Tables 3
 * §4 — a column-group box carries width / background / border
 * declarations for one or more columns; the box itself never renders.
 * BlockLayout treats a `TableColumnBox` as a layout no-op (zero
 * geometry contribution); BlockLayout's `collectColumnWidths` reads its
 * cascaded `width` to drive fixed-table-layout column distribution.
 */
final class TableColumnBox extends Box {}
