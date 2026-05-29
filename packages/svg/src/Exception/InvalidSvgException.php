<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Exception;

/**
 * Thrown when SVG input fails to parse — malformed XML, wrong root element,
 * unsupported feature (e.g. XInclude), or any value-level parse error
 * encountered while building the typed tree.
 */
final class InvalidSvgException extends \RuntimeException {}
