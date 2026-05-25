<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * A box that establishes a flex formatting context — `display: flex`
 * (block-level) per CSS Flexible Box Layout 1 §3.
 *
 * Phase-1 subset: row direction only, single line (no wrapping), all
 * items get their declared `width`. `flex-grow` / `flex-shrink` /
 * `flex-basis` aren't yet honoured for size distribution.
 */
final class FlexBox extends Box {}
