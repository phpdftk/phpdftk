<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core;

/**
 * Represents a PDF dictionary object: << /Key value ... >>
 *
 * Keys are plain strings (no leading slash needed; added during serialization).
 * Values may be any Serializable, scalar int/float/bool, or null.
 */
class PdfDictionary implements Serializable
{
    /** @param array<string, mixed> $entries */
    public function __construct(public array $entries = [])
    {
    }

    /**
     * Set or replace an entry. Returns $this for fluent chaining.
     */
    public function set(string $key, mixed $value): self
    {
        $this->entries[$key] = $value;
        return $this;
    }

    /**
     * Get an entry value by key.
     */
    public function get(string $key): mixed
    {
        return $this->entries[$key] ?? null;
    }

    /**
     * Check whether a key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->entries);
    }

    public function toPdf(): string
    {
        $lines = ['<<'];
        foreach ($this->entries as $key => $value) {
            $serialized = match (true) {
                $value instanceof Serializable => $value->toPdf(),
                is_int($value), is_float($value) => (new PdfNumber($value))->toPdf(),
                is_bool($value) => (new PdfBoolean($value))->toPdf(),
                is_null($value) => 'null',
                default => (string) $value,
            };
            $lines[] = '/' . $key . ' ' . $serialized;
        }
        $lines[] = '>>';

        return implode("\n", $lines);
    }
}
