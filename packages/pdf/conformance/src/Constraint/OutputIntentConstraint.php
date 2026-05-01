<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
/**
 * PDF/A clause 6.2.2: At least one OutputIntent with the correct
 * subtype and an embedded ICC profile is required.
 */
final class OutputIntentConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];

        if (!$inspector->hasOutputIntents()) {
            $violations[] = new ConformanceViolation(
                clause: '6.2.2',
                message: 'At least one OutputIntent is required for ' . $profile->getFamily() . '-' . $profile->getLevel(),
                severity: ViolationSeverity::Error,
            );
            return $violations;
        }

        if (!$inspector->hasOutputIntentWithIccProfile()) {
            $violations[] = new ConformanceViolation(
                clause: '6.2.2',
                message: 'OutputIntent must include a /DestOutputProfile (embedded ICC profile)',
                severity: ViolationSeverity::Error,
            );
        }

        return $violations;
    }
}
