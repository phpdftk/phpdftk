<?php

declare(strict_types=1);

namespace Phpdftk\Svg;

/**
 * Catch-all for SVG elements that don't yet have a dedicated typed class.
 * Stores the local name and the raw attribute bag so sanitisers and
 * format converters can still traverse the document. As Phase-3 work
 * lands typed classes for individual elements (circle, ellipse, path,
 * group, text, …), this becomes the home only for genuinely unknown
 * extension elements.
 */
final class GenericElement extends Element {}
