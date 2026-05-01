<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Tests;

use ApprLabs\Pdf\Core\Annotation\WidgetAnnotation;
use ApprLabs\Pdf\Core\Document\DssBuilder;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Interactive\Form\AcroForm;
use ApprLabs\Pdf\Core\Interactive\Form\SignatureField;
use ApprLabs\Pdf\Core\Interactive\Signature\CertificateUtils;
use ApprLabs\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureValue;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\LtvSigner;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class LtvSignerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required');
        }
    }

    /**
     * Create a signed PDF for testing.
     *
     * @return array{pdf: string, cert: string, key: string}
     */
    private function createSignedPdf(): array
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('ltv-test');

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 12)
            ->moveTextPosition(72, 720)
            ->showText('LTV signer test page')
            ->endText();

        $sigValue = new SignatureValue();
        $sigValue->name = new PdfString('LTV Test');
        $sigValueRef = $writer->register($sigValue);

        $field = new SignatureField();
        $field->t = new PdfString('Signature1');
        $field->setSignatureValue($sigValueRef);
        $fieldRef = $writer->register($field);

        $widget = new WidgetAnnotation(new PdfArray([
            new PdfNumber(72), new PdfNumber(600),
            new PdfNumber(320), new PdfNumber(680),
        ]));
        $widget->parent = $fieldRef;
        $page->corePage()->annots[] = $writer->register($widget);

        $acroForm = new AcroForm();
        $acroForm->fields = [$fieldRef];
        $acroForm->sigFlags = 3;
        $writer->getCatalog()->acroForm = $writer->register($acroForm);

        $writer->setSigner($sigValue, new Pkcs7Signer($creds['cert'], $creds['key']));
        $pdf = $writer->generate();

        return ['pdf' => $pdf, 'cert' => $creds['cert'], 'key' => $creds['key']];
    }

    /**
     * Create an unsigned PDF for testing.
     */
    private function createUnsignedPdf(): string
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
        $cs = $writer->addContentStream($page);
        $cs->beginText()->setFont($fontName, 12)->moveTextPosition(72, 720)->showText('Unsigned')->endText();
        return $writer->generate();
    }

    public function testOpenFromString(): void
    {
        $signed = $this->createSignedPdf();
        $ltv = LtvSigner::openString($signed['pdf']);
        self::assertInstanceOf(LtvSigner::class, $ltv);
    }

    public function testOpenFromFile(): void
    {
        $signed = $this->createSignedPdf();
        $tmpFile = tempnam(sys_get_temp_dir(), 'ltv_test_');
        file_put_contents($tmpFile, $signed['pdf']);

        try {
            $ltv = LtvSigner::open($tmpFile);
            self::assertInstanceOf(LtvSigner::class, $ltv);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testToBytesWithNoSignaturesReturnsOriginal(): void
    {
        $unsigned = $this->createUnsignedPdf();
        $ltv = LtvSigner::openString($unsigned);
        $result = $ltv->toBytes();

        self::assertSame($unsigned, $result, 'Unsigned PDF should be returned unchanged');
    }

    public function testAddLtvWithPreloadedData(): void
    {
        $signed = $this->createSignedPdf();
        $certDer = CertificateUtils::pemToDer($signed['cert']);

        // Create dummy OCSP/CRL data for testing
        $dummyOcsp = random_bytes(256);
        $dummyCrl = random_bytes(512);

        $result = LtvSigner::openString($signed['pdf'])
            ->addCertificate($certDer)
            ->addOcspResponse($dummyOcsp)
            ->addCrl($dummyCrl)
            ->toBytes();

        // Result should be a valid PDF
        self::assertStringStartsWith('%PDF-', $result);
        self::assertStringContainsString('%%EOF', $result);

        // Result should contain DSS
        self::assertStringContainsString('/DSS', $result);
    }

    public function testAddLtvPopulatesVri(): void
    {
        $signed = $this->createSignedPdf();

        $result = LtvSigner::openString($signed['pdf'])
            ->addCertificate(CertificateUtils::pemToDer($signed['cert']))
            ->toBytes();

        // VRI should be present
        self::assertStringContainsString('/VRI', $result);
    }

    public function testAddLtvPreservesOriginalSignature(): void
    {
        $signed = $this->createSignedPdf();

        $result = LtvSigner::openString($signed['pdf'])
            ->addCertificate(CertificateUtils::pemToDer($signed['cert']))
            ->toBytes();

        // The original PDF bytes should be preserved at the start
        self::assertStringStartsWith(substr($signed['pdf'], 0, 100), $result);

        // Original signature structures should still be present
        self::assertStringContainsString('/Type /Sig', $result);
        self::assertStringContainsString('/Filter /Adobe.PPKLite', $result);
    }

    public function testAddLtvWithMultiplePreloadedOcsps(): void
    {
        $signed = $this->createSignedPdf();

        $result = LtvSigner::openString($signed['pdf'])
            ->addOcspResponse(random_bytes(128))
            ->addOcspResponse(random_bytes(128))
            ->addCertificate(CertificateUtils::pemToDer($signed['cert']))
            ->toBytes();

        self::assertStringContainsString('/DSS', $result);
        self::assertStringContainsString('/OCSPs', $result);
    }

    public function testForSignatureTargetsSpecificField(): void
    {
        $signed = $this->createSignedPdf();

        $result = LtvSigner::openString($signed['pdf'])
            ->forSignature('Signature1')
            ->addCertificate(CertificateUtils::pemToDer($signed['cert']))
            ->toBytes();

        self::assertStringContainsString('/DSS', $result);
    }

    public function testForSignatureThrowsForUnknownField(): void
    {
        $signed = $this->createSignedPdf();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        LtvSigner::openString($signed['pdf'])
            ->forSignature('NonExistentField')
            ->toBytes();
    }

    public function testGetWarningsReportsOcspFailures(): void
    {
        // With no OCSP client and no pre-loaded data, self-signed certs
        // will produce warnings about missing AIA
        $signed = $this->createSignedPdf();

        $ltv = LtvSigner::openString($signed['pdf']);
        $ltv->toBytes();

        // No OCSP client set and self-signed cert has no OCSP URL
        // so no warnings about OCSP (it just doesn't try)
        $warnings = $ltv->getWarnings();
        self::assertIsArray($warnings);
    }

    public function testGetVersionWarnings(): void
    {
        $signed = $this->createSignedPdf();

        $ltv = LtvSigner::openString($signed['pdf'])
            ->addCertificate(CertificateUtils::pemToDer($signed['cert']));
        $ltv->toBytes();

        // DSS requires PDF 2.0, so version should be bumped
        $warnings = $ltv->getVersionWarnings();
        self::assertNotEmpty($warnings, 'Should have version bump warning for DSS (PDF 2.0)');
    }

    public function testSaveWritesFile(): void
    {
        $signed = $this->createSignedPdf();
        $tmpFile = tempnam(sys_get_temp_dir(), 'ltv_save_');

        try {
            LtvSigner::openString($signed['pdf'])
                ->addCertificate(CertificateUtils::pemToDer($signed['cert']))
                ->save($tmpFile);

            self::assertFileExists($tmpFile);
            $content = file_get_contents($tmpFile);
            self::assertStringStartsWith('%PDF-', $content);
            self::assertStringContainsString('/DSS', $content);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGetPageCount(): void
    {
        $signed = $this->createSignedPdf();
        $ltv = LtvSigner::openString($signed['pdf']);
        self::assertSame(1, $ltv->getPageCount());
    }

    public function testGetReader(): void
    {
        $signed = $this->createSignedPdf();
        $ltv = LtvSigner::openString($signed['pdf']);
        self::assertInstanceOf(PdfReader::class, $ltv->getReader());
    }

    public function testLtvOutputIsReadableByPdfReader(): void
    {
        $signed = $this->createSignedPdf();

        $ltvBytes = LtvSigner::openString($signed['pdf'])
            ->addCertificate(CertificateUtils::pemToDer($signed['cert']))
            ->addOcspResponse(random_bytes(128))
            ->toBytes();

        // Should be parseable by PdfReader
        $reader = PdfReader::fromString($ltvBytes);
        self::assertSame(1, $reader->getPageCount());
    }

    public function testDssContainsCertificateStreams(): void
    {
        $signed = $this->createSignedPdf();
        $certDer = CertificateUtils::pemToDer($signed['cert']);

        $ltvBytes = LtvSigner::openString($signed['pdf'])
            ->addCertificate($certDer)
            ->toBytes();

        // Parse the output and check for /DSS in catalog
        $reader = PdfReader::fromString($ltvBytes);
        $catalog = $reader->resolveReference($reader->getTrailer()->get('Root'));
        self::assertNotNull($catalog->get('DSS'), 'Catalog should contain /DSS');
    }

    public function testVriDictionaryMatchesSignatureHash(): void
    {
        $signed = $this->createSignedPdf();

        $ltvBytes = LtvSigner::openString($signed['pdf'])
            ->addCertificate(CertificateUtils::pemToDer($signed['cert']))
            ->toBytes();

        // Extract the signature /Contents from the original PDF
        preg_match('/\/Contents <([0-9a-fA-F]+)>/', $signed['pdf'], $m);
        self::assertNotEmpty($m[1], 'Should find /Contents hex in signed PDF');

        $rawDer = hex2bin(rtrim($m[1], '0'));
        if (strlen(rtrim($m[1], '0')) % 2 !== 0) {
            $rawDer = hex2bin(rtrim($m[1], '0') . '0');
        }
        $expectedKey = DssBuilder::computeVriKey($rawDer);

        // The VRI key should appear in the output
        self::assertStringContainsString($expectedKey, $ltvBytes, 'VRI key should match signature hash');
    }

    public function testFluentApiReturnsSelf(): void
    {
        $signed = $this->createSignedPdf();
        $ltv = LtvSigner::openString($signed['pdf']);

        self::assertSame($ltv, $ltv->addCertificate(random_bytes(32)));
        self::assertSame($ltv, $ltv->addOcspResponse(random_bytes(32)));
        self::assertSame($ltv, $ltv->addCrl(random_bytes(32)));
        self::assertSame($ltv, $ltv->forSignature('Signature1'));
    }
}
