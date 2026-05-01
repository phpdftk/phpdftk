<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;
use ApprLabs\Pdf\Core\Serializable;

/**
 * RoleMap dictionary — ISO 32000-2 §14.7.3.
 *
 * Maps non-standard structure type names to standard structure types.
 * Lives inline on `StructTreeRoot::$roleMap`.
 *
 * Example:
 *   $map = new RoleMap();
 *   $map->map('Note', 'P');
 *   $map->map('Heading1', 'H1');
 */
#[RequiresPdfVersion(PdfVersion::V1_3)]
class RoleMap implements Serializable
{
    /** @var array<string, string> */
    public array $entries = [];

    public function map(string $customType, string $standardType): self
    {
        $this->entries[$customType] = $standardType;
        return $this;
    }

    public function toDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        foreach ($this->entries as $from => $to) {
            $dict->set($from, new PdfName($to));
        }
        return $dict;
    }

    public function toPdf(): string
    {
        return $this->toDictionary()->toPdf();
    }
}
