<?php

declare(strict_types=1);

namespace ApprLabs\Tests\Support\Arlington;

final readonly class ValidationResult
{
    /**
     * @param string[] $errors   Spec violations (required keys missing, etc.)
     * @param string[] $warnings Non-fatal issues (unknown keys, version mismatches)
     */
    public function __construct(
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    public function merge(self $other): self
    {
        return new self(
            [...$this->errors, ...$other->errors],
            [...$this->warnings, ...$other->warnings],
        );
    }
}
