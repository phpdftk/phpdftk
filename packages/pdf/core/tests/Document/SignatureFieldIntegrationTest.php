<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Tests\Document;

use ApprLabs\Pdf\Core\Annotation\WidgetAnnotation;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Interactive\Form\AcroForm;
use ApprLabs\Pdf\Core\Interactive\Form\SignatureField;
use ApprLabs\Pdf\Core\Interactive\Signature\DocMDPTransformParams;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureReference;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureValue;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Generates a real PDF containing:
 *   - A SignatureField with a SignatureValue placeholder
 *   - A SignatureReference pointing at DocMDPTransformParams (certification)
 *   - A WidgetAnnotation on the page providing the visible signature box
 *   - An AcroForm carrying /SigFlags 3 (SignaturesExist + AppendOnly)
 *   - Catalog /Perms referencing the signature for DocMDP enforcement
 *
 * This exercises the signature object graph end-to-end without performing
 * any actual cryptographic signing — /Contents is the zeroed placeholder.
 */
class SignatureFieldIntegrationTest extends TestCase
{
    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/signature_field.pdf';

    public function testGeneratesSignatureFieldPdf(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 18)
            ->moveTextPosition(72, 740)
            ->showText('Signature field integration')
            ->moveTextPosition(0, -24)
            ->setFont($fontName, 11)
            ->showText('The box below is a signable area (placeholder signature).')
            ->endText();

        // ----- DocMDP params + signature reference ----------------------
        $docMdp = new DocMDPTransformParams(p: 2);
        $docMdpRef = $writer->register($docMdp);

        $sigRef = new SignatureReference('DocMDP');
        $sigRef->transformParams = $docMdpRef;
        $sigRef->digestMethod = new PdfName('SHA256');
        $sigRefRef = $writer->register($sigRef);

        // ----- SignatureValue placeholder -------------------------------
        $sigValue = new SignatureValue();
        $sigValue->name = new PdfString('Integration Test');
        $sigValue->reason = new PdfString('Certification');
        $sigValue->location = new PdfString('phpdftk tests');
        $sigValue->m = new PdfString('D:20260410120000Z');
        $sigValue->reference = new PdfArray([$sigRefRef]);
        // Placeholder ByteRange — real signers recompute this.
        $sigValue->byteRange = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber(0), new PdfNumber(0),
        ]);
        $sigValueRef = $writer->register($sigValue);

        // ----- SignatureField + WidgetAnnotation ------------------------
        $field = new SignatureField();
        $field->t = new PdfString('Signature1');
        $field->tu = new PdfString('Certifying signature');
        $field->setSignatureValue($sigValueRef);
        $fieldRef = $writer->register($field);

        $widget = new WidgetAnnotation(new PdfArray([
            new PdfNumber(72), new PdfNumber(600),
            new PdfNumber(320), new PdfNumber(680),
        ]));
        $widget->parent = $fieldRef;
        $widgetRef = $writer->register($widget);
        $page->corePage()->annots[] = $widgetRef;

        // ----- AcroForm -------------------------------------------------
        $acroForm = new AcroForm();
        $acroForm->fields = [$fieldRef];
        $acroForm->sigFlags = 3;  // SignaturesExist (1) | AppendOnly (2)
        $acroFormRef = $writer->register($acroForm);
        $writer->getCatalog()->acroForm = $acroFormRef;

        // ----- Catalog /Perms /DocMDP -----------------------------------
        $writer->getCatalog()->perms = new PdfDictionary([
            'DocMDP' => $sigValueRef,
        ]);

        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $contents = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($contents);
        self::assertStringStartsWith('%PDF-', $contents);
        self::assertStringContainsString('/FT /Sig', $contents);
        self::assertStringContainsString('/Type /Sig', $contents);
        self::assertStringContainsString('/Filter /Adobe.PPKLite', $contents);
        self::assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $contents);
        self::assertStringContainsString('/ByteRange', $contents);
        self::assertStringContainsString('/Type /SigRef', $contents);
        self::assertStringContainsString('/TransformMethod /DocMDP', $contents);
        self::assertStringContainsString('/Type /TransformParams', $contents);
        self::assertStringContainsString('/DigestMethod /SHA256', $contents);
        self::assertStringContainsString('/SigFlags 3', $contents);
        self::assertStringContainsString('/Perms', $contents);
        self::assertStringContainsString('/DocMDP', $contents);
        self::assertStringContainsString('%%EOF', $contents);
    }
}
