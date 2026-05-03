<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document\StructAttribute;

use Phpdftk\Pdf\Core\Document\StructAttribute;

/**
 * Table attribute object (owner /Table) — ISO 32000-2 §14.8.5.7.
 *
 * Typed helper for row/col-span, scope, header metadata.
 */
final class TableAttribute extends StructAttribute
{
    public function __construct()
    {
        parent::__construct('Table');
    }
}
