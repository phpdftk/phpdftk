<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Font;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;
use ApprLabs\Pdf\Core\Serializable;

/**
 * CIDSystemInfo dictionary.
 *
 * Identifies the character collection used by a CIDFont or CMap stream.
 * All three fields are required.
 *
 * Example:
 *   $info = new CIDSystemInfo('Adobe', 'Identity', 0);
 *   $cidFont->cidSystemInfo = $info;
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class CIDSystemInfo implements Serializable
{
    public PdfString $registry;    // /Registry - issuer of the character collection
    public PdfString $ordering;    // /Ordering - identifies the collection within the registry
    public PdfNumber $supplement;  // /Supplement - supplement number (0 for the base collection)

    public function __construct(string $registry, string $ordering, int $supplement)
    {
        $this->registry   = new PdfString($registry);
        $this->ordering   = new PdfString($ordering);
        $this->supplement = new PdfNumber($supplement);
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Registry', $this->registry);
        $dict->set('Ordering', $this->ordering);
        $dict->set('Supplement', $this->supplement);

        return $dict->toPdf();
    }
}
