<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Profile\ZugferdProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\FileSpec\FileSpec;

/**
 * ZUGFeRD / Factur-X: Embedded invoice XML validation.
 *
 * Verifies that an XML invoice file is embedded with the correct filename
 * (factur-x.xml or zugferd-invoice.xml) and an appropriate AFRelationship.
 */
final class ZugferdInvoiceConstraint implements ConformanceConstraint
{
    private const VALID_FILENAMES = [
        'factur-x.xml',
        'zugferd-invoice.xml',
    ];

    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        if (!$profile instanceof ZugferdProfile) {
            return [];
        }

        if (!$inspector->hasEmbeddedFiles()) {
            return [
                new ConformanceViolation(
                    clause: 'A.2',
                    message: 'Factur-X requires an embedded XML invoice file',
                    severity: ViolationSeverity::Error,
                    objectPath: 'Catalog.Names.EmbeddedFiles',
                ),
            ];
        }

        // Check for a FileSpec with a valid invoice filename
        $foundInvoice = false;
        foreach ($inspector->getRegisteredObjects() as $object) {
            if ($object instanceof FileSpec) {
                $filename = $object->uf?->value ?? $object->f?->value ?? '';
                if (in_array(strtolower($filename), self::VALID_FILENAMES, true)) {
                    $foundInvoice = true;
                    break;
                }
            }
        }

        if (!$foundInvoice) {
            return [
                new ConformanceViolation(
                    clause: 'A.2',
                    message: sprintf(
                        'Factur-X requires an embedded file named %s',
                        implode(' or ', self::VALID_FILENAMES),
                    ),
                    severity: ViolationSeverity::Error,
                    objectPath: 'Catalog.Names.EmbeddedFiles',
                ),
            ];
        }

        return [];
    }
}
