<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\File;

use ApprLabs\Pdf\Core\PdfVersion;

/**
 * Thrown when strict version mode is enabled and a registered object
 * requires a higher PDF version than the document is configured for.
 */
class VersionRequirementException extends \RuntimeException
{
    public function __construct(
        public readonly string $objectClass,
        public readonly PdfVersion $requiredVersion,
        public readonly PdfVersion $currentVersion,
    ) {
        parent::__construct(sprintf(
            '%s requires PDF %s, but the document version is %s. '
            . 'Call setVersion(PdfVersion::%s) or disable strict mode '
            . 'with setStrictVersionMode(false) to allow auto-bumping.',
            $objectClass,
            $requiredVersion->value,
            $currentVersion->value,
            $requiredVersion->name,
        ));
    }
}
