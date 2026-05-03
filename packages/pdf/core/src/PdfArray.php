<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core;

/**
 * Represents a PDF array object: [ item1 item2 ... ]
 *
 * Items may be any Serializable object, a raw string (emitted verbatim),
 * or a scalar int/float (treated as PdfNumber).
 */
class PdfArray implements Serializable
{
    /** @param array<int|string, mixed> $items */
    public function __construct(public readonly array $items = [])
    {
    }

    public function toPdf(): string
    {
        $parts = [];
        foreach ($this->items as $item) {
            $parts[] = match (true) {
                $item instanceof Serializable => $item->toPdf(),
                is_int($item), is_float($item) => (new PdfNumber($item))->toPdf(),
                is_bool($item) => (new PdfBoolean($item))->toPdf(),
                is_null($item) => 'null',
                default => (string) $item,
            };
        }

        return '[ ' . implode(' ', $parts) . ' ]';
    }
}
