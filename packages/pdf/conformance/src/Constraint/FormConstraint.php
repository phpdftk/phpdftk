<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

/**
 * Prohibits interactive forms (AcroForm).
 *
 * Used by PDF/mail and other profiles that require non-interactive documents.
 */
final class FormConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        if ($inspector->hasInteractiveForms()) {
            return [
                new ConformanceViolation(
                    clause: '6.7',
                    message: 'Interactive forms (AcroForm) are prohibited in ' . $profile->getFamily(),
                    severity: ViolationSeverity::Error,
                    objectPath: 'Catalog.AcroForm',
                ),
            ];
        }

        return [];
    }
}
