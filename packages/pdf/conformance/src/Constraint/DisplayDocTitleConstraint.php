<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Document\ViewerPreferences;

/**
 * PDF/UA-1 clause 7.18.1: ViewerPreferences /DisplayDocTitle must be true.
 *
 * The document title (from /Info /Title or XMP dc:title) must be
 * displayed in the viewer title bar rather than the filename.
 */
final class DisplayDocTitleConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        // Check registered objects for a ViewerPreferences with displayDocTitle
        $found = false;
        foreach ($inspector->getRegisteredObjects() as $object) {
            if ($object instanceof ViewerPreferences) {
                if ($object->displayDocTitle === true) {
                    return [];
                }
                $found = true;
            }
        }

        // Also check if Catalog has viewerPreferences as inline dict
        $catalog = $inspector->getCatalog();
        if ($catalog->viewerPreferences !== null && !$found) {
            // Inline PdfDictionary — check for DisplayDocTitle key
            if ($catalog->viewerPreferences->has('DisplayDocTitle')) {
                $val = $catalog->viewerPreferences->get('DisplayDocTitle');
                if ($val instanceof \ApprLabs\Pdf\Core\PdfBoolean && $val->value === true) {
                    return [];
                }
            }
        }

        return [new ConformanceViolation(
            clause: '7.18.1',
            message: 'ViewerPreferences /DisplayDocTitle must be true — the document title must appear in the viewer title bar',
            severity: ViolationSeverity::Error,
            objectPath: 'Catalog.ViewerPreferences.DisplayDocTitle',
        )];
    }
}
