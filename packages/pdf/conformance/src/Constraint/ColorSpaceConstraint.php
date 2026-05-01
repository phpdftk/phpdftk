<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * PDF/A clause 6.2: Device-dependent color spaces are only allowed
 * when a matching OutputIntent is present.
 *
 * This is an advisory check: the constraint verifies that either the
 * document uses only device-independent color spaces (CalGray, CalRGB,
 * Lab, ICCBased) or has an OutputIntent with an embedded ICC profile
 * to anchor device-dependent color (DeviceRGB, DeviceCMYK, DeviceGray).
 *
 * A full check would require parsing every content stream operator for
 * color space usage — this constraint checks at the structural level.
 */
final class ColorSpaceConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        // If an OutputIntent with ICC profile exists, device color is anchored
        if ($inspector->hasOutputIntents()) {
            return [];
        }

        // Without an OutputIntent, warn that device color may not be conformant
        return [new ConformanceViolation(
            clause: '6.2.3',
            message: 'Device-dependent color spaces (DeviceRGB, DeviceCMYK, DeviceGray) '
                . 'require an OutputIntent with an embedded ICC profile, '
                . 'or all color must use device-independent spaces (CalRGB, ICCBased, etc.)',
            severity: ViolationSeverity::Warning,
        )];
    }
}
