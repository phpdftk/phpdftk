<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core;

/**
 * Represents a PDF boolean object.
 */
class PdfBoolean implements Serializable
{
    public function __construct(public readonly bool $value)
    {
    }

    public function toPdf(): string
    {
        return $this->value ? 'true' : 'false';
    }
}
