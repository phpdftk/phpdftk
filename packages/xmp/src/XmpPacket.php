<?php

declare(strict_types=1);

namespace Phpdftk\Xmp;

/**
 * Immutable bag of XMP metadata properties.
 *
 * Properties are stored as namespace-prefixed keys (e.g., "dc:title").
 * `set()` returns a new instance — the original is never mutated.
 */
final class XmpPacket
{
    /** @param array<string, string> $properties */
    private function __construct(
        private readonly array $properties,
    ) {}

    public static function create(): self
    {
        return new self([]);
    }

    public function get(string $key): ?string
    {
        return $this->properties[$key] ?? null;
    }

    public function set(string $key, string $value): self
    {
        $props = $this->properties;
        $props[$key] = $value;
        return new self($props);
    }

    public function has(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->properties;
    }
}
