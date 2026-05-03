<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core;

/**
 * Represents a PDF numeric object (integer or real number).
 */
class PdfNumber implements Serializable
{
    public function __construct(public readonly int|float $value)
    {
    }

    public function toPdf(): string
    {
        if (is_int($this->value)) {
            return (string) $this->value;
        }

        // Format float without trailing zeros but with enough precision.
        // PDF reals should not use scientific notation.
        $formatted = rtrim(rtrim(sprintf('%.6f', $this->value), '0'), '.');
        // Edge case: -0 should be 0
        if ($formatted === '-0' || $formatted === '') {
            $formatted = '0';
        }

        return $formatted;
    }
}
