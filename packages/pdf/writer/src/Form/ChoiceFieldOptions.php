<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Form;

/**
 * Options for {@see \Phpdftk\Pdf\Writer\PdfDoc::addChoiceField()}.
 *
 * `$choices` is a list of `[value, displayLabel]` pairs (or plain
 * strings, in which case the same value is used for both). `combo`
 * controls whether the field renders as a drop-down combo box (true)
 * or a scrolling list box (false).
 */
final class ChoiceFieldOptions
{
    /**
     * @param list<string|array{0:string,1:string}> $choices
     */
    public function __construct(
        public readonly array $choices,
        public readonly ?string $defaultValue = null,
        public readonly bool $combo = true,
        public readonly bool $editable = false,
        public readonly bool $sort = false,
        public readonly bool $multiSelect = false,
        public readonly bool $required = false,
        public readonly bool $readOnly = false,
    ) {}
}
