<?php

declare(strict_types=1);

namespace Phpdftk\Tests\Support\Arlington;

final readonly class FieldSpec
{
    /**
     * @param string[] $types
     * @param string[] $possibleValues
     */
    public function __construct(
        public string $key,
        public array $types,
        public string $sinceVersion,
        public string $deprecatedIn,
        public string $required,
        public string $indirectReference,
        public bool $inheritable,
        public ?string $defaultValue,
        public array $possibleValues,
        public string $specialCase,
        public string $link,
        public string $note,
    ) {}

    public function isSimplyRequired(): bool
    {
        return $this->required === 'TRUE';
    }
}
