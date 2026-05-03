<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Squiggly annotation (/Subtype /Squiggly).
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class SquigglyAnnotation extends MarkupAnnotation
{
    public ?PdfArray $quadPoints = null; // /QuadPoints

    public function getSubtype(): string
    {
        return 'Squiggly';
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
