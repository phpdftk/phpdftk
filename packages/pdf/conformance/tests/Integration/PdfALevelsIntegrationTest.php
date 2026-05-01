<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Conformance\Tests\Integration;

use ApprLabs\Pdf\Conformance\ConformanceException;
use ApprLabs\Pdf\Conformance\Profile\PdfAProfile;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Document\MarkInfo;
use ApprLabs\Pdf\Core\Document\OutputIntent;
use ApprLabs\Pdf\Core\Document\StructElem;
use ApprLabs\Pdf\Core\Document\StructTreeRoot;
use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

class PdfALevelsIntegrationTest extends TestCase
{
    private const OUTPUT_DIR = __DIR__ . '/../../tests/output';

    protected function setUp(): void
    {
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0o755, true);
        }
    }

    private function findFont(): string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Georgia.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $this->markTestSkipped('No TTF font found on this system');
    }

    private function findIccProfile(): string
    {
        foreach ([
            '/System/Library/ColorSync/Profiles/sRGB Profile.icc',
            '/usr/share/color/icc/colord/sRGB.icc',
            '/usr/share/color/icc/sRGB.icc',
        ] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $this->markTestSkipped('No sRGB ICC profile found on this system');
    }

    private function addOutputIntent(PdfWriter $writer): void
    {
        $iccData = file_get_contents($this->findIccProfile());
        $iccStream = new PdfStream(new PdfDictionary(), $iccData);
        $iccStream->dictionary->set('N', new PdfNumber(3));
        $iccRef = $writer->register($iccStream);

        $outputIntent = new OutputIntent('GTS_PDFA1', 'sRGB IEC61966-2.1');
        $outputIntent->registryName = new PdfString('http://www.color.org');
        $outputIntent->info = new PdfString('sRGB IEC61966-2.1');
        $outputIntent->destOutputProfile = $iccRef;
        $oiRef = $writer->register($outputIntent);
        $writer->getCatalog()->outputIntents = new PdfArray([$oiRef]);
    }

    /**
     * PDF/A-1a requires tagged structure — missing it should fail.
     */
    public function testA1aFailsWithoutTaggedStructure(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1a, strict: false);

        $info = new Info();
        $info->title = new PdfString('A1a Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);
        $this->addOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)->showText('A1a test')->endText();

        $writer->generate();

        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);

        // Should have tagged structure violations
        $errorClauses = array_map(fn($v) => $v->clause, $results[0]->getErrors());
        self::assertContains('6.8.1', $errorClauses); // MarkInfo
        self::assertContains('6.8.2', $errorClauses); // StructTreeRoot
        self::assertContains('6.8.4', $errorClauses); // Lang
    }

    /**
     * PDF/A-1a passes when tagged structure is present.
     */
    public function testA1aPassesWithTaggedStructure(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1a);

        $info = new Info();
        $info->title = new PdfString('A1a Tagged Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);
        $this->addOutputIntent($writer);

        // Set up tagged structure
        $catalog = $writer->getCatalog();

        $markInfo = new MarkInfo();
        $markInfo->marked = true;
        $catalog->markInfo = $markInfo;
        $catalog->lang = new PdfString('en-US');

        $structRoot = new StructTreeRoot();
        $writer->register($structRoot);
        $catalog->structTreeRoot = new PdfReference($structRoot->objectNumber);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)->showText('A1a tagged test')->endText();

        $outPath = self::OUTPUT_DIR . '/pdfa1a_tagged.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    /**
     * PDF/A-2b allows transparency (unlike A-1b).
     */
    public function testA2bAllowsTransparency(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A2b);

        $info = new Info();
        $info->title = new PdfString('A2b Transparency Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);
        $this->addOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)->showText('A2b test')->endText();

        $outPath = self::OUTPUT_DIR . '/pdfa2b_compliance.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        $results = $writer->getConformanceResults();
        self::assertTrue($results[0]->isCompliant);
    }

    /**
     * PDF/A-2b generates correct XMP identification.
     */
    public function testA2bXmpIdentification(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A2b, strict: false);

        $info = new Info();
        $info->title = new PdfString('A2b XMP Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();

        // Should contain part=2 and conformance=B
        self::assertStringContainsString('pdfaid:part', $pdf);
        self::assertStringContainsString('>2<', $pdf);
        self::assertStringContainsString('>B<', $pdf);
    }

    /**
     * PDF/A-3b should also pass with embedded font and OutputIntent.
     */
    public function testA3bGeneratesSuccessfully(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A3b);

        $info = new Info();
        $info->title = new PdfString('A3b Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);
        $this->addOutputIntent($writer);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)->showText('A3b test')->endText();

        $outPath = self::OUTPUT_DIR . '/pdfa3b_compliance.pdf';
        $writer->save($outPath);

        self::assertFileExists($outPath);
        self::assertTrue($writer->getConformanceResults()[0]->isCompliant);
    }

    /**
     * PDF/A-1b should fail with a JavaScript action.
     */
    public function testA1bFailsWithJavaScript(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A1b, strict: false);

        $info = new Info();
        $info->title = new PdfString('JS Test');
        $info->producer = new PdfString('phpdftk');
        $writer->setInfo($info);
        $this->addOutputIntent($writer);

        // Register a JavaScript action
        $js = new \ApprLabs\Pdf\Core\Action\JavaScriptAction(new PdfString('alert("hi")'));
        $writer->register($js);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $fontHandle = $writer->addFont($font, $page);
        $cs = $writer->addContentStream($page);
        $cs->beginText()->setFont($fontHandle->getResourceName(), 12)
            ->moveTextPosition(72, 720)->showText('JS test')->endText();

        $writer->generate();

        $results = $writer->getConformanceResults();
        self::assertFalse($results[0]->isCompliant);

        $errorClauses = array_map(fn($v) => $v->clause, $results[0]->getErrors());
        self::assertContains('6.6.1', $errorClauses);
    }

    /**
     * PDF/A-2u XMP should contain conformance=U.
     */
    public function testA2uXmpConformanceLevel(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A2u, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();

        self::assertStringContainsString('>U<', $pdf);
    }

    /**
     * PDF/A-4 should use PDF 2.0 version.
     */
    public function testA4PinsVersionTo2_0(): void
    {
        $writer = new PdfWriter();
        $writer->setConformance(PdfAProfile::A4, strict: false);

        $page = $writer->addPage(612, 792);
        $font = TrueTypeFont::fromFile($this->findFont());
        $writer->addFont($font, $page);

        $pdf = $writer->generate();

        self::assertStringStartsWith('%PDF-2.0', $pdf);
    }
}
