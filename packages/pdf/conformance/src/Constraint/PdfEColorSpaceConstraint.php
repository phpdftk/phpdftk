<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * ISO 24517-1 (PDF/E-1): Color space validation.
 *
 * Device-dependent color spaces (DeviceRGB, DeviceCMYK, DeviceGray) should
 * be anchored by an OutputIntent with an ICC profile, or device-independent
 * color spaces should be used instead.
 */
final class PdfEColorSpaceConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        // If an OutputIntent with ICC profile exists, device colors are anchored
        if ($inspector->hasOutputIntentWithIccProfile()) {
            return [];
        }

        // Check whether device-dependent color spaces are used without an OutputIntent
        if (!$inspector->hasOutputIntents()) {
            return [
                new ConformanceViolation(
                    clause: '6.2.2',
                    message: 'PDF/E-1 documents using device-dependent color spaces should include an OutputIntent with ICC profile',
                    severity: ViolationSeverity::Warning,
                    objectPath: 'Catalog.OutputIntents',
                ),
            ];
        }

        return [];
    }
}
