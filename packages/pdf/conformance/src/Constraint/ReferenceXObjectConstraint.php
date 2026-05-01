<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfXProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * ISO 15930-8/9 (PDF/X-5): Reference XObject validation.
 *
 * PDF/X-5g, X-5pg, and X-5n profiles support external graphical content
 * via reference XObjects. When present, the FormXObject's /Ref dictionary
 * must be valid. This constraint is a no-op for non-X-5 profiles.
 */
final class ReferenceXObjectConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        // Only applies to X-5 profiles
        if (!$profile instanceof PdfXProfile || !$profile->supportsReferenceXObjects()) {
            return [];
        }

        $violations = [];

        foreach ($inspector->getReferenceXObjects() as $formXObject) {
            // /Ref is already non-null (that's how getReferenceXObjects filters),
            // but the reference must point to a valid object
            if ($formXObject->ref === null) {
                $violations[] = new ConformanceViolation(
                    clause: '6.8',
                    message: 'Reference XObject is missing required /Ref dictionary',
                    severity: ViolationSeverity::Error,
                    objectPath: 'FormXObject',
                );
            }
        }

        return $violations;
    }
}
