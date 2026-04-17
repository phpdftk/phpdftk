<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\Serializable;

/**
 * Optional content usage dictionary — ISO 32000-2 §8.11.4.4, Table 104.
 *
 * Holds the nine usage sub-dictionaries that let viewers decide
 * whether an OCG should be visible for printing, viewing, exporting,
 * etc. Each entry is a PdfDictionary containing the usage-specific
 * keys (e.g. /Language /Lang /Preferred for /Language).
 *
 * Attached to `OCG::$usage` (now `OCUsage|PdfDictionary|null`).
 */
class OCUsage implements Serializable
{
    public ?PdfDictionary $creatorInfo = null;  // /CreatorInfo
    public ?PdfDictionary $language = null;     // /Language
    public ?PdfDictionary $export = null;       // /Export
    public ?PdfDictionary $zoom = null;         // /Zoom
    public ?PdfDictionary $print = null;        // /Print
    public ?PdfDictionary $view = null;         // /View
    public ?PdfDictionary $user = null;         // /User
    public ?PdfDictionary $pageElement = null;  // /PageElement

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->creatorInfo !== null) {
            $dict->set('CreatorInfo', $this->creatorInfo);
        }
        if ($this->language !== null) {
            $dict->set('Language', $this->language);
        }
        if ($this->export !== null) {
            $dict->set('Export', $this->export);
        }
        if ($this->zoom !== null) {
            $dict->set('Zoom', $this->zoom);
        }
        if ($this->print !== null) {
            $dict->set('Print', $this->print);
        }
        if ($this->view !== null) {
            $dict->set('View', $this->view);
        }
        if ($this->user !== null) {
            $dict->set('User', $this->user);
        }
        if ($this->pageElement !== null) {
            $dict->set('PageElement', $this->pageElement);
        }
        return $dict->toPdf();
    }
}
