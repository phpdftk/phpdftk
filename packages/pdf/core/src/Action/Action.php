<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Action;

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
}
