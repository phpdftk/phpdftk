<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Form;

/**
 * Options for {@see \Phpdftk\Pdf\Writer\PdfDoc::addCheckbox()}.
 *
 * `onValue` is the export value emitted when the checkbox is ticked
 * (PDF convention: `Yes`). `defaultChecked` controls the initial state.
 */
final class CheckboxOptions
{
    public function __construct(
        public readonly string $onValue = 'Yes',
        public readonly bool $defaultChecked = false,
        public readonly bool $required = false,
        public readonly bool $readOnly = false,
    ) {}
}
