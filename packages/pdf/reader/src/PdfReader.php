<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader;

/**
 * PDF reader — parses existing PDF files into the phpdftk object model.
 *
 * @todo Not yet implemented.
 */
class PdfReader
{
    public static function fromFile(string $path): self
    {
        throw new \RuntimeException('PdfReader is not yet implemented.');
    }

    public static function fromString(string $content): self
    {
        throw new \RuntimeException('PdfReader is not yet implemented.');
    }

    /** @param resource $stream */
    public static function fromStream($stream): self
    {
        throw new \RuntimeException('PdfReader is not yet implemented.');
    }
}
