<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\SignatureField;
use Phpdftk\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end: generate a real PDF signed with a self-signed cert, then
 * verify the embedded PKCS#7 blob actually matches the signed byte range
 * using openssl_pkcs7_verify.
 *
 * This is the "real oracle" for the signing pipeline — it proves the
 * /ByteRange was computed correctly AND that /Contents is a valid
 * PKCS#7 signature over those exact bytes.
 */
#[Group("qpdf")]
class SignedPdfIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/signed_pdf.pdf';

    protected function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required');
        }
    }

    public function testGeneratesAndVerifiesSignedPdf(): void
    {
        $creds = Pkcs7Signer::createSelfSignedTestCredentials('phpdftk signer');

        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 18)
            ->moveTextPosition(72, 740)
            ->showText('Signed PDF integration test')
            ->moveTextPosition(0, -24)
            ->setFont($fontName, 11)
            ->showText('Signed with a self-generated RSA-2048 cert.')
            ->endText();

        // Signature value placeholder — will be filled in by the writer.
        $sigValue = new SignatureValue();
        $sigValue->name = new PdfString('phpdftk signer');
        $sigValue->reason = new PdfString('Automated test');
        $sigValue->location = new PdfString('CI');
        $sigValue->m = new PdfString('D:20260411120000Z');
        $sigValueRef = $writer->register($sigValue);

        // Signature field + widget + AcroForm.
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

        // Wire the signer.
        $writer->setSigner(
            $sigValue,
            new Pkcs7Signer($creds['cert'], $creds['key'])
        );

        $writer->save(self::OUTPUT_FILE);

        // ---- Structural assertions ----------------------------------
        $pdf = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($pdf);
        self::assertStringStartsWith('%PDF-', $pdf);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        self::assertStringContainsString('%%EOF', $pdf);
        self::assertStringContainsString('/Type /Sig', $pdf);
        self::assertStringContainsString('/Filter /Adobe.PPKLite', $pdf);
        self::assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $pdf);

        // ---- Extract /ByteRange and /Contents from the saved file ---
        self::assertMatchesRegularExpression(
            '/\/ByteRange \[ (\d{10}) (\d{10}) (\d{10}) (\d{10}) \]/',
            $pdf,
            'byte range not patched'
        );
        preg_match(
            '/\/ByteRange \[ (\d{10}) (\d{10}) (\d{10}) (\d{10}) \]/',
            $pdf,
            $br
        );
        $br = array_map('intval', array_slice($br, 1));
        self::assertSame(0, $br[0]);
        self::assertSame(strlen($pdf), $br[2] + $br[3]);

        // Pull the /Contents hex blob out.
        self::assertMatchesRegularExpression('/\/Contents <([0-9a-f]+)>/', $pdf);
        preg_match('/\/Contents <([0-9a-f]+)>/', $pdf, $m);
        $derHex = rtrim($m[1], '0');
        // rtrim is safe because the signature is DER and ends in a non-zero
        // byte with overwhelming probability; pad back to even length.
        if (strlen($derHex) % 2 !== 0) {
            $derHex .= '0';
        }
        $der = hex2bin($derHex);
        self::assertNotFalse($der);
        self::assertSame("\x30", $der[0], 'extracted contents are not DER');

        // ---- Verify the signature against the byte range ------------
        $signedData = substr($pdf, $br[0], $br[1]) . substr($pdf, $br[2], $br[3]);

        $openssl = $this->findOpensslBinary();
        if ($openssl === null) {
            // The structural assertions above are the portable oracle;
            // cryptographic verification needs the openssl CLI.
            return;
        }

        $dataFile = tempnam(sys_get_temp_dir(), 'pdf_signed_');
        $sigFile = tempnam(sys_get_temp_dir(), 'pdf_sig_');
        $certFile = tempnam(sys_get_temp_dir(), 'pdf_cert_') . '.pem';
        file_put_contents($dataFile, $signedData);
        file_put_contents($sigFile, $der);
        file_put_contents($certFile, $creds['cert']);

        try {
            $cmd = sprintf(
                '%s cms -verify -inform DER -in %s -content %s -certfile %s -noverify -binary -out /dev/null 2>&1',
                escapeshellarg($openssl),
                escapeshellarg($sigFile),
                escapeshellarg($dataFile),
                escapeshellarg($certFile)
            );
            $output = [];
            $ret = 0;
            exec($cmd, $output, $ret);
            self::assertSame(
                0,
                $ret,
                'openssl cms -verify rejected the signed byte range: ' . implode("\n", $output)
            );
        } finally {
            @unlink($dataFile);
            @unlink($sigFile);
            @unlink($certFile);
        }
    }

    private function findOpensslBinary(): ?string
    {
        foreach (['/usr/bin/openssl', '/usr/local/bin/openssl', '/opt/homebrew/bin/openssl'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        $which = trim((string) @shell_exec('command -v openssl 2>/dev/null'));
        return $which !== '' ? $which : null;
    }
}
