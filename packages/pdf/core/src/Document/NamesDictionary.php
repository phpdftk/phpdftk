<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Document Names dictionary — ISO 32000-2 §7.7.4, Table 33.
 *
 * Referenced from `Catalog::$names`. Each entry is a reference to a
 * name tree mapping a string key to the relevant object type.
 */
class NamesDictionary extends PdfObject
{
    public ?PdfReference $dests = null;                  // /Dests
    public ?PdfReference $ap = null;                     // /AP
    public ?PdfReference $javaScript = null;             // /JavaScript
    public ?PdfReference $pages = null;                  // /Pages
    public ?PdfReference $templates = null;              // /Templates
    public ?PdfReference $ids = null;                    // /IDS
    public ?PdfReference $urls = null;                   // /URLS
    public ?PdfReference $embeddedFiles = null;          // /EmbeddedFiles
    public ?PdfReference $alternatePresentations = null; // /AlternatePresentations
    public ?PdfReference $renditions = null;             // /Renditions

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->dests !== null) {
            $dict->set('Dests', $this->dests);
        }
        if ($this->ap !== null) {
            $dict->set('AP', $this->ap);
        }
        if ($this->javaScript !== null) {
            $dict->set('JavaScript', $this->javaScript);
        }
        if ($this->pages !== null) {
            $dict->set('Pages', $this->pages);
        }
        if ($this->templates !== null) {
            $dict->set('Templates', $this->templates);
        }
        if ($this->ids !== null) {
            $dict->set('IDS', $this->ids);
        }
        if ($this->urls !== null) {
            $dict->set('URLS', $this->urls);
        }
        if ($this->embeddedFiles !== null) {
            $dict->set('EmbeddedFiles', $this->embeddedFiles);
        }
        if ($this->alternatePresentations !== null) {
            $dict->set('AlternatePresentations', $this->alternatePresentations);
        }
        if ($this->renditions !== null) {
            $dict->set('Renditions', $this->renditions);
        }
        return $dict->toPdf();
    }
}
