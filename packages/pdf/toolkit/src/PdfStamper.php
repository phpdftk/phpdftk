<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit;

use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\File\IncrementalWriter;
use ApprLabs\Pdf\Core\Font\StandardFont;
use ApprLabs\Pdf\Core\Font\Type1Font;
use ApprLabs\Pdf\Core\Graphics\ExtGState;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\Internal\PageResolver;
use ApprLabs\Pdf\Toolkit\Stamper\StampPosition;
use ApprLabs\Pdf\Toolkit\Stamper\StampStyle;
use ApprLabs\Pdf\Toolkit\Stamper\WatermarkStyle;

/**
 * Add text overlays, watermarks, page numbers, headers and footers to PDFs.
 *
 * Usage:
 *   PdfStamper::open('report.pdf')
 *       ->watermark('DRAFT')
 *       ->addPageNumbers(StampPosition::BottomCenter)
 *       ->save('stamped.pdf');
 */
final class PdfStamper
{
    private string $originalBytes;

    /** @var list<array{type: string, args: array}> */
    private array $operations = [];

    private function __construct(
        private readonly PdfReader $reader,
        string $originalBytes,
    ) {
        $this->originalBytes = $originalBytes;
    }

    public static function open(string $path, string $password = ''): self
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Cannot read file: $path");
        }
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

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $this->toBytes());
    }

    public function toBytes(): string
    {
        if (empty($this->operations)) {
            return $this->originalBytes;
        }

        $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);
        $pageRefs = PageResolver::getPageReferences($this->reader);
        $totalPages = count($pageRefs);

        // Register a standard font for text stamps
        $font = new Type1Font(StandardFont::Helvetica);
        $fontRef = $writer->addNewObject($font);
        $fontName = 'StF1';

        // Pre-create shared ExtGState for opacity if needed
        $gsRefs = [];

        // Collect stamp content per page
        /** @var array<int, list<string>> $pageOps  0-indexed page => list of operator strings */
        $pageOps = [];
        /** @var array<int, array<string, PdfReference>> $pageExtGState */
        $pageExtGState = [];

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
                        $op['args']['text'], $op['args']['position'],
                        $op['args']['style'] ?? new StampStyle(),
                        $dims, $fontName,
                    ),
                    'watermark' => $this->buildWatermarkOps(
                        $op['args']['text'],
                        $op['args']['style'] ?? new WatermarkStyle(),
                        $dims, $fontName,
                    ),
                    'pageNumbers' => $this->buildTextOps(
                        str_replace(['{n}', '{total}'], [(string) $pageNum, (string) $totalPages], $op['args']['format']),
                        $op['args']['position'],
                        $op['args']['style'] ?? new StampStyle(fontSize: 10.0),
                        $dims, $fontName,
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
                }
            }
        }

        // For each page with stamps, create content stream and modify page
        foreach ($pageOps as $pageIdx => $operators) {
            $cs = new ContentStream();
            $cs->raw(implode("\n", $operators));

            // Build resources for this content stream
            $resources = new Resources();
            $resources->addFont($fontName, $fontRef);
            foreach ($pageExtGState[$pageIdx] ?? [] as $gsName => $gsRef) {
                $resources->addExtGState($gsName, $gsRef);
            }

            $cs->resources = $resources;
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

            // Merge resources: add font and extgstate to existing page resources
            $existingRes = $pageDict->get('Resources');
            if ($existingRes instanceof PdfDictionary) {
                // Add font
                $fontDict = $existingRes->get('Font');
                if ($fontDict instanceof PdfDictionary) {
                    $fontDict->set($fontName, $fontRef);
                } else {
                    $existingRes->set('Font', (new PdfDictionary())->set($fontName, $fontRef));
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
            } else {
                // No existing resources dict — use inline resource dict
                $resDict = new PdfDictionary();
                $resDict->set('Font', (new PdfDictionary())->set($fontName, $fontRef));
                foreach ($pageExtGState[$pageIdx] ?? [] as $gsName => $gsRef) {
                    $gsDictEntry = $resDict->get('ExtGState');
                    if (!$gsDictEntry instanceof PdfDictionary) {
                        $gsDictEntry = new PdfDictionary();
                        $resDict->set('ExtGState', $gsDictEntry);
                    }
                    $gsDictEntry->set($gsName, $gsRef);
                }
                $pageDict->set('Resources', $resDict);
            }

            // Create a PdfObject wrapper for the modified page
            $pageObj = new class ($pageDict) extends PdfObject {
                public function __construct(private readonly PdfDictionary $dict) {}
                public function toPdf(): string { return $this->dict->toPdf(); }
            };
            $pageObj->objectNumber = $pageRefs[$pageIdx]->objectNumber;
            $pageObj->generationNumber = 0;
            $writer->addModifiedObject($pageObj);
        }

        return $writer->generate();
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

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
            $dims['width'], $dims['height'], $textWidth, $textHeight,
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
            $cos, $sin, -$sin, $cos,
            $cx - ($textWidth * $cos / 2), $cy - ($textWidth * $sin / 2),
        );
        $operators[] = sprintf('(%s) Tj', $escaped);
        $operators[] = 'ET';
        $operators[] = 'Q';

        return ['operators' => $operators, 'extGState' => $extGState];
    }

    private function escapeText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
