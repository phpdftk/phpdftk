<?php

declare(strict_types=1);

namespace Phpdftk\Raster\Exception;

/**
 * Umbrella exception for the raster package. Thrown for out-of-
 * bounds pixel access, buffer size mismatches, encoder failures,
 * and any other raster-specific error. Pure runtime — not a
 * subclass of `RuntimeException` directly because callers may want
 * to catch raster errors without catching every RuntimeException.
 */
class RasterException extends \RuntimeException {}
