<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * `display: table-cell`. Behaves like a block box but its width / x
 * position are set by the parent {@see TableRowBox} during layout.
 */
final class TableCellBox extends Box {}
