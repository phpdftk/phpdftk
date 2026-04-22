<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Multimedia;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\RequiresPdfVersion;

/**
 * Rendition (/Type /Rendition) — ISO 32000-2 §13.2.3.
 *
 * Abstract base for Media renditions (MR) and Selector renditions (SR).
 * Common entries: Type, S (subtype), N (name), MH (media-handler), BE.
 */
#[RequiresPdfVersion(PdfVersion::V1_5)]
abstract class Rendition extends PdfObject
{
    public const PDF_TYPE = 'Rendition';

    public ?PdfString $n = null;              // /N   name
    public ?PdfDictionary $mh = null;         // /MH  "must-honor" criteria
    public ?PdfDictionary $be = null;         // /BE  "best-effort" criteria

    /** Returns the /S (subtype) value, e.g. "MR" or "SR". */
    abstract public function getRenditionSubtype(): string;

    protected function baseDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', new PdfName($this->getRenditionSubtype()));
        if ($this->n !== null) {
            $dict->set('N', $this->n);
        }
        if ($this->mh !== null) {
            $dict->set('MH', $this->mh);
        }
        if ($this->be !== null) {
            $dict->set('BE', $this->be);
        }
        return $dict;
    }
}
