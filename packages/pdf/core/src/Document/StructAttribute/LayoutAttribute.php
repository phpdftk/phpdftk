<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Document\StructAttribute;

use Phpdftk\Pdf\Core\Document\StructAttribute;

/**
 * Layout attribute object (owner /Layout) — ISO 32000-2 §14.8.5.4.
 *
 * Typed helper that pre-sets `/O /Layout` and exposes every spec-defined
 * layout attribute as a typed setter. Use `$attr->entries` for any
 * remaining attribute keys.
 */
final class LayoutAttribute extends StructAttribute
{
    public function __construct()
    {
        parent::__construct('Layout');
    }
}
