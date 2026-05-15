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

    public function testFileSpecAllOptionalFields(): void
    {
        $f = new FileSpec();
        $f->fs = new \Phpdftk\Pdf\Core\PdfName('URL');
        $f->f = new PdfString('http://example.com');
        $f->uf = new PdfString('http://example.com');
        $f->dos = new PdfString('C:\\file.txt');
        $f->mac = new PdfString(':file.txt');
        $f->unix = new PdfString('/tmp/file.txt');
        $f->id = new \Phpdftk\Pdf\Core\PdfArray([new PdfString('abc'), new PdfString('def')]);
        $f->volatile = true;
        $f->ef = new \Phpdftk\Pdf\Core\PdfDictionary();
        $f->rf = new \Phpdftk\Pdf\Core\PdfDictionary();
        $f->desc = new PdfString('Test attachment');
        $f->ci = new PdfReference(99);
        $f->afRelationship = new \Phpdftk\Pdf\Core\PdfName('Source');

        $pdf = $f->toPdf();
        $this->assertStringContainsString('/FS /URL', $pdf);
        $this->assertStringContainsString('/F (http://example.com)', $pdf);
        $this->assertStringContainsString('/UF (http://example.com)', $pdf);
        $this->assertStringContainsString('/DOS', $pdf);
        $this->assertStringContainsString('/Mac', $pdf);
        $this->assertStringContainsString('/Unix', $pdf);
        $this->assertStringContainsString('/ID', $pdf);
        $this->assertStringContainsString('/V true', $pdf);
        $this->assertStringContainsString('/EF', $pdf);
        $this->assertStringContainsString('/RF', $pdf);
        $this->assertStringContainsString('/Desc', $pdf);
        $this->assertStringContainsString('/CI 99 0 R', $pdf);
        $this->assertStringContainsString('/AFRelationship /Source', $pdf);
    }

    public function testEmbeddedFileParamsAllFields(): void
    {
        $p = new EmbeddedFileParams();
        $p->size = 1024;
        $p->creationDate = new PdfString('D:20260101120000Z');
        $p->modDate = new PdfString('D:20260101130000Z');
        $p->mac = new PdfString('mac-info');
        $p->checkSum = new PdfString('checksum', hex: true);

        $pdf = $p->toPdf();
        $this->assertStringContainsString('/Size 1024', $pdf);
        $this->assertStringContainsString('/CreationDate', $pdf);
        $this->assertStringContainsString('/ModDate', $pdf);
        $this->assertStringContainsString('/Mac', $pdf);
        $this->assertStringContainsString('/CheckSum', $pdf);
    }

    public function testEmbeddedFileParamsEmptyEmitsEmptyDict(): void
    {
        $p = new EmbeddedFileParams();
        $pdf = $p->toPdf();
        $this->assertStringContainsString('<<', $pdf);
        $this->assertStringNotContainsString('/Size', $pdf);
    }
}
