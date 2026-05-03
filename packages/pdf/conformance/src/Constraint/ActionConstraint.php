<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\Action\JavaScriptAction;
use Phpdftk\Pdf\Core\Action\LaunchAction;
use Phpdftk\Pdf\Core\Action\MovieAction;
use Phpdftk\Pdf\Core\Action\SoundAction;
use Phpdftk\Pdf\Core\Action\RichMediaExecuteAction;
use Phpdftk\Pdf\Core\Action\RenditionAction;

/**
 * PDF/A clause 6.5 / 6.6.1: Restricted action types.
 *
 * All PDF/A levels prohibit:
 *   - JavaScript actions
 *   - Launch actions (external application execution)
 *
 * PDF/A-1 additionally prohibits:
 *   - Movie actions
 *   - Sound actions
 *   - Rendition actions
 *   - RichMediaExecute actions
 */
final class ActionConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];

        foreach ($inspector->getRegisteredObjects() as $object) {
            if ($object instanceof JavaScriptAction) {
                $violations[] = new ConformanceViolation(
                    clause: '6.6.1',
                    message: 'JavaScript actions are prohibited in ' . $profile->getFamily(),
                    severity: ViolationSeverity::Error,
                    objectPath: 'Action[JavaScript]',
                );
            }

            if ($object instanceof LaunchAction) {
                $violations[] = new ConformanceViolation(
                    clause: '6.6.1',
                    message: 'Launch actions are prohibited in ' . $profile->getFamily(),
                    severity: ViolationSeverity::Error,
                    objectPath: 'Action[Launch]',
                );
            }

            // PDF/A-1 is stricter about multimedia actions
            if ($profile instanceof \Phpdftk\Pdf\Conformance\Profile\PdfAProfile
                && $profile->getPart() === 1
            ) {
                if ($object instanceof MovieAction) {
                    $violations[] = new ConformanceViolation(
                        clause: '6.5',
                        message: 'Movie actions are prohibited in PDF/A-1',
                        severity: ViolationSeverity::Error,
                        objectPath: 'Action[Movie]',
                    );
                }
                if ($object instanceof SoundAction) {
                    $violations[] = new ConformanceViolation(
                        clause: '6.5',
                        message: 'Sound actions are prohibited in PDF/A-1',
                        severity: ViolationSeverity::Error,
                        objectPath: 'Action[Sound]',
                    );
                }
                if ($object instanceof RenditionAction) {
                    $violations[] = new ConformanceViolation(
                        clause: '6.5',
                        message: 'Rendition actions are prohibited in PDF/A-1',
                        severity: ViolationSeverity::Error,
                        objectPath: 'Action[Rendition]',
                    );
                }
                if ($object instanceof RichMediaExecuteAction) {
                    $violations[] = new ConformanceViolation(
                        clause: '6.5',
                        message: 'RichMediaExecute actions are prohibited in PDF/A-1',
                        severity: ViolationSeverity::Error,
                        objectPath: 'Action[RichMediaExecute]',
                    );
                }
            }
        }

        return $violations;
    }
}
