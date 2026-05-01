<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfXProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * Transparency prohibition for PDF/A-1 (clause 6.4) and PDF/X-1a/X-3.
 *
 * Only applies to profiles that prohibit transparency.
 */
final class TransparencyConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        // Check if this profile prohibits transparency
        if ($profile instanceof PdfAProfile && !$profile->prohibitsTransparency()) {
            return [];
        }
        if ($profile instanceof PdfXProfile && !$profile->prohibitsTransparency()) {
            return [];
        }

        if ($inspector->hasTransparency()) {
            return [new ConformanceViolation(
                clause: '6.4',
                message: 'Transparency (page groups with /S /Transparency) is prohibited in ' . $profile->getFamily() . '-' . $profile->getLevel(),
                severity: ViolationSeverity::Error,
            )];
        }

        return [];
    }
}
