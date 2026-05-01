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
 * ISO 23504-1 (PDF/R-1): Action restrictions.
 *
 * PDF/R-1 prohibits JavaScript and Launch actions.
 */
final class PdfRActionConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];

        foreach ($inspector->getRegisteredObjects() as $object) {
            if ($object instanceof JavaScriptAction) {
                $violations[] = new ConformanceViolation(
                    clause: '6.6',
                    message: 'JavaScript actions are prohibited in PDF/R-1',
                    severity: ViolationSeverity::Error,
                    objectPath: 'Action[JavaScript]',
                );
            }

            if ($object instanceof LaunchAction) {
                $violations[] = new ConformanceViolation(
                    clause: '6.6',
                    message: 'Launch actions are prohibited in PDF/R-1',
                    severity: ViolationSeverity::Error,
                    objectPath: 'Action[Launch]',
                );
            }
        }

        return $violations;
    }
}
