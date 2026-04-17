<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\Serializable;

/**
 * ClassMap dictionary — ISO 32000-2 §14.7.4.
 *
 * Maps class names to structure-attribute objects (or arrays of them).
 * Lives inline on `StructTreeRoot::$classMap`.
 */
class ClassMap implements Serializable
{
    /** @var array<string, StructAttribute|PdfReference|PdfArray> */
    public array $entries = [];

    public function set(string $className, StructAttribute|PdfReference|PdfArray $value): self
    {
        $this->entries[$className] = $value;
        return $this;
    }

    public function toDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        foreach ($this->entries as $name => $value) {
            $dict->set($name, $value);
        }
        return $dict;
    }

    public function toPdf(): string
    {
        return $this->toDictionary()->toPdf();
    }
}
