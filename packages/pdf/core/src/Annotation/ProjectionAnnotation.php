<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Annotation;

/**
 * Projection annotation (/Subtype /Projection).
 */
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
