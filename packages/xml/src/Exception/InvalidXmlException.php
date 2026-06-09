<?php

declare(strict_types=1);

namespace Phpdftk\Xml\Exception;

/**
 * Thrown by {@see \Phpdftk\Xml\HardenedLoader::load()} when the input
 * isn't well-formed XML.
 *
 * Format-specific parsers (`Phpdftk\Svg\Parser`, `Phpdftk\Mathml\Parser`)
 * catch this and re-throw with their own format-specific exception so
 * the original cause is preserved but the type matches the consumer's
 * expectations.
 */
final class InvalidXmlException extends \RuntimeException {}
