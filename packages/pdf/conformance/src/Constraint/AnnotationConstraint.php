<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Constraint;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceViolation;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;
use ApprLabs\Pdf\Core\Annotation\Annotation;
use ApprLabs\Pdf\Core\Annotation\WidgetAnnotation;
use ApprLabs\Pdf\Core\Annotation\PopupAnnotation;
use ApprLabs\Pdf\Core\Annotation\LinkAnnotation;

/**
 * PDF/UA-1 clause 7.18.1: Annotations must be accessible.
 *
 * All annotations (except Widget and Popup) must have /Contents set
 * to provide an accessible text alternative. Link annotations must
 * additionally have /Contents or be wrapped in a Link structure element.
 */
final class AnnotationConstraint implements ConformanceConstraint
{
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array
    {
        $violations = [];

        foreach ($inspector->getRegisteredObjects() as $object) {
            if (!$object instanceof Annotation) {
                continue;
            }

            // Widget and Popup annotations are exempt from /Contents requirement
            if ($object instanceof WidgetAnnotation || $object instanceof PopupAnnotation) {
                continue;
            }

            if ($object->contents === null || trim($object->contents->value) === '') {
                $subtype = $object->getSubtype();
                $violations[] = new ConformanceViolation(
                    clause: '7.18.1',
                    message: sprintf(
                        '%s annotation (object %d) must have /Contents for accessibility',
                        $subtype,
                        $object->objectNumber,
                    ),
                    severity: ViolationSeverity::Error,
                    objectPath: "Annotation[{$subtype}][{$object->objectNumber}]",
                );
            }
        }

        return $violations;
    }
}
