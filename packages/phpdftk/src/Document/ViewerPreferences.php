<?php

declare(strict_types=1);

namespace Phpdftk\Document;

use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfBoolean;
use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfObject;

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

        return $dict->toPdf();
    }
}
