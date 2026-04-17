<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfBoolean;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Optional content configuration dictionary — ISO 32000-2 §8.11.4.3,
 * Table 101.
 *
 * Lives inside `OCPropertiesDict::$d` (default) or in the `/Configs`
 * array (alternative configurations). Carries the initial on/off state
 * of each OCG, the display tree order (/Order), radio-button groups
 * (/RBGroups), and permanently locked groups (/Locked).
 */
class OCConfig extends PdfObject
{
    public ?PdfString $name = null;        // /Name
    public ?PdfString $creator = null;     // /Creator
    public ?PdfName $baseState = null;     // /BaseState - ON | OFF | Unchanged
    public ?PdfArray $on = null;           // /ON
    public ?PdfArray $off = null;          // /OFF
    public ?PdfArray $intent = null;       // /Intent
    public ?PdfArray $as = null;           // /AS - auto-state
    public ?PdfArray $order = null;        // /Order
    public ?PdfName $listMode = null;      // /ListMode
    public ?PdfArray $rbGroups = null;     // /RBGroups
    public ?PdfArray $locked = null;       // /Locked

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        if ($this->name !== null) {
            $dict->set('Name', $this->name);
        }
        if ($this->creator !== null) {
            $dict->set('Creator', $this->creator);
        }
        if ($this->baseState !== null) {
            $dict->set('BaseState', $this->baseState);
        }
        if ($this->on !== null) {
            $dict->set('ON', $this->on);
        }
        if ($this->off !== null) {
            $dict->set('OFF', $this->off);
        }
        if ($this->intent !== null) {
            $dict->set('Intent', $this->intent);
        }
        if ($this->as !== null) {
            $dict->set('AS', $this->as);
        }
        if ($this->order !== null) {
            $dict->set('Order', $this->order);
        }
        if ($this->listMode !== null) {
            $dict->set('ListMode', $this->listMode);
        }
        if ($this->rbGroups !== null) {
            $dict->set('RBGroups', $this->rbGroups);
        }
        if ($this->locked !== null) {
            $dict->set('Locked', $this->locked);
        }
        return $dict->toPdf();
    }
}
