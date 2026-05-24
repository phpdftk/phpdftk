<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * `display: table` — block-level wrapper around row groups / rows / cells.
 * Phase-1 layout treats it as a block that hosts one or more
 * {@see TableRowBox}es; CSS Tables 3 §3 automatic column-width / table-
 * caption / multi-section layout lands in a follow-up.
 */
final class TableBox extends Box {}
