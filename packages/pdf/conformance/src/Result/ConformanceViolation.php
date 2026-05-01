<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Result;

/**
 * A single conformance violation detected during validation.
 */
final readonly class ConformanceViolation
{
    public function __construct(
        /** ISO clause reference (e.g. "6.3.4"). */
        public string $clause,
        /** Human-readable description of the violation. */
        public string $message,
        /** Severity level. */
        public ViolationSeverity $severity = ViolationSeverity::Error,
        /** Path to the offending object (e.g. "Page[0].Resources.Font[F1]"). */
        public ?string $objectPath = null,
    ) {}
}
