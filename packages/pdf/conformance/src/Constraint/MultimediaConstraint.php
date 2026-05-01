<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * Prohibits multimedia content (movies, sounds, renditions, rich media).
 *
 * Used by PDF/mail and other profiles that require static documents.
 */
final class MultimediaConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        if ($inspector->hasMultimediaContent()) {
            return [
                new ConformanceViolation(
                    clause: '6.8',
                    message: 'Multimedia content is prohibited in ' . $profile->getFamily(),
                    severity: ViolationSeverity::Error,
                    objectPath: 'Document',
                ),
            ];
        }

        return [];
    }
}
