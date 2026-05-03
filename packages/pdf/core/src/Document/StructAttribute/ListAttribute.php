<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document\StructAttribute;

use Phpdftk\Pdf\Core\Document\StructAttribute;

/**
 * List attribute object (owner /List) — ISO 32000-2 §14.8.5.5.
 */
final class ListAttribute extends StructAttribute
{
    public function __construct()
    {
        parent::__construct('List');
    }
}
