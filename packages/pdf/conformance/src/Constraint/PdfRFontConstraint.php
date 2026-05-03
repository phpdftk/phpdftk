<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

/**
 * ISO 23504-1 (PDF/R-1): Font presence warning.
 *
 * PDF/R-1 documents are intended for raster-only content. The presence
 * of fonts suggests non-raster content, which is a conformance warning.
 */
final class PdfRFontConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        foreach ($inspector->getFonts() as $_) {
            return [
                new ConformanceViolation(
                    clause: '6.3',
                    message: 'PDF/R-1 documents should not contain fonts; raster-only content expected',
                    severity: ViolationSeverity::Warning,
                    objectPath: 'Document.Fonts',
                ),
            ];
        }

        return [];
    }
}
