<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;
use Phpdftk\Pdf\Core\Serializable;

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
    /**
     * /ViewerPreferences — accepts an inline `PdfDictionary`, a
     * `PdfReference` to a registered {@see ViewerPreferences} object,
     * or the value itself when caller writes inline.
     */
    public Serializable|null $viewerPreferences = null;
    public ?PdfName $pageLayout = null;              // /PageLayout
    public ?PdfName $pageMode = null;                // /PageMode
    public ?PdfReference $openAction = null;         // /OpenAction
    public ?PdfReference $acroForm = null;           // /AcroForm
    public ?PdfReference $metadata = null;           // /Metadata
    public ?MarkInfo $markInfo = null;               // /MarkInfo
    public ?PdfString $lang = null;                  // /Lang
    public ?PdfReference $pageLabels = null;         // /PageLabels - number tree of PageLabel dicts
    public ?PdfReference $aa = null;                 // /AA - additional actions dict
    public ?PdfDictionary $uri = null;               // /URI - base URI dict
    public ?PdfArray $outputIntents = null;           // /OutputIntents - array of OutputIntent refs
    public ?PdfBoolean $needsRendering = null;       // /NeedsRendering - XFA flag
    public ?PdfDictionary $legal = null;             // /Legal - legal attestation dict
    public ?PdfReference $ocProperties = null;       // /OCProperties - optional content
    public ?PdfDictionary $perms = null;             // /Perms - permissions dict
    public ?PdfArray $requirements = null;           // /Requirements - requirements array
    public ?PdfReference $collection = null;         // /Collection - PDF portfolio
    public ?PdfReference $spiderInfo = null;         // /SpiderInfo - web capture info
    public ?PdfDictionary $pieceInfo = null;         // /PieceInfo - application data
    public ?PdfReference $structTreeRoot = null;     // /StructTreeRoot - structure tree root
    #[RequiresPdfVersion(PdfVersion::V2_0)]
    public DSS|PdfReference|null $dss = null;        // /DSS - document security store (PAdES LTV)
    #[RequiresPdfVersion(PdfVersion::V1_7)]
    public ?PdfDictionary $extensions = null;        // /Extensions - developer extensions
    #[RequiresPdfVersion(PdfVersion::V2_0)]
    public ?PdfArray $af = null;                     // /AF - associated files
    #[RequiresPdfVersion(PdfVersion::V2_0)]
    public ?PdfReference $dPartRoot = null;          // /DPartRoot - document parts root (PDF 2.0)

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
        if ($this->aa !== null) {
            $dict->set('AA', $this->aa);
        }
        if ($this->uri !== null) {
            $dict->set('URI', $this->uri);
        }
        if ($this->outputIntents !== null) {
            $dict->set('OutputIntents', $this->outputIntents);
        }
        if ($this->needsRendering !== null) {
            $dict->set('NeedsRendering', $this->needsRendering);
        }
        if ($this->legal !== null) {
            $dict->set('Legal', $this->legal);
        }
        if ($this->ocProperties !== null) {
            $dict->set('OCProperties', $this->ocProperties);
        }
        if ($this->perms !== null) {
            $dict->set('Perms', $this->perms);
        }
        if ($this->requirements !== null) {
            $dict->set('Requirements', $this->requirements);
        }
        if ($this->collection !== null) {
            $dict->set('Collection', $this->collection);
        }
        if ($this->spiderInfo !== null) {
            $dict->set('SpiderInfo', $this->spiderInfo);
        }
        if ($this->pieceInfo !== null) {
            $dict->set('PieceInfo', $this->pieceInfo);
        }
        if ($this->structTreeRoot !== null) {
            $dict->set('StructTreeRoot', $this->structTreeRoot);
        }
        if ($this->dss !== null) {
            $dict->set('DSS', $this->dss);
        }
        if ($this->extensions !== null) {
            $dict->set('Extensions', $this->extensions);
        }
        if ($this->af !== null) {
            $dict->set('AF', $this->af);
        }
        if ($this->dPartRoot !== null) {
            $dict->set('DPartRoot', $this->dPartRoot);
        }

        return $dict->toPdf();
    }
}
