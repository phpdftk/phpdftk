<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Profile\ZugferdProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

/**
 * PDF/A-1 clause 6.9 / PDF/A-2 clause 6.10: Embedded files are prohibited.
 * PDF/A-3 (ISO 19005-3): Embedded files are allowed (associated via /AF).
 *
 * This constraint checks for the presence of embedded files via the
 * Catalog /Names dictionary. PDF/A-3+ allows them, so the constraint
 * is skipped for those profiles. ZUGFeRD/Factur-X profiles are based on
 * PDF/A-3 and require embedded files.
 */
final class EmbeddedFileConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        // PDF/A-3+ allows embedded files
        if ($profile instanceof PdfAProfile && $profile->allowsEmbeddedFiles()) {
            return [];
        }

        // ZUGFeRD/Factur-X is based on PDF/A-3 which allows embedded files
        if ($profile instanceof ZugferdProfile) {
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
