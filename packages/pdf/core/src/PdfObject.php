<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core;

/**
 * Base class for PDF objects that live as indirect objects (`X Y obj ... endobj`).
 *
 * Every PdfObject gets an object number assigned by {@see File\ObjectRegistry}
 * when registered with a writer. Unlike plain {@see Serializable} types
 * (which serialize inline), PdfObjects can be referenced from anywhere in
 * the document via {@see PdfReference}.
 */
abstract class PdfObject implements Serializable
{
    public int $objectNumber = 0;
    public int $generationNumber = 0;

    /**
     * Serialize the object's dictionary/value to PDF syntax.
     */
    abstract public function toPdf(): string;

    /**
     * Wrap the object in an indirect object structure:
     *   X Y obj
     *   ...
     *   endobj
     */
    public function toIndirectObject(): string
    {
        return sprintf(
            "%d %d obj\n%s\nendobj",
            $this->objectNumber,
            $this->generationNumber,
            $this->toPdf(),
        );
    }
}
