<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;
use Phpdftk\Pdf\Conformance\Result\ViolationSeverity;

/**
 * PDF/A clause 6.7: XMP metadata is required and must contain the
 * conformance identification schema.
 */
final class MetadataConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];

        if (!$inspector->hasXmpMetadata()) {
            $violations[] = new ConformanceViolation(
                clause: '6.7.2',
                message: 'XMP metadata stream is required on the document Catalog',
                severity: ViolationSeverity::Error,
            );
            return $violations;
        }

        $xmp = $inspector->getXmpBytes();
        if ($xmp === null || trim($xmp) === '') {
            $violations[] = new ConformanceViolation(
                clause: '6.7.2',
                message: 'XMP metadata stream is empty',
                severity: ViolationSeverity::Error,
            );
            return $violations;
        }

        // Check for identification schema properties
        $prefix = $profile->getXmpPrefix();
        foreach ($profile->getXmpProperties() as $localName => $expectedValue) {
            $tag = $prefix . ':' . $localName;
            if (!str_contains($xmp, $tag)) {
                $violations[] = new ConformanceViolation(
                    clause: '6.7.11',
                    message: sprintf(
                        'XMP metadata must contain <%s>%s</%s> for %s-%s identification',
                        $tag, $expectedValue, $tag,
                        $profile->getFamily(), $profile->getLevel(),
                    ),
                    severity: ViolationSeverity::Error,
                );
            }
        }

        return $violations;
    }
}
