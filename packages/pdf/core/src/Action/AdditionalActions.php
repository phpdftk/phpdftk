<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Action;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Additional-actions dictionary — ISO 32000-2 §12.6.3, Tables 194–197.
 *
 * Referenced from `/AA` on a Catalog, Page, Annotation, or Form field.
 * The set of valid trigger-event keys depends on the parent type:
 *
 *   - Catalog:      WC  WS  DS  WP  DP
 *   - Page:         O   C
 *   - Annotation:   E   X   D   U   Fo  Bl  PO  PC  PV  PI
 *   - Form Field:   K   F   V   C
 *
 * This class is permissive: it holds arbitrary trigger → action entries
 * and does not enforce which keys are legal for which parent. The
 * helper methods cover the common triggers; uncommon ones can be set
 * via `$this->set($trigger, $action)`.
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
class AdditionalActions extends PdfObject
{
    /** @var array<string, Action|PdfReference> trigger key => action */
    public array $triggers = [];

    public function set(string $trigger, Action|PdfReference $action): self
    {
        $this->triggers[$trigger] = $action;
        return $this;
    }

    // -- Catalog triggers ------------------------------------------------
    public function onWillClose(Action|PdfReference $a): self      { return $this->set('WC', $a); }
    public function onWillSave(Action|PdfReference $a): self       { return $this->set('WS', $a); }
    public function onDidSave(Action|PdfReference $a): self        { return $this->set('DS', $a); }
    public function onWillPrint(Action|PdfReference $a): self      { return $this->set('WP', $a); }
    public function onDidPrint(Action|PdfReference $a): self       { return $this->set('DP', $a); }

    // -- Page triggers ---------------------------------------------------
    public function onPageOpen(Action|PdfReference $a): self       { return $this->set('O', $a); }
    public function onPageClose(Action|PdfReference $a): self      { return $this->set('C', $a); }

    // -- Annotation triggers --------------------------------------------
    public function onMouseEnter(Action|PdfReference $a): self     { return $this->set('E', $a); }
    public function onMouseExit(Action|PdfReference $a): self      { return $this->set('X', $a); }
    public function onMouseDown(Action|PdfReference $a): self      { return $this->set('D', $a); }
    public function onMouseUp(Action|PdfReference $a): self        { return $this->set('U', $a); }
    public function onFocus(Action|PdfReference $a): self          { return $this->set('Fo', $a); }
    public function onBlur(Action|PdfReference $a): self           { return $this->set('Bl', $a); }
    public function onPageVisible(Action|PdfReference $a): self    { return $this->set('PV', $a); }
    public function onPageInvisible(Action|PdfReference $a): self  { return $this->set('PI', $a); }

    // -- Form field triggers --------------------------------------------
    public function onKeystroke(Action|PdfReference $a): self      { return $this->set('K', $a); }
    public function onFormat(Action|PdfReference $a): self         { return $this->set('F', $a); }
    public function onValidate(Action|PdfReference $a): self       { return $this->set('V', $a); }
    public function onCalculate(Action|PdfReference $a): self      { return $this->set('C', $a); }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        foreach ($this->triggers as $trigger => $action) {
            $dict->set($trigger, $action);
        }
        return $dict->toPdf();
    }
}
