<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Validator;

use ApprLabs\Pdf\Conformance\Inspection\DocumentInspector;
use ApprLabs\Pdf\Conformance\Profile\ConformanceProfile;
use ApprLabs\Pdf\Conformance\Result\ConformanceResult;
use ApprLabs\Pdf\Conformance\Result\ViolationSeverity;

/**
 * Orchestrates conformance validation: runs all applicable constraints
 * for a profile against a document inspector and returns a result.
 */
final class ConformanceValidator
{
    private ProfileConstraintRegistry $registry;

    public function __construct(?ProfileConstraintRegistry $registry = null)
    {
        $this->registry = $registry ?? new ProfileConstraintRegistry();
    }

    /**
     * Validate a document against a single profile.
     */
    public function validate(DocumentInspector $inspector, ConformanceProfile $profile): ConformanceResult
    {
        $violations = [];

        foreach ($this->registry->getConstraints($profile) as $constraint) {
            $violations = [...$violations, ...$constraint->check($inspector, $profile)];
        }

        $hasErrors = false;
        foreach ($violations as $v) {
            if ($v->severity === ViolationSeverity::Error) {
                $hasErrors = true;
                break;
            }
        }

        return new ConformanceResult(
            profile: $profile,
            isCompliant: !$hasErrors,
            violations: $violations,
        );
    }

    /**
     * Validate a document against multiple profiles.
     *
     * @param ConformanceProfile[] $profiles
     * @return list<ConformanceResult>
     */
    public function validateAll(DocumentInspector $inspector, array $profiles): array
    {
        return array_map(
            fn(ConformanceProfile $p) => $this->validate($inspector, $p),
            $profiles,
        );
    }
}
