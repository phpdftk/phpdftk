<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * ISO 23504-1 (PDF/R-1): Raster content validation.
 *
 * PDF/R-1 documents are intended for raster-only (scanned) content.
 * This constraint warns if the document appears to contain non-raster
 * content such as text or vector graphics (detected heuristically via
 * font presence).
 */
final class RasterContentConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        if ($inspector->hasRasterOnlyContent()) {
            return [];
        }

        return [
            new ConformanceViolation(
                clause: '6.1',
                message: 'PDF/R-1 documents should contain raster-only content; text or vector content detected',
                severity: ViolationSeverity::Warning,
                objectPath: 'Document',
            ),
        ];
    }
}
