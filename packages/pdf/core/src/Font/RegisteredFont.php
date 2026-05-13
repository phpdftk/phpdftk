<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font;

use Phpdftk\Encoding\TextEncoder;

/**
 * A font that has been registered with a writer and assigned a resource
 * name. ContentStream::setFont() accepts this so callers can pass UTF-8 to
 * showText() and have the encoder do the byte-level translation for them.
 *
 * The encoder is optional — CID/Type0 fonts return null and continue to use
 * the showTextHex() / showUnicodeText() path.
 */
interface RegisteredFont
{
    public function getResourceName(): string;

    public function getTextEncoder(): ?TextEncoder;
}
