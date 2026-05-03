<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core;

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
