<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * PDF/A-1 clause 6.9 / PDF/A-2 clause 6.10: Embedded files are prohibited.
 * PDF/A-3 (ISO 19005-3): Embedded files are allowed (associated via /AF).
 *
 * This constraint checks for the presence of embedded files via the
 * Catalog /Names dictionary. PDF/A-3+ allows them, so the constraint
 * is skipped for those profiles.
 */
final class EmbeddedFileConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        // PDF/A-3+ allows embedded files
        if ($profile instanceof PdfAProfile && $profile->allowsEmbeddedFiles()) {
            return [];
        }

        if ($inspector->hasEmbeddedFiles()) {
            $clause = $profile instanceof PdfAProfile && $profile->getPart() === 1
                ? '6.9'
                : '6.10';

            return [new ConformanceViolation(
                clause: $clause,
                message: 'Embedded files are prohibited in ' . $profile->getFamily() . '-' . $profile->getLevel(),
                severity: ViolationSeverity::Error,
            )];
        }

        return [];
    }
}
