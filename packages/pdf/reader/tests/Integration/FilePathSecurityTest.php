<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Integration;

use Phpdftk\Pdf\Reader\PdfReader;
use PHPUnit\Framework\TestCase;

final class FilePathSecurityTest extends TestCase
{
    public function testFromFileRejectsStreamWrapperPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream wrappers are not allowed');

        PdfReader::fromFile('php://filter/read=convert.base64-encode/resource=/etc/passwd');
    }
}
