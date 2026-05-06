<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

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
