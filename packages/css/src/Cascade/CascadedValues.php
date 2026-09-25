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

    /**
     * Properties that a DECLARATION won the cascade for on this element,
     * as opposed to arriving from inheritance or the registry's initial
     * value.
     *
     * `has()` cannot answer that question for an INHERITED property —
     * `color`, `white-space`, `border-spacing` and friends are present in
     * every element's map no matter what the author wrote — and that is
     * exactly what a presentational-hint mapping needs to know, since a
     * hint must lose to any author declaration.
     *
     * @var array<string, true>
     */
    private array $declared = [];

    public function __construct(private readonly PropertyRegistry $registry) {}

    /** Mark `$name` as supplied by a declaration (see {@see wasDeclared()}). */
    public function markDeclared(string $name): void
    {
        $this->declared[$this->normalise($name)] = true;
    }

    /**
     * Did a declaration supply this property's value on this element?
     * False for a value that came from inheritance or the initial value.
     */
    public function wasDeclared(string $name): bool
    {
        return isset($this->declared[$this->normalise($name)]);
    }

    /**
     * Did ANY declaration supply a value on this element?
     *
     * False for a bag built purely from inheritance + initial values.
     * Pseudo-element box generation uses this to tell "an author styled
     * `::first-letter`" from "nobody did, and this bag is just the host's
     * inherited text styles" — a distinction `has()` cannot make, since
     * every registered property answers `has()` once inheritance has run.
     */
    public function hasDeclarations(): bool
    {
        return $this->declared !== [];
    }

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
