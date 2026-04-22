<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

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
