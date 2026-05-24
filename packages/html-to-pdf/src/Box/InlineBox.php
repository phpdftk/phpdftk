<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * A box for `display: inline` content — children are laid out along the
 * inline axis into line boxes during layout. Holds styling that applies to
 * the run (color, font, decoration) but no block-level box edges.
 */
final class InlineBox extends Box {}
