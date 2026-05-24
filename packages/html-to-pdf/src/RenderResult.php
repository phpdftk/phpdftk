<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf;

use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Return value of {@see Renderer::render}: the populated `PdfWriter` plus
 * the list of warnings emitted during rendering. `hasErrors()` reports
 * whether any warning is `Error` severity (only possible in lenient mode;
 * strict mode would have thrown instead).
 */
final readonly class RenderResult
{
    /** @param list<Warning> $warnings */
    public function __construct(
        public PdfWriter $writer,
        public array $warnings,
    ) {}

    public function hasErrors(): bool
    {
        foreach ($this->warnings as $w) {
            if ($w->severity === WarningSeverity::Error) {
                return true;
            }
        }
        return false;
    }
}
