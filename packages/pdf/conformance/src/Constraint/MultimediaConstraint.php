<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

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
