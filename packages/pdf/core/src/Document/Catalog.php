<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * PDF Document Catalog (/Type /Catalog).
 * This is the root object of every PDF document.
 */
class Catalog extends PdfObject
{
    public const PDF_TYPE = 'Catalog';

    public ?PdfReference $pages = null;              // /Pages - required
    public ?PdfName $version = null;                 // /Version
    public ?PdfReference $outlines = null;           // /Outlines
    public ?PdfReference $names = null;              // /Names
    public ?PdfReference $dests = null;              // /Dests
    public ?PdfDictionary $viewerPreferences = null; // /ViewerPreferences
    public ?PdfName $pageLayout = null;              // /PageLayout
    public ?PdfName $pageMode = null;                // /PageMode
    public ?PdfReference $openAction = null;         // /OpenAction
    public ?PdfReference $acroForm = null;           // /AcroForm
    public ?PdfReference $metadata = null;           // /Metadata
    public ?PdfDictionary $markInfo = null;          // /MarkInfo
    public ?PdfString $lang = null;                  // /Lang
    public ?PdfReference $pageLabels = null;         // /PageLabels - number tree of PageLabel dicts

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));

        if ($this->pages !== null) {
            $dict->set('Pages', $this->pages);
        }
        if ($this->version !== null) {
            $dict->set('Version', $this->version);
        }
        if ($this->outlines !== null) {
            $dict->set('Outlines', $this->outlines);
        }
        if ($this->names !== null) {
            $dict->set('Names', $this->names);
        }
        if ($this->dests !== null) {
            $dict->set('Dests', $this->dests);
        }
        if ($this->viewerPreferences !== null) {
            $dict->set('ViewerPreferences', $this->viewerPreferences);
        }
        if ($this->pageLayout !== null) {
            $dict->set('PageLayout', $this->pageLayout);
        }
        if ($this->pageMode !== null) {
            $dict->set('PageMode', $this->pageMode);
        }
        if ($this->openAction !== null) {
            $dict->set('OpenAction', $this->openAction);
        }
        if ($this->acroForm !== null) {
            $dict->set('AcroForm', $this->acroForm);
        }
        if ($this->metadata !== null) {
            $dict->set('Metadata', $this->metadata);
        }
        if ($this->markInfo !== null) {
            $dict->set('MarkInfo', $this->markInfo);
        }
        if ($this->lang !== null) {
            $dict->set('Lang', $this->lang);
        }
        if ($this->pageLabels !== null) {
            $dict->set('PageLabels', $this->pageLabels);
        }

        return $dict->toPdf();
    }
}
