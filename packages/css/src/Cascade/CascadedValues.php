<?php

declare(strict_types=1);

namespace Phpdftk\Css\Cascade;

use Phpdftk\Css\Value\Value;

/**
 * Per-element bag of resolved property → value pairs after the cascade
 * has been applied. Phase 1D.3 ships this as a simple map; once the full
 * `ComputedStyle` accessor surface lands (per `contracts.md`), this becomes
 * the underlying storage backing those getters.
 *
 * `get($name)` falls back to the registry's initial value when the property
 * was never set, so callers can treat the map as if every registered
 * property is present.
 */
final class CascadedValues
{
    /** @var array<string, Value> */
    private array $values = [];

    /** @var array<string, Value> */
    private array $customProperties = [];

    public function __construct(private readonly PropertyRegistry $registry) {}

    public function set(string $name, Value $value): void
    {
        $key = $this->normalise($name);
        if (str_starts_with($key, '--')) {
            $this->customProperties[$key] = $value;
            return;
        }
        $this->values[$key] = $value;
    }

    public function has(string $name): bool
    {
        $key = $this->normalise($name);
        if (str_starts_with($key, '--')) {
            return isset($this->customProperties[$key]);
        }
        return isset($this->values[$key]);
    }

    public function get(string $name): ?Value
    {
        $key = $this->normalise($name);
        if (str_starts_with($key, '--')) {
            return $this->customProperties[$key] ?? null;
        }
        if (isset($this->values[$key])) {
            return $this->values[$key];
        }
        return $this->registry->get($key)?->initial;
    }

    /** @return array<string, Value> standard properties only */
    public function all(): array
    {
        return $this->values;
    }

    /** @return array<string, Value> declared custom properties only */
    public function customProperties(): array
    {
        return $this->customProperties;
    }

    /**
     * Custom-property names are case-sensitive per the spec; standard
     * properties are lower-cased.
     */
    private function normalise(string $name): string
    {
        return str_starts_with($name, '--') ? $name : strtolower($name);
    }
}
