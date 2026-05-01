<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Conformance\Profile\PdfUaProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * Tagged structure requirements for PDF/A Level A and PDF/UA.
 *
 * PDF/A-1a/2a/3a (ISO 19005 clause 6.8):
 *   - MarkInfo /Marked must be true
 *   - StructTreeRoot must be present
 *   - Catalog /Lang must be set
 *
 * PDF/UA-1 (ISO 14289-1 clause 7):
 *   - Same base requirements as PDF/A Level A
 */
final class TaggedStructureConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        // PDF/UA always requires tagged structure
        // PDF/A only requires it for Level A
        if ($profile instanceof PdfAProfile && !$profile->requiresTaggedStructure()) {
            return [];
        }

        $isPdfUa = $profile instanceof PdfUaProfile;
        $violations = [];
        $catalog = $inspector->getCatalog();

        // MarkInfo /Marked must be true
        if ($catalog->markInfo === null || $catalog->markInfo->marked !== true) {
            $violations[] = new ConformanceViolation(
                clause: $isPdfUa ? '7.1' : '6.8.1',
                message: 'MarkInfo /Marked must be true — all content must be tagged',
                severity: ViolationSeverity::Error,
                objectPath: 'Catalog.MarkInfo',
            );
        }

        // StructTreeRoot must be present
        if ($catalog->structTreeRoot === null) {
            $violations[] = new ConformanceViolation(
                clause: $isPdfUa ? '7.1' : '6.8.2',
                message: 'StructTreeRoot is required — all content must be in the structure tree',
                severity: ViolationSeverity::Error,
                objectPath: 'Catalog.StructTreeRoot',
            );
        }

        // Document language must be set
        if ($catalog->lang === null) {
            $violations[] = new ConformanceViolation(
                clause: $isPdfUa ? '7.2' : '6.8.4',
                message: 'Catalog /Lang is required — document must specify its natural language',
                severity: ViolationSeverity::Error,
                objectPath: 'Catalog.Lang',
            );
        }

        return $violations;
    }
}
