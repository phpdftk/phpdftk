<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Constraint;

use Phpdftk\Pdf\Conformance\Inspection\DocumentInspector;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceViolation;

/**
 * A single category of conformance checks (e.g. font embedding, encryption).
 */
interface ConformanceConstraint
{
    /**
     * Check the document against this constraint for the given profile.
     *
     * @return list<ConformanceViolation>
     */
    public function check(DocumentInspector $inspector, ConformanceProfile $profile): array;
}
