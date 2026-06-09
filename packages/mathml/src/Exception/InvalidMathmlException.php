<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Exception;

/**
 * Raised by {@see \Phpdftk\Mathml\Parser} when the input isn't a
 * well-formed MathML Core document — wrong root, malformed XML,
 * non-MathML namespace on the root, etc.
 */
final class InvalidMathmlException extends \RuntimeException {}
