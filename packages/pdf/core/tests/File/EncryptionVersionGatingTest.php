<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\File;

use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\PageTree;
use ApprLabs\Pdf\Core\File\PdfFileWriter;
use ApprLabs\Pdf\Core\File\VersionRequirementResolver;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Pdf\Core\Security\PdfEncryptor;
use PHPUnit\Framework\TestCase;

class EncryptionVersionGatingTest extends TestCase
{
    protected function setUp(): void
    {
        VersionRequirementResolver::clearCache();
    }

    private function createWriter(PdfVersion $version): PdfFileWriter
    {
        $writer = new PdfFileWriter(version: $version);
        $catalog = new Catalog();
        $writer->setCatalog($catalog);
        $pageTree = new PageTree();
        $writer->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);
        return $writer;
    }

    public function testEncryptorMinimumVersionRc4(): void
    {
        $fileId = md5('test', true);
        $enc = PdfEncryptor::rc4128('user', 'owner', $fileId);
        $this->assertSame(PdfVersion::V1_4, $enc->getMinimumPdfVersion());
    }

    public function testEncryptorMinimumVersionAes128(): void
    {
        $fileId = md5('test', true);
        $enc = PdfEncryptor::aes128('user', 'owner', $fileId);
        $this->assertSame(PdfVersion::V1_6, $enc->getMinimumPdfVersion());
    }

    public function testEncryptorMinimumVersionAes256(): void
    {
        $fileId = md5('test', true);
        $enc = PdfEncryptor::aes256('user', 'owner', $fileId);
        $this->assertSame(PdfVersion::V2_0, $enc->getMinimumPdfVersion());
    }

    public function testAes128AutoBumpsVersion(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_4);
        $fileId = md5('test', true);
        $enc = PdfEncryptor::aes128('user', 'owner', $fileId);
        $writer->setEncryption($enc);

        $this->assertSame(PdfVersion::V1_6, $writer->getPdfVersion());
    }

    public function testAes256AutoBumpsTo20(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);
        $fileId = md5('test', true);
        $enc = PdfEncryptor::aes256('user', 'owner', $fileId);
        $writer->setEncryption($enc);

        $this->assertSame(PdfVersion::V2_0, $writer->getPdfVersion());
    }

    public function testRc4DoesNotBumpFrom17(): void
    {
        $writer = $this->createWriter(PdfVersion::V1_7);
        $fileId = md5('test', true);
        $enc = PdfEncryptor::rc4128('user', 'owner', $fileId);
        $writer->setEncryption($enc);

        $this->assertSame(PdfVersion::V1_7, $writer->getPdfVersion());
    }
}
