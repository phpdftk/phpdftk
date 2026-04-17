<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfArray;

/**
 * Underline annotation (/Subtype /Underline).
 */
class UnderlineAnnotation extends MarkupAnnotation
{
    public ?PdfArray $quadPoints = null; // /QuadPoints

    public function getSubtype(): string
    {
        return 'Underline';
    }

    public function toPdf(): string
    {
        $dict = $this->buildDictionary();

        if ($this->quadPoints !== null) {
            $dict->set('QuadPoints', $this->quadPoints);
        }

        return $dict->toPdf();
    }
}
