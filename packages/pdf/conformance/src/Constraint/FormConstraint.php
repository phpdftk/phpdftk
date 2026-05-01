<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

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
