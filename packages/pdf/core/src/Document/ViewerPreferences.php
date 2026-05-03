<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document;

use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfBoolean;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * PDF Viewer Preferences dictionary.
 * Controls how the PDF viewer displays the document.
 */
class ViewerPreferences extends PdfObject
{
    public ?bool $hideToolbar = null;                      // /HideToolbar
    public ?bool $hideMenubar = null;                      // /HideMenubar
    public ?bool $hideWindowUI = null;                     // /HideWindowUI
    public ?bool $fitWindow = null;                        // /FitWindow
    public ?bool $centerWindow = null;                     // /CenterWindow
    public ?bool $displayDocTitle = null;                  // /DisplayDocTitle
    public ?PdfName $nonFullScreenPageMode = null;         // /NonFullScreenPageMode
    public ?PdfName $direction = null;                     // /Direction
    public ?PdfName $viewArea = null;                      // /ViewArea
    public ?PdfName $viewClip = null;                      // /ViewClip
    public ?PdfName $printArea = null;                     // /PrintArea
    public ?PdfName $printClip = null;                     // /PrintClip
    public ?PdfName $printScaling = null;                  // /PrintScaling
    public ?PdfName $duplex = null;                        // /Duplex
    public ?bool $pickTrayByPDFSize = null;                // /PickTrayByPDFSize
    public ?PdfArray $printPageRange = null;               // /PrintPageRange
    public ?int $numCopies = null;                         // /NumCopies
    #[RequiresPdfVersion(PdfVersion::V2_0)]
    public ?PdfArray $enforce = null;                      // /Enforce - PDF 2.0 array of names

    public function toPdf(): string
    {
        $dict = new PdfDictionary();

        if ($this->hideToolbar !== null) {
            $dict->set('HideToolbar', new PdfBoolean($this->hideToolbar));
        }
        if ($this->hideMenubar !== null) {
            $dict->set('HideMenubar', new PdfBoolean($this->hideMenubar));
        }
        if ($this->hideWindowUI !== null) {
            $dict->set('HideWindowUI', new PdfBoolean($this->hideWindowUI));
        }
        if ($this->fitWindow !== null) {
            $dict->set('FitWindow', new PdfBoolean($this->fitWindow));
        }
        if ($this->centerWindow !== null) {
            $dict->set('CenterWindow', new PdfBoolean($this->centerWindow));
        }
        if ($this->displayDocTitle !== null) {
            $dict->set('DisplayDocTitle', new PdfBoolean($this->displayDocTitle));
        }
        if ($this->nonFullScreenPageMode !== null) {
            $dict->set('NonFullScreenPageMode', $this->nonFullScreenPageMode);
        }
        if ($this->direction !== null) {
            $dict->set('Direction', $this->direction);
        }
        if ($this->viewArea !== null) {
            $dict->set('ViewArea', $this->viewArea);
        }
        if ($this->viewClip !== null) {
            $dict->set('ViewClip', $this->viewClip);
        }
        if ($this->printArea !== null) {
            $dict->set('PrintArea', $this->printArea);
        }
        if ($this->printClip !== null) {
            $dict->set('PrintClip', $this->printClip);
        }
        if ($this->printScaling !== null) {
            $dict->set('PrintScaling', $this->printScaling);
        }
        if ($this->duplex !== null) {
            $dict->set('Duplex', $this->duplex);
        }
        if ($this->pickTrayByPDFSize !== null) {
            $dict->set('PickTrayByPDFSize', new PdfBoolean($this->pickTrayByPDFSize));
        }
        if ($this->printPageRange !== null) {
            $dict->set('PrintPageRange', $this->printPageRange);
        }
        if ($this->numCopies !== null) {
            $dict->set('NumCopies', new PdfNumber($this->numCopies));
        }
        if ($this->enforce !== null) {
            $dict->set('Enforce', $this->enforce);
        }

        return $dict->toPdf();
    }
}
