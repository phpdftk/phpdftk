<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Tests\Document;

use Phpdftk\Pdf\Core\Action\SubmitFormAction;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\FileSpec\EmbeddedFile;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\DeviceRGB;
use Phpdftk\Pdf\Core\Graphics\Function\FunctionType2;
use Phpdftk\Pdf\Core\Graphics\Pattern\ShadingPattern;
use Phpdftk\Pdf\Core\Graphics\Pattern\TilingPattern;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType2;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType3;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test that builds a real PDF exercising:
 *   - Type 2 (axial) shading via a shading pattern
 *   - Type 3 (radial) shading via a shading pattern
 *   - Type 1 tiling pattern
 *   - File attachment via FileSpec + EmbeddedFile stream
 *   - SubmitFormAction on the catalog open action
 */
#[Group("qpdf")]
class GraphicsPipelineIntegrationTest extends TestCase
{
    use QpdfValidationTrait;

    private const OUTPUT_FILE = __DIR__ . '/../../../../../docs/sample-pdfs/graphics_pipeline.pdf';

    public function testGeneratesGraphicsPipelinePdf(): void
    {
        $writer = new PdfWriter();
        $page = $writer->addPage(612, 792);
        $fontName = $writer->addFont(new Type1Font(StandardFont::Helvetica))->getResourceName();

        // --------------------------------------------------------------
        // Axial + radial gradient ramps built from a Type 2 function.
        // --------------------------------------------------------------
        $rampRedToBlue = new FunctionType2(
            domain: new PdfArray([new PdfNumber(0), new PdfNumber(1)]),
            c0: new PdfArray([new PdfNumber(1), new PdfNumber(0), new PdfNumber(0)]),
            c1: new PdfArray([new PdfNumber(0), new PdfNumber(0), new PdfNumber(1)]),
            n: 1.0,
        );
        $rampRef = $writer->register($rampRedToBlue);

        $axial = new ShadingType2(
            new DeviceRGB(),
            new PdfArray([
                new PdfNumber(72), new PdfNumber(600),
                new PdfNumber(540), new PdfNumber(600),
            ]),
            $rampRef,
        );
        $axialRef = $writer->register($axial);

        $radial = new ShadingType3(
            new DeviceRGB(),
            new PdfArray([
                new PdfNumber(306), new PdfNumber(400), new PdfNumber(0),
                new PdfNumber(306), new PdfNumber(400), new PdfNumber(150),
            ]),
            $rampRef,
        );
        $radialRef = $writer->register($radial);

        // Shading patterns wrapping each shading.
        $axialPattern = new ShadingPattern($axialRef);
        $axialPatternRef = $writer->register($axialPattern);
        $radialPattern = new ShadingPattern($radialRef);
        $radialPatternRef = $writer->register($radialPattern);

        // --------------------------------------------------------------
        // Colored tiling pattern — a 20×20 green check with a red dot.
        // --------------------------------------------------------------
        $tilingStream =
            "0 0.6 0 rg\n0 0 20 20 re\nf\n" .
            "1 0 0 rg\n5 5 10 10 re\nf\n";
        $tiling = new TilingPattern(
            paintType: 1,
            tilingType: 1,
            bbox: new PdfArray([
                new PdfNumber(0), new PdfNumber(0),
                new PdfNumber(20), new PdfNumber(20),
            ]),
            xStep: 20,
            yStep: 20,
            resources: new Resources(),
            contentStream: $tilingStream,
        );
        $tilingRef = $writer->register($tiling);

        // Expose patterns as page resources.
        if ($page->corePage()->resources !== null) {
            $page->corePage()->resources->pattern = [
                'P1' => $axialPatternRef,
                'P2' => $radialPatternRef,
                'P3' => $tilingRef,
            ];
        }

        // --------------------------------------------------------------
        // Page content — fill three rectangles using the patterns.
        // --------------------------------------------------------------
        $cs = $writer->addContentStream($page);
        $cs->beginText()
            ->setFont($fontName, 18)
            ->moveTextPosition(72, 740)
            ->showText('Graphics pipeline integration')
            ->endText();

        // Axial gradient band
        $cs->raw('/Pattern cs');
        $cs->raw('/P1 scn');
        $cs->rectangle(72, 580, 468, 40)->fill();

        // Radial gradient circle bounded by a rectangle
        $cs->raw('/Pattern cs');
        $cs->raw('/P2 scn');
        $cs->rectangle(156, 250, 300, 300)->fill();

        // Tiling pattern fill
        $cs->raw('/Pattern cs');
        $cs->raw('/P3 scn');
        $cs->rectangle(72, 120, 468, 80)->fill();

        // --------------------------------------------------------------
        // Embedded file attached via FileSpec.
        // --------------------------------------------------------------
        $ef = new EmbeddedFile("hello from embedded file\n", 'text/plain');
        $efRef = $writer->register($ef);

        $spec = new FileSpec('readme.txt');
        $spec->desc = new PdfString('Sample text attachment');
        $spec->attachEmbeddedFile($efRef);
        $writer->register($spec);

        // --------------------------------------------------------------
        // SubmitFormAction on open — never actually fired at test time,
        // but exercises FileSpec + action wiring into the Catalog.
        // --------------------------------------------------------------
        $submitSpec = new FileSpec();
        $submitSpec->f = new PdfString('https://example.com/submit');
        $writer->register($submitSpec);
        $action = new SubmitFormAction(new PdfReference($submitSpec->objectNumber));
        $action->flags = 4;  // ExportFormat
        $actionRef = $writer->register($action);
        $writer->getCatalog()->openAction = $actionRef;

        $writer->save(self::OUTPUT_FILE);

        self::assertFileExists(self::OUTPUT_FILE);
        $this->assertQpdfValid(self::OUTPUT_FILE);
        $contents = file_get_contents(self::OUTPUT_FILE);
        self::assertNotFalse($contents);
        self::assertStringStartsWith('%PDF-', $contents);
        self::assertStringContainsString('/ShadingType 2', $contents);
        self::assertStringContainsString('/ShadingType 3', $contents);
        self::assertStringContainsString('/PatternType 1', $contents);
        self::assertStringContainsString('/PatternType 2', $contents);
        self::assertStringContainsString('/FunctionType 2', $contents);
        self::assertStringContainsString('/Type /EmbeddedFile', $contents);
        self::assertStringContainsString('/Type /Filespec', $contents);
        self::assertStringContainsString('/S /SubmitForm', $contents);
        self::assertStringContainsString('%%EOF', $contents);
    }
}
