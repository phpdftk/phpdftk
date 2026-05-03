<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\File;

use Phpdftk\Pdf\Core\PdfObject;

/**
 * Tracks all PdfObject instances and assigns sequential object numbers.
 *
 * Numbering starts at 1 because object 0 is reserved by the PDF spec as the
 * free-list head in the cross-reference table (ISO 32000-2 section 7.5.4). Generation
 * numbers are always 0 for objects in a new document -- non-zero generations
 * only appear in incremental updates that reuse deleted object numbers.
 */
class ObjectRegistry
{
    /** @var array<int, PdfObject> */
    private array $objects = [];
    private int $nextObjectNumber = 1;

    /**
     * Register a PdfObject, assign it an object number, and return that number.
     */
    public function register(PdfObject $object): int
    {
        $objectNumber = $this->nextObjectNumber++;
        $object->objectNumber = $objectNumber;
        $object->generationNumber = 0;
        $this->objects[$objectNumber] = $object;
        return $objectNumber;
    }

    /**
     * Return all registered objects, keyed by object number.
     *
     * @return array<int, PdfObject>
     */
    public function getAll(): array
    {
        return $this->objects;
    }

    /**
     * Return the total count of objects including the free list head (object 0).
     */
    public function getSize(): int
    {
        return $this->nextObjectNumber; // includes object 0
    }
}
