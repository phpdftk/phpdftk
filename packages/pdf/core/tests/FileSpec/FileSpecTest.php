<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\FileSpec;

use Phpdftk\Pdf\Core\FileSpec\EmbeddedFile;
use Phpdftk\Pdf\Core\FileSpec\EmbeddedFileParams;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use PHPUnit\Framework\TestCase;

class FileSpecTest extends TestCase
{
    public function testFileSpecType(): void
    {
        $f = new FileSpec('data.txt');
        $f->objectNumber = 1;
        self::assertStringContainsString('/Type /Filespec', $f->toPdf());
        self::assertStringContainsString('/F (data.txt)', $f->toPdf());
        self::assertStringContainsString('/UF (data.txt)', $f->toPdf());
    }

    public function testFileSpecAttachEmbeddedFile(): void
    {
        $f = new FileSpec('data.txt');
        $f->objectNumber = 1;
        $f->attachEmbeddedFile(new PdfReference(5));
        $pdf = $f->toPdf();
        self::assertStringContainsString('/EF', $pdf);
        self::assertStringContainsString('5 0 R', $pdf);
    }

    public function testFileSpecDescription(): void
    {
        $f = new FileSpec('a.xml');
        $f->objectNumber = 1;
        $f->desc = new PdfString('A sample attachment');
        self::assertStringContainsString('/Desc (A sample attachment)', $f->toPdf());
    }

    public function testEmbeddedFileType(): void
    {
        $ef = new EmbeddedFile('<doc/>', 'application/xml');
        $ef->objectNumber = 1;
        $pdf = $ef->toPdf();
        self::assertStringContainsString('/Type /EmbeddedFile', $pdf);
        self::assertStringContainsString('/Subtype', $pdf);
        self::assertStringContainsString('application', $pdf);
    }

    public function testEmbeddedFileWithParams(): void
    {
        $params = new EmbeddedFileParams();
        $params->size = 6;
        $params->checkSum = new PdfString(md5('<doc/>', true), hex: true);

        $ef = new EmbeddedFile('<doc/>', 'application/xml');
        $ef->objectNumber = 1;
        $ef->params = $params;
        $pdf = $ef->toPdf();
        self::assertStringContainsString('/Params', $pdf);
        self::assertStringContainsString('/Size 6', $pdf);
        self::assertStringContainsString('/CheckSum', $pdf);
    }
}
