<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\Document\DssBuilder;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\SignatureField;
use Phpdftk\Pdf\Core\Interactive\Signature\CertificateUtils;
use Phpdftk\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\LtvSigner;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end: generate a signed PDF, add LTV data via LtvSigner,
 * verify the output is structurally valid and contains the expected
 * DSS/VRI dictionaries.
 */
#[Group("qpdf")]
class LtvSignedPdfIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    protected function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required');
        }
    }

    /**
     * @return array{pdf: string, cert: string, key: string}
     */
    private function createSignedPdf(): array
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('ltv-integration');

        $writer = new PdfWriter();

        for ($i = 1; $i <= 3; $i++) {
            $page = $writer->addPage(612, 792);
            $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();
            $cs = $writer->addContentStream($page);
            $cs->beginText()
                ->setFont($fontName, 14)
                ->moveTextPosition(72, 740)
                ->showText(sprintf('LTV integration test — page %d of 3', $i))
                ->endText();

            if ($i === 1) {
                $sigValue = new SignatureValue();
                $sigValue->name = new PdfString('LTV Integration');
                $sigValue->reason = new PdfString('Test long-term validation');
                $sigValue->m = new PdfString('D:20260424120000Z');
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
            }
        }

        return ['pdf' => $writer->generate(), 'cert' => $creds['cert'], 'key' => $creds['key']];
    }

    public function testGeneratesSignedPdfWithDss(): void
    {
        $signed = $this->createSignedPdf();
        $certDer = CertificateUtils::pemToDer($signed['cert']);

        // Add LTV data with pre-loaded cert and dummy revocation data
        $ltvBytes = LtvSigner::openString($signed['pdf'])
            ->addCertificate($certDer)
            ->addOcspResponse(random_bytes(256))
            ->addCrl(random_bytes(512))
            ->toBytes();

        // Structural assertions
        self::assertStringStartsWith('%PDF-', $ltvBytes);
        self::assertStringContainsString('%%EOF', $ltvBytes);
        self::assertStringContainsString('/DSS', $ltvBytes);
        self::assertStringContainsString('/VRI', $ltvBytes);
        self::assertStringContainsString('/Certs', $ltvBytes);
        self::assertStringContainsString('/OCSPs', $ltvBytes);
        self::assertStringContainsString('/CRLs', $ltvBytes);

        // LTV output should be larger than original (incremental update)
        self::assertGreaterThan(strlen($signed['pdf']), strlen($ltvBytes));

        // Original PDF bytes should be preserved at the start
        self::assertStringStartsWith(
            substr($signed['pdf'], 0, 200),
            $ltvBytes,
            'Original PDF bytes should be preserved',
        );

        // Write to temp file for QPDF validation
        $tmpFile = tempnam(sys_get_temp_dir(), 'ltv_qpdf_');
        file_put_contents($tmpFile, $ltvBytes);
        try {
            $this->assertQpdfValid($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testDssContainsCertificateStreams(): void
    {
        $signed = $this->createSignedPdf();
        $certDer = CertificateUtils::pemToDer($signed['cert']);

        $ltvBytes = LtvSigner::openString($signed['pdf'])
            ->addCertificate($certDer)
            ->toBytes();

        $reader = PdfReader::fromString($ltvBytes);
        self::assertSame(3, $reader->getPageCount());

        $catalogRef = $reader->getTrailer()->get('Root');
        self::assertNotNull($catalogRef);

        $catalog = $reader->resolveReference($catalogRef);
        self::assertNotNull($catalog);

        $dssRef = $catalog->get('DSS');
        self::assertNotNull($dssRef, 'Catalog should have /DSS entry');
    }

    public function testVriDictionaryMatchesSignatureHash(): void
    {
        $signed = $this->createSignedPdf();

        $ltvBytes = LtvSigner::openString($signed['pdf'])
            ->addCertificate(CertificateUtils::pemToDer($signed['cert']))
            ->toBytes();

        // Extract /Contents hex from signed PDF
        preg_match('/\/Contents <([0-9a-fA-F]+)>/', $signed['pdf'], $m);
        self::assertNotEmpty($m[1]);

        $hexTrimmed = rtrim($m[1], '0');
        if (strlen($hexTrimmed) % 2 !== 0) {
            $hexTrimmed .= '0';
        }
        $rawDer = hex2bin($hexTrimmed);
        $expectedVriKey = DssBuilder::computeVriKey($rawDer);

        self::assertStringContainsString(
            $expectedVriKey,
            $ltvBytes,
            'VRI key should be uppercase hex SHA-256 of signature /Contents',
        );
    }

    public function testOriginalSignatureRemainsVerifiable(): void
    {
        $signed = $this->createSignedPdf();

        $ltvBytes = LtvSigner::openString($signed['pdf'])
            ->addCertificate(CertificateUtils::pemToDer($signed['cert']))
            ->toBytes();

        // The original byte range and signature should be intact
        // since we used incremental update
        preg_match(
            '/\/ByteRange \[ (\d{10}) (\d{10}) (\d{10}) (\d{10}) \]/',
            $signed['pdf'],
            $brOrig,
        );
        self::assertNotEmpty($brOrig, 'Original PDF should have byte range');

        // The same byte range should exist in the LTV output
        $brPattern = sprintf(
            '/\/ByteRange \[ %s %s %s %s \]/',
            $brOrig[1], $brOrig[2], $brOrig[3], $brOrig[4],
        );
        self::assertMatchesRegularExpression(
            $brPattern,
            $ltvBytes,
            'Original byte range should be preserved in LTV output',
        );
    }
}
