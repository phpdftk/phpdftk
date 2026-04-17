<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;

/**
 * Abstract base class for all PDF action types (/Type /Action).
 */
abstract class Action extends PdfObject
{
    public const PDF_TYPE = 'Action';

    /**
     * Returns the /S (action type) value for this action.
     */
    abstract public function getActionType(): string;

    public ?PdfReference $next = null; // /Next - next action to perform

    /**
     * Build a dictionary pre-populated with the common /Type, /S, and /Next
     * entries so subclasses only need to add their subtype-specific fields.
     */
    protected function baseDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('Type', new PdfName(self::PDF_TYPE));
        $dict->set('S', new PdfName($this->getActionType()));
        if ($this->next !== null) {
            $dict->set('Next', $this->next);
        }
        return $dict;
    }
}
