<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document\StructAttribute;

use ApprLabs\Pdf\Core\Document\StructAttribute;

/**
 * PrintField attribute object (owner /PrintField) — ISO 32000-2 §14.8.5.6.
 */
final class PrintFieldAttribute extends StructAttribute
{
    public function __construct()
    {
        parent::__construct('PrintField');
    }
}
