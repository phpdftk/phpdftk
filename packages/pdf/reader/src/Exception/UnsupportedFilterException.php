<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Exception;

/**
 * Thrown when a stream uses a filter the reader cannot decode
 * (e.g. JBIG2 without the jbig2dec extension, or an unrecognized
 * custom filter name).
 */
final class UnsupportedFilterException extends \RuntimeException
{
}
