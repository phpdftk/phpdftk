<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core;

/**
 * Represents the PDF null object.
 */
class PdfNull implements Serializable
{
    public function toPdf(): string
    {
        return 'null';
    }
}
