<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * PDF/VT: Catalog must have a /DPartRoot reference.
 *
 * The DPartRoot defines the document-part hierarchy used for
 * variable-data printing workflows. This is the key structural
 * requirement that distinguishes PDF/VT from plain PDF/X-4.
 */
final class DPartRootConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $catalog = $inspector->getCatalog();

        if ($catalog->dPartRoot === null) {
            return [new ConformanceViolation(
                clause: '6.1',
                message: 'Catalog /DPartRoot is required for ' . $profile->getFamily() . '-' . $profile->getLevel() . ' conformance',
                severity: ViolationSeverity::Error,
                objectPath: 'Catalog.DPartRoot',
            )];
        }

        return [];
    }
}
