<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Profile\ZugferdProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;
use Phpdftk\Xmp\XmpReader;

/**
 * ZUGFeRD / Factur-X: XMP metadata validation.
 *
 * Verifies that the document's XMP metadata contains the required Factur-X
 * identification properties: fx:ConformanceLevel, fx:DocumentType,
 * fx:DocumentFileName.
 */
final class ZugferdXmpConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        if (!$profile instanceof ZugferdProfile) {
            return [];
        }

        $xmpBytes = $inspector->getXmpBytes();
        if ($xmpBytes === null) {
            return [
                new ConformanceViolation(
                    clause: 'A.1',
                    message: 'Factur-X requires XMP metadata with fx: identification properties',
                    severity: ViolationSeverity::Error,
                    objectPath: 'Catalog.Metadata',
                ),
            ];
        }

        $violations = [];
        $expectedProps = $profile->getXmpProperties();
        $prefix = $profile->getXmpPrefix();

        foreach ($expectedProps as $localName => $expectedValue) {
            $fullKey = $prefix . ':' . $localName;
            if (!str_contains($xmpBytes, $fullKey)) {
                $violations[] = new ConformanceViolation(
                    clause: 'A.1',
                    message: sprintf(
                        'Factur-X XMP metadata is missing required property %s (expected value: %s)',
                        $fullKey,
                        $expectedValue,
                    ),
                    severity: ViolationSeverity::Error,
                    objectPath: 'Catalog.Metadata',
                );
            }
        }

        return $violations;
    }
}
