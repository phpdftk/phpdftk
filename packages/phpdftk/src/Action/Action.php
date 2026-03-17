<?php

declare(strict_types=1);

namespace Phpdftk\Action;

use Phpdftk\Core\PdfObject;
use Phpdftk\Core\PdfReference;

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
