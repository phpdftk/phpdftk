<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Profile\PdfAProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Pdf\Core\PdfStream;

/**
 * PDF/A-1 clause 6.8: LZWDecode filter is prohibited.
 */
final class FilterConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        // LZWDecode is only prohibited in PDF/A-1
        if ($profile instanceof PdfAProfile && $profile->getPart() > 1) {
            return [];
        }

        $violations = [];

        foreach ($inspector->getRegisteredObjects() as $object) {
            if (!$object instanceof PdfStream) {
                continue;
            }

            if (!$object->dictionary->has('Filter')) {
                continue;
            }

            $filter = $object->dictionary->get('Filter');
            $filterStr = $filter instanceof \Phpdftk\Pdf\Core\PdfName ? $filter->value : '';

            if ($filterStr === 'LZWDecode') {
                $violations[] = new ConformanceViolation(
                    clause: '6.1.10',
                    message: 'LZWDecode filter is prohibited in PDF/A-1',
                    severity: ViolationSeverity::Error,
                    objectPath: 'Object[' . $object->objectNumber . ']',
                );
            }
        }

        return $violations;
    }
}
