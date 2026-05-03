<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

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
