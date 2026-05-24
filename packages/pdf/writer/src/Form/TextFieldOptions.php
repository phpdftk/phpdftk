<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Form;

/**
 * Options for {@see \Phpdftk\Pdf\Writer\PdfDoc::addTextField()}.
 *
 * Sensible defaults: single-line, not required, not read-only,
 * unlimited length. The `defaultAppearance` matches what most viewers
 * expect for a Helvetica 10pt black text field.
 */
final class TextFieldOptions
{
    public function __construct(
        public readonly ?string $defaultValue = null,
        public readonly ?int $maxLength = null,
        public readonly bool $multiline = false,
        public readonly bool $password = false,
        public readonly bool $required = false,
        public readonly bool $readOnly = false,
        public readonly string $defaultAppearance = '/Helv 10 Tf 0 0 0 rg',
    ) {}
}
