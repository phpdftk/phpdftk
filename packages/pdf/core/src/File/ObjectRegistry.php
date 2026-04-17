<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\File;

use ApprLabs\Pdf\Core\PdfObject;

/**
 * Tracks all PdfObject instances and assigns sequential object numbers.
 * Object 0 is reserved as the free list head (never actually registered here).
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
