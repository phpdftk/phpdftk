<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Annotation\ThreeDAnnotation;

/**
 * ISO 24517-1 (PDF/E-1): 3D content validation.
 *
 * Validates that 3D annotations reference valid 3D streams with a recognized
 * subtype (U3D or PRC) and at least one view definition.
 */
final class ThreeDContentConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];

        // Check 3D annotations have a /3DD reference
        foreach ($inspector->getRegisteredObjects() as $object) {
            if ($object instanceof ThreeDAnnotation && $object->dd === null) {
                $violations[] = new ConformanceViolation(
                    clause: '13.6.3',
                    message: '3D annotation is missing required /3DD stream reference',
                    severity: ViolationSeverity::Error,
                    objectPath: 'Annotation[3D]',
                );
            }
        }

        // Check 3D streams have a valid subtype and at least one view
        foreach ($inspector->getThreeDStreams() as $stream) {
            $subtype = $stream->subtype->value;
            if ($subtype !== 'U3D' && $subtype !== 'PRC') {
                $violations[] = new ConformanceViolation(
                    clause: '13.6.3',
                    message: sprintf(
                        '3D stream has invalid subtype "%s"; must be U3D or PRC',
                        $subtype,
                    ),
                    severity: ViolationSeverity::Error,
                    objectPath: 'ThreeDStream',
                );
            }

            if ($stream->va === null && $stream->dv === null) {
                $violations[] = new ConformanceViolation(
                    clause: '13.6.3',
                    message: '3D stream has no views defined (/VA or /DV required)',
                    severity: ViolationSeverity::Warning,
                    objectPath: 'ThreeDStream',
                );
            }
        }

        return $violations;
    }
}
