<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf;

/**
 * Thrown when {@see RendererOptions::$strict} is true and the renderer
 * encounters a condition that would otherwise emit a {@see Warning} of
 * `Error` severity. The originating warning is available on the
 * exception via `getWarning()`.
 */
final class StrictModeException extends \RuntimeException
{
    public function __construct(public readonly Warning $warning)
    {
        parent::__construct($warning->message);
    }
}
