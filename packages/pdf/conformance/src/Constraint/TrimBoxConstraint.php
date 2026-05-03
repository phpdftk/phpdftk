<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

/**
 * PDF/X: All pages must have /TrimBox (or /ArtBox as fallback).
 *
 * The TrimBox defines the intended finished dimensions of the printed
 * page after trimming. This is mandatory for all PDF/X levels.
 */
final class TrimBoxConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];
        $pageIndex = 0;

        foreach ($inspector->getPages() as $page) {
            if ($page->trimBox === null && $page->artBox === null) {
                $violations[] = new ConformanceViolation(
                    clause: '6.2',
                    message: sprintf(
                        'Page %d must have /TrimBox (or /ArtBox) for %s conformance',
                        $pageIndex,
                        $profile->getFamily() . '-' . $profile->getLevel(),
                    ),
                    severity: ViolationSeverity::Error,
                    objectPath: "Page[{$pageIndex}].TrimBox",
                );
            }
            $pageIndex++;
        }

        return $violations;
    }
}
