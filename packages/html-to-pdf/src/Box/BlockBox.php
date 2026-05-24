<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Box;

/**
 * A box that participates in a block formatting context — `display: block`,
 * `list-item`, the principal box of a block-level replaced element, etc.
 *
 * Block boxes stack vertically and establish a BFC for their children when
 * required (e.g. `overflow: hidden`, floats, abs-pos roots).
 */
final class BlockBox extends Box {}
