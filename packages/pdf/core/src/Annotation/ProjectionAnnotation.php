<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Annotation;

use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Projection annotation (/Subtype /Projection).
 */
#[RequiresPdfVersion(PdfVersion::V2_0)]
class ProjectionAnnotation extends Annotation
{
    public function getSubtype(): string
    {
        return 'Projection';
    }

    public function toPdf(): string
    {
        return $this->buildDictionary()->toPdf();
    }
}
