<?php

declare(strict_types=1);

namespace ApprLabs\Tests\Support\Arlington;

final readonly class DictionarySpec
{
    /**
     * @param array<string, FieldSpec> $fields keyed by key name
     */
    public function __construct(
        public string $name,
        public array $fields,
    ) {}

    public function hasField(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    /** @return array<string, FieldSpec> */
    public function getRequiredFields(): array
    {
        return array_filter(
            $this->fields,
            static fn(FieldSpec $f): bool => $f->isSimplyRequired(),
        );
    }
}
