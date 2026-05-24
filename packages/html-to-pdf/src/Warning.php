<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf;

/**
 * One diagnostic event from the renderer. Collected in {@see RenderResult}
 * after a `render()` / `renderInto()` call. In `strict` mode warnings of
 * severity `Error` instead throw a {@see StrictModeException}.
 *
 * `context` is a free-form bag of relevant key-values (e.g. the offending
 * CSS property name, the missing font's family, the failed URL). Keys are
 * stable for a given code so log aggregators can dispatch off them.
 */
final readonly class Warning
{
    /** @param array<string, scalar|null> $context */
    public function __construct(
        public WarningCode $code,
        public string $message,
        public WarningSeverity $severity = WarningSeverity::Warning,
        public array $context = [],
    ) {}
}
