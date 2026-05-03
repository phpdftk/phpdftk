<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Conformance\Result;

use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;

/**
 * The result of validating a document against a conformance profile.
 */
final readonly class ConformanceResult
{
    /**
     * @param list<ConformanceViolation> $violations
     */
    public function __construct(
        public ConformanceProfile $profile,
        public bool $isCompliant,
        public array $violations,
    ) {}

    /** @return list<ConformanceViolation> */
    public function getErrors(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn(ConformanceViolation $v) => $v->severity === ViolationSeverity::Error,
        ));
    }

    /** @return list<ConformanceViolation> */
    public function getWarnings(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn(ConformanceViolation $v) => $v->severity === ViolationSeverity::Warning,
        ));
    }
}
