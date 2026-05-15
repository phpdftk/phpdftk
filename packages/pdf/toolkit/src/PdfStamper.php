<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

use Phpdftk\Encoding\WinAnsiEncoder;
use Phpdftk\ImageMetadata\ImageParser;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Filesystem\LocalFilesystem;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\Graphics\ExtGState;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Internal\PageResolver;
use Phpdftk\Pdf\Toolkit\Stamper\ImageStampStyle;
use Phpdftk\Pdf\Toolkit\Stamper\StampPosition;
use Phpdftk\Pdf\Toolkit\Stamper\StampStyle;
use Phpdftk\Pdf\Toolkit\Stamper\WatermarkStyle;

/**
 * Add text overlays, watermarks, page numbers, headers and footers to PDFs.
 *
 * Usage:
 *   PdfStamper::open('report.pdf')
 *       ->watermark('DRAFT')
 *       ->addPageNumbers(StampPosition::BottomCenter)
 *       ->save('stamped.pdf');
 *
 * @api
 */
final class PdfStamper
{
    private string $originalBytes;

    /** @var list<array{type: string, args: array}> */
    private array $operations = [];

    /** @var list<string> */
    private array $lastVersionWarnings = [];

    private function __construct(
        private readonly PdfReader $reader,
        string $originalBytes,
    ) {
        $this->originalBytes = $originalBytes;
    }

    public static function open(string $path, string $password = ''): self
    {
        $bytes = LocalFilesystem::readFile($path);
        return new self(PdfReader::fromString($bytes, $password), $bytes);
    }

    public static function openString(string $pdfBytes, string $password = ''): self
    {
        return new self(PdfReader::fromString($pdfBytes, $password), $pdfBytes);
    }

    // -----------------------------------------------------------------------
    // Stamp operations
    // -----------------------------------------------------------------------

    public function stampText(
        string $text,
        StampPosition $position,
        ?PageSelector $pages = null,
        ?StampStyle $style = null,
    ): self {
        $this->operations[] = ['type' => 'text', 'args' => compact('text', 'position', 'pages', 'style')];
        return $this;
    }

    public function watermark(
        string $text,
        ?PageSelector $pages = null,
        ?WatermarkStyle $style = null,
    ): self {
        $this->operations[] = ['type' => 'watermark', 'args' => compact('text', 'pages', 'style')];
        return $this;
    }

    public function addPageNumbers(
        StampPosition $position = StampPosition::BottomCenter,
        string $format = 'Page {n} of {total}',
        ?StampStyle $style = null,
        ?PageSelector $pages = null,
    ): self {
        $this->operations[] = ['type' => 'pageNumbers', 'args' => compact('position', 'format', 'style', 'pages')];
        return $this;
    }

    public function header(string $text, ?StampStyle $style = null, ?PageSelector $pages = null): self
    {
        return $this->stampText($text, StampPosition::TopCenter, $pages, $style);
    }

    public function footer(string $text, ?StampStyle $style = null, ?PageSelector $pages = null): self
    {
        return $this->stampText($text, StampPosition::BottomCenter, $pages, $style);
    }

    /**
     * Overlay a JPEG or PNG image at a given position on selected pages.
     *
     * Dimensions default to the image's native pixel size (at 72 DPI).
     * Set width or height in the style to scale; setting one preserves
     * the aspect ratio. Set both to stretch.
     */
    public function stampImage(
        string $imagePath,
        StampPosition $position,
        ?PageSelector $pages = null,
        ?ImageStampStyle $style = null,
    ): self {
        if (!is_file($imagePath)) {
            throw new \RuntimeException("Image file not found: $imagePath");
        }
        $this->operations[] = ['type' => 'image', 'args' => compact('imagePath', 'position', 'pages', 'style')];
        return $this;
    }

    /**
     * Overlay a page from another PDF at a given position on selected pages.
     *
     * The source page is imported as a Form XObject. Dimensions default to
     * the source page's MediaBox size. Use width/height in the style to scale.
     *
     * @param string $pdfPath   Path to the source PDF file
     * @param int    $pageIndex 0-based page index in the source PDF
     */
    public function stampPdf(
        string $pdfPath,
        int $pageIndex = 0,
        ?StampPosition $position = null,
        ?PageSelector $pages = null,
        ?ImageStampStyle $style = null,
    ): self {
        if (!is_file($pdfPath)) {
            throw new \RuntimeException("PDF file not found: $pdfPath");
        }
        $sourceReader = PdfReader::fromFile($pdfPath);
        if ($pageIndex < 0 || $pageIndex >= $sourceReader->getPageCount()) {
            throw new \InvalidArgumentException(sprintf(
                'Page index %d out of range (source has %d pages)',
                $pageIndex,
                $sourceReader->getPageCount(),
            ));
        }
        $position ??= StampPosition::Center;
        $this->operations[] = ['type' => 'pdf', 'args' => compact('sourceReader', 'pageIndex', 'position', 'pages', 'style')];
        return $this;
    }

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    public function save(string $path): void
    {
        LocalFilesystem::writeFile($path, $this->toBytes(), createDirectories: true);
    }

    public function toBytes(): string
    {
        if (empty($this->operations)) {
            return $this->originalBytes;
        }

        $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);
        $pageRefs = PageResolver::getPageReferences($this->reader);
        $totalPages = count($pageRefs);

        // Register a standard font for text stamps (only when needed)
        $fontRef = null;
        $fontName = 'StF1';
        $needsFont = false;
        foreach ($this->operations as $op) {
            if (in_array($op['type'], ['text', 'watermark', 'pageNumbers'], true)) {
                $needsFont = true;
                break;
            }
        }
        if ($needsFont) {
            $font = new Type1Font(StandardFont::Helvetica);
            $fontRef = $writer->addNewObject($font);
        }

        // Pre-create shared ExtGState for opacity if needed
        $gsRefs = [];

        // Pre-register XObject resources that are shared across pages
        $xObjectCounter = 0;
        /** @var array<string, PdfReference> $xObjectRefs  xoName => ref */
        $xObjectRefs = [];

        // Pre-process image and PDF operations to register XObjects once
        foreach ($this->operations as $idx => $op) {
            if ($op['type'] === 'image') {
                $xObjectCounter++;
                $xoName = 'StXo' . $xObjectCounter;
                $xoRef = $this->registerImageXObject($writer, $op['args']['imagePath']);
                $xObjectRefs[$xoName] = $xoRef;
                $this->operations[$idx]['xoName'] = $xoName;

                $info = ImageParser::parse($op['args']['imagePath']);
                $this->operations[$idx]['sourceWidth'] = (float) $info->width;
                $this->operations[$idx]['sourceHeight'] = (float) $info->height;
            } elseif ($op['type'] === 'pdf') {
                $xObjectCounter++;
                $xoName = 'StXo' . $xObjectCounter;
                $sourceReader = $op['args']['sourceReader'];
                $pageIndex = $op['args']['pageIndex'];
                $sourcePageDict = $sourceReader->getPage($pageIndex);
                $sourceDims = PageResolver::getPageDimensions($sourcePageDict, $sourceReader);

                $xoRef = $this->registerPdfPageXObject($writer, $sourceReader, $pageIndex, $sourceDims);
                $xObjectRefs[$xoName] = $xoRef;
                $this->operations[$idx]['xoName'] = $xoName;
                $this->operations[$idx]['sourceWidth'] = $sourceDims['width'];
                $this->operations[$idx]['sourceHeight'] = $sourceDims['height'];
            }
        }

        // Collect stamp content per page
        /** @var array<int, list<string>> $pageOps  0-indexed page => list of operator strings */
        $pageOps = [];
        /** @var array<int, array<string, PdfReference>> $pageExtGState */
        $pageExtGState = [];
        /** @var array<int, array<string, PdfReference>> $pageXObjects */
        $pageXObjects = [];

        foreach ($this->operations as $op) {
            for ($i = 0; $i < $totalPages; $i++) {
                $pageNum = $i + 1;
                $selector = $op['args']['pages'] ?? null;
                if ($selector !== null && !$selector->matches($pageNum, $totalPages)) {
                    continue;
                }

                $pageDict = $this->reader->getPage($i);
                $dims = PageResolver::getPageDimensions($pageDict, $this->reader);

                $ops = match ($op['type']) {
                    'text' => $this->buildTextOps(
                        $op['args']['text'],
                        $op['args']['position'],
                        $op['args']['style'] ?? new StampStyle(),
                        $dims,
                        $fontName,
                    ),
                    'watermark' => $this->buildWatermarkOps(
                        $op['args']['text'],
                        $op['args']['style'] ?? new WatermarkStyle(),
                        $dims,
                        $fontName,
                    ),
                    'pageNumbers' => $this->buildTextOps(
                        str_replace(['{n}', '{total}'], [(string) $pageNum, (string) $totalPages], $op['args']['format']),
                        $op['args']['position'],
                        $op['args']['style'] ?? new StampStyle(fontSize: 10.0),
                        $dims,
                        $fontName,
                    ),
                    'image', 'pdf' => $this->buildXObjectOps(
                        $op['xoName'],
                        $op['args']['position'],
                        $op['args']['style'] ?? new ImageStampStyle(),
                        $dims,
                        $op['sourceWidth'],
                        $op['sourceHeight'],
                    ),
                    default => [],
                };

                if (!empty($ops)) {
                    $pageOps[$i] = array_merge($pageOps[$i] ?? [], $ops['operators']);
                    if (isset($ops['extGState'])) {
                        foreach ($ops['extGState'] as $gsName => $opacity) {
                            if (!isset($gsRefs[$gsName])) {
                                $gs = new ExtGState();
                                $gs->ca = $opacity;
                                $gs->caLower = $opacity;
                                $gsRefs[$gsName] = $writer->addNewObject($gs);
                            }
                            $pageExtGState[$i][$gsName] = $gsRefs[$gsName];
                        }
                    }
                    if (isset($ops['xObjects'])) {
                        foreach ($ops['xObjects'] as $xoName) {
                            $pageXObjects[$i][$xoName] = $xObjectRefs[$xoName];
                        }
                    }
                }
            }
        }

        // For each page with stamps, create content stream and modify page
        foreach ($pageOps as $pageIdx => $operators) {
            $cs = new ContentStream();
            $cs->raw(implode("\n", $operators));

            // Build resources for this content stream
            $resources = new Resources();
            if ($fontRef !== null) {
                $resources->addFont($fontName, $fontRef);
            }
            foreach ($pageExtGState[$pageIdx] ?? [] as $gsName => $gsRef) {
                $resources->addExtGState($gsName, $gsRef);
            }
            foreach ($pageXObjects[$pageIdx] ?? [] as $xoName => $xoRef) {
                $resources->addXObject($xoName, $xoRef);
            }

            $csRef = $writer->addNewObject($cs);

            // Modify the page to include the new content stream
            $pageDict = $this->reader->getPage($pageIdx);
            $existingContents = $pageDict->get('Contents');
            $contentsArray = [];
            if ($existingContents instanceof PdfReference) {
                $contentsArray[] = $existingContents;
            } elseif ($existingContents instanceof PdfArray) {
                $contentsArray = $existingContents->items;
            }
            $contentsArray[] = $csRef;

            $pageDict->set('Contents', new PdfArray($contentsArray));

            // Merge resources: add font, extgstate, xobjects to existing page resources
            $existingRes = $pageDict->get('Resources');
            if ($existingRes instanceof PdfDictionary) {
                // Add font
                if ($fontRef !== null) {
                    $fontDict = $existingRes->get('Font');
                    if ($fontDict instanceof PdfDictionary) {
                        $fontDict->set($fontName, $fontRef);
                    } else {
                        $existingRes->set('Font', (new PdfDictionary())->set($fontName, $fontRef));
                    }
                }
                // Add ExtGState
                foreach ($pageExtGState[$pageIdx] ?? [] as $gsName => $gsRef) {
                    $gsDict = $existingRes->get('ExtGState');
                    if ($gsDict instanceof PdfDictionary) {
                        $gsDict->set($gsName, $gsRef);
                    } else {
                        $existingRes->set('ExtGState', (new PdfDictionary())->set($gsName, $gsRef));
                    }
                }
                // Add XObject
                foreach ($pageXObjects[$pageIdx] ?? [] as $xoName => $xoRef) {
                    $xoDict = $existingRes->get('XObject');
                    if ($xoDict instanceof PdfDictionary) {
                        $xoDict->set($xoName, $xoRef);
                    } else {
                        $existingRes->set('XObject', (new PdfDictionary())->set($xoName, $xoRef));
                    }
                }
            } else {
                // No existing resources dict — build inline resource dict
                $resDict = new PdfDictionary();
                if ($fontRef !== null) {
                    $resDict->set('Font', (new PdfDictionary())->set($fontName, $fontRef));
                }
                foreach ($pageExtGState[$pageIdx] ?? [] as $gsName => $gsRef) {
                    $gsDictEntry = $resDict->get('ExtGState');
                    if (!$gsDictEntry instanceof PdfDictionary) {
                        $gsDictEntry = new PdfDictionary();
                        $resDict->set('ExtGState', $gsDictEntry);
                    }
                    $gsDictEntry->set($gsName, $gsRef);
                }
                foreach ($pageXObjects[$pageIdx] ?? [] as $xoName => $xoRef) {
                    $xoDictEntry = $resDict->get('XObject');
                    if (!$xoDictEntry instanceof PdfDictionary) {
                        $xoDictEntry = new PdfDictionary();
                        $resDict->set('XObject', $xoDictEntry);
                    }
                    $xoDictEntry->set($xoName, $xoRef);
                }
                $pageDict->set('Resources', $resDict);
            }

            // Create a PdfObject wrapper for the modified page
            $pageObj = new class ($pageDict) extends PdfObject {
                public function __construct(private readonly PdfDictionary $dict) {}
                public function toPdf(): string
                {
                    return $this->dict->toPdf();
                }
            };
            $pageObj->objectNumber = $pageRefs[$pageIdx]->objectNumber;
            $pageObj->generationNumber = 0;
            $writer->addModifiedObject($pageObj);
        }

        $result = $writer->generate();
        $this->lastVersionWarnings = $writer->getVersionWarnings();
        return $result;
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->lastVersionWarnings;
    }

    public function getReader(): PdfReader
    {
        return $this->reader;
    }

    public function getPageCount(): int
    {
        return $this->reader->getPageCount();
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    /**
     * @return array{operators: list<string>, extGState?: array<string, float>}
     */
    private function buildTextOps(
        string $text,
        StampPosition $position,
        StampStyle $style,
        array $dims,
        string $fontName,
    ): array {
        $textWidth = strlen($text) * $style->fontSize * 0.5; // approximate
        $textHeight = $style->fontSize;
        [$x, $y] = $position->computeCoordinates(
            $dims['width'],
            $dims['height'],
            $textWidth,
            $textHeight,
        );

        $escaped = $this->escapeText($text);
        $operators = ['q'];

        $extGState = [];
        if ($style->opacity < 1.0) {
            $gsName = 'GsStamp' . (int) ($style->opacity * 100);
            $operators[] = "/$gsName gs";
            $extGState[$gsName] = $style->opacity;
        }

        $operators[] = sprintf('%.3f %.3f %.3f rg', $style->r, $style->g, $style->b);
        $operators[] = 'BT';
        $operators[] = sprintf('/%s %.1f Tf', $fontName, $style->fontSize);
        $operators[] = sprintf('%.2f %.2f Td', $x, $y);
        $operators[] = sprintf('(%s) Tj', $escaped);
        $operators[] = 'ET';
        $operators[] = 'Q';

        return ['operators' => $operators, 'extGState' => $extGState];
    }

    /**
     * @return array{operators: list<string>, extGState?: array<string, float>}
     */
    private function buildWatermarkOps(
        string $text,
        WatermarkStyle $style,
        array $dims,
        string $fontName,
    ): array {
        $cx = $dims['width'] / 2;
        $cy = $dims['height'] / 2;
        $rad = deg2rad($style->rotation);
        $cos = cos($rad);
        $sin = sin($rad);

        $escaped = $this->escapeText($text);
        $textWidth = strlen($text) * $style->fontSize * 0.5;

        $operators = ['q'];

        $gsName = 'GsWm' . (int) ($style->opacity * 100);
        $extGState = [$gsName => $style->opacity];
        $operators[] = "/$gsName gs";

        $operators[] = sprintf('%.3f %.3f %.3f rg', $style->r, $style->g, $style->b);
        $operators[] = 'BT';
        $operators[] = sprintf('/%s %.1f Tf', $fontName, $style->fontSize);
        // Position: translate to center, then apply rotation matrix
        $operators[] = sprintf(
            '%.4f %.4f %.4f %.4f %.2f %.2f Tm',
            $cos,
            $sin,
            -$sin,
            $cos,
            $cx - ($textWidth * $cos / 2),
            $cy - ($textWidth * $sin / 2),
        );
        $operators[] = sprintf('(%s) Tj', $escaped);
        $operators[] = 'ET';
        $operators[] = 'Q';

        return ['operators' => $operators, 'extGState' => $extGState];
    }

    private function escapeText(string $text): string
    {
        // The stamper always renders with Helvetica (WinAnsi), so convert
        // UTF-8 input to its WinAnsi byte form before escaping reserved
        // characters. Without this, an em dash would emit three WinAnsi
        // glyphs (â€") instead of one.
        $text = (new WinAnsiEncoder())->encode($text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /**
     * Build content stream operators to render an XObject at a position.
     *
     * @return array{operators: list<string>, extGState?: array<string, float>, xObjects?: list<string>}
     */
    private function buildXObjectOps(
        string $xoName,
        StampPosition $position,
        ImageStampStyle $style,
        array $dims,
        float $sourceWidth,
        float $sourceHeight,
    ): array {
        // Compute display dimensions
        if ($style->width !== null && $style->height !== null) {
            $displayWidth = $style->width;
            $displayHeight = $style->height;
        } elseif ($style->width !== null) {
            $displayWidth = $style->width;
            $displayHeight = $sourceHeight * ($style->width / $sourceWidth);
        } elseif ($style->height !== null) {
            $displayHeight = $style->height;
            $displayWidth = $sourceWidth * ($style->height / $sourceHeight);
        } else {
            $displayWidth = $sourceWidth;
            $displayHeight = $sourceHeight;
        }

        [$x, $y] = $position->computeCoordinates(
            $dims['width'],
            $dims['height'],
            $displayWidth,
            $displayHeight,
        );

        $operators = ['q'];
        $extGState = [];

        if ($style->opacity < 1.0) {
            $gsName = 'GsStamp' . (int) ($style->opacity * 100);
            $operators[] = "/$gsName gs";
            $extGState[$gsName] = $style->opacity;
        }

        // cm operator: scale and translate the XObject
        $operators[] = sprintf(
            '%.4f 0 0 %.4f %.4f %.4f cm',
            $displayWidth,
            $displayHeight,
            $x,
            $y,
        );
        $operators[] = "/$xoName Do";
        $operators[] = 'Q';

        $result = ['operators' => $operators, 'xObjects' => [$xoName]];
        if (!empty($extGState)) {
            $result['extGState'] = $extGState;
        }
        return $result;
    }

    /**
     * Register a JPEG/PNG image as an ImageXObject in the incremental writer.
     */
    private function registerImageXObject(IncrementalWriter $writer, string $imagePath): PdfReference
    {
        $info = ImageParser::parse($imagePath);
        $data = LocalFilesystem::readFile($imagePath);

        $dict = new PdfDictionary([
            'Type'             => new PdfName('XObject'),
            'Subtype'          => new PdfName('Image'),
            'Width'            => new PdfNumber($info->width),
            'Height'           => new PdfNumber($info->height),
            'ColorSpace'       => new PdfName($info->colorSpace),
            'BitsPerComponent' => new PdfNumber($info->bitsPerComponent),
        ]);

        // Set the appropriate decode filter for pass-through formats
        match ($info->format) {
            'jpeg' => $dict->set('Filter', new PdfName('DCTDecode')),
            'jpeg2000' => $dict->set('Filter', new PdfName('JPXDecode')),
            default => null,
        };

        $xObject = new PdfStream($dict, $data);
        return $writer->addNewObject($xObject);
    }

    /**
     * Import a page from a source PDF as a Form XObject.
     *
     * Extracts the page's content streams and resources, wrapping them in
     * a single Form XObject that can be rendered via the Do operator.
     */
    private function registerPdfPageXObject(
        IncrementalWriter $writer,
        PdfReader $sourceReader,
        int $pageIndex,
        array $sourceDims,
    ): PdfReference {
        $sourcePageDict = $sourceReader->getPage($pageIndex);

        // Collect content stream data
        $contentData = '';
        $contents = $sourcePageDict->get('Contents');
        if ($contents instanceof PdfReference) {
            $obj = $sourceReader->resolveReference($contents);
            if ($obj instanceof PdfStream) {
                $contentData = $obj->data;
            } elseif ($obj instanceof PdfDictionary) {
                $contentData = '';
            }
        } elseif ($contents instanceof PdfArray) {
            foreach ($contents->items as $ref) {
                if ($ref instanceof PdfReference) {
                    $obj = $sourceReader->resolveReference($ref);
                    if ($obj instanceof PdfStream) {
                        $contentData .= $obj->data . "\n";
                    }
                }
            }
        }

        $bBox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber($sourceDims['width']), new PdfNumber($sourceDims['height']),
        ]);

        $formXObject = new FormXObject($bBox, $contentData);

        // Copy resources from the source page
        $sourceResources = $sourcePageDict->get('Resources');
        if ($sourceResources instanceof PdfReference) {
            $sourceResources = $sourceReader->resolveReference($sourceResources);
        }
        if ($sourceResources instanceof PdfDictionary) {
            $formXObject->resources = $sourceResources;
        }

        return $writer->addNewObject($formXObject);
    }
}
