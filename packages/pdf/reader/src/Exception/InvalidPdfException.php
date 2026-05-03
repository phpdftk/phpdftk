<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Exception;

/**
 * Thrown when the parser encounters structurally invalid PDF data that
 * cannot be recovered (e.g. missing %PDF header, corrupt xref table,
 * unresolvable /Root catalog).
 */
final class InvalidPdfException extends \RuntimeException
{
}
