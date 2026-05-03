<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

/**
 * PDF/X: The Info dictionary /Trapped key must be /True or /False.
 *
 * /Unknown is not acceptable for PDF/X conformance — the trapping
 * status must be explicitly declared for prepress workflows.
 */
final class TrappedConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $info = $inspector->getInfo();

        if ($info === null || $info->trapped === null) {
            return [new ConformanceViolation(
                clause: '6.3',
                message: 'Info /Trapped must be set to /True or /False for ' . $profile->getFamily() . ' conformance',
                severity: ViolationSeverity::Error,
                objectPath: 'Info.Trapped',
            )];
        }

        $value = $info->trapped->value;
        if ($value !== 'True' && $value !== 'False') {
            return [new ConformanceViolation(
                clause: '6.3',
                message: sprintf(
                    'Info /Trapped is /%s — must be /True or /False for %s conformance',
                    $value,
                    $profile->getFamily(),
                ),
                severity: ViolationSeverity::Error,
                objectPath: 'Info.Trapped',
            )];
        }

        return [];
    }
}
