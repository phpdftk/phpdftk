<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Encryption;

use Phpdftk\Pdf\Core\Security\PdfEncryptor;

/**
 * PDF permission flags — mirrors PdfEncryptor constants for convenience.
 */
final class Permission
{
    public const PRINT = PdfEncryptor::PERM_PRINT;
    public const MODIFY = PdfEncryptor::PERM_MODIFY;
    public const COPY = PdfEncryptor::PERM_COPY;
    public const ANNOTATE = PdfEncryptor::PERM_ANNOTATE;
    public const FILL_FORMS = PdfEncryptor::PERM_FILL_FORMS;
    public const ACCESSIBILITY = PdfEncryptor::PERM_ACCESSIBILITY;
    public const ASSEMBLE = PdfEncryptor::PERM_ASSEMBLE;
    public const PRINT_HIGH = PdfEncryptor::PERM_PRINT_HIGH;
    public const ALL = PdfEncryptor::PERM_ALL;
}
