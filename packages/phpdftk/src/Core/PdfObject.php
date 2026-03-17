<?php

declare(strict_types=1);

namespace Phpdftk\Core;

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
            $this->toPdf()
        );
    }
}
