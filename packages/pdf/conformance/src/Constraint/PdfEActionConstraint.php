<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Action\JavaScriptAction;
use ApprLabs\Pdf\Core\Action\LaunchAction;

/**
 * ISO 24517-1 (PDF/E-1): Action restrictions.
 *
 * PDF/E-1 prohibits JavaScript and Launch actions. GoTo, URI, GoToR,
 * and GoToE actions are permitted.
 */
final class PdfEActionConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];

        foreach ($inspector->getRegisteredObjects() as $object) {
            if ($object instanceof JavaScriptAction) {
                $violations[] = new ConformanceViolation(
                    clause: '6.6',
                    message: 'JavaScript actions are prohibited in PDF/E-1',
                    severity: ViolationSeverity::Error,
                    objectPath: 'Action[JavaScript]',
                );
            }

            if ($object instanceof LaunchAction) {
                $violations[] = new ConformanceViolation(
                    clause: '6.6',
                    message: 'Launch actions are prohibited in PDF/E-1',
                    severity: ViolationSeverity::Error,
                    objectPath: 'Action[Launch]',
                );
            }
        }

        return $violations;
    }
}
