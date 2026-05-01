<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * PDF/UA-1 clause 7.5: Every page that contains annotations must
 * have /Tabs set to /S (structure order).
 *
 * This ensures that keyboard tab order follows the logical structure
 * tree rather than visual layout, which is critical for screen readers.
 */
final class TabOrderConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];
        $pageIndex = 0;

        foreach ($inspector->getPages() as $page) {
            // Only require /Tabs on pages that have annotations
            $hasAnnotations = !empty($page->annots);

            if ($hasAnnotations && ($page->tabs === null || $page->tabs->value !== 'S')) {
                $violations[] = new ConformanceViolation(
                    clause: '7.5',
                    message: sprintf(
                        'Page %d has annotations but /Tabs is not set to /S (structure order)',
                        $pageIndex,
                    ),
                    severity: ViolationSeverity::Error,
                    objectPath: "Page[{$pageIndex}].Tabs",
                );
            }
            $pageIndex++;
        }

        return $violations;
    }
}
