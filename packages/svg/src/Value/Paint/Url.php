<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value\Paint;

use Phpdftk\Svg\Value\Paint;

/**
 * `url(#id) [ none | <color> ]` — a reference to a gradient, pattern, or
 * other paint server defined elsewhere in the document, with an optional
 * fallback paint used when the referent can't be resolved.
 *
 * Per SVG 2 §13.2 the fallback can only be `none` or `<color>`; a chained
 * `url(...)` is not a legal fallback, so `parse()` strips it.
 */
final class Url extends Paint
{
    public function __construct(
        public readonly string $id,
        public readonly ?Paint $fallback = null,
    ) {}
}
