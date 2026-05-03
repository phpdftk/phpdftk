<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Internal\PageResolver;
use Phpdftk\Pdf\Toolkit\Redaction\RedactionArea;

/**
 * Redact text or areas from PDF pages by drawing filled rectangles.
 *
 * Note: This is visual redaction (Phase 1). The underlying text bytes
 * remain in the PDF. For full content removal, a future Phase 2 will
 * rewrite content streams to strip text operators.
 *
 * Usage:
 *   TextRedactor::open('contract.pdf')
 *       ->redactArea(1, 72, 700, 200, 20)
 *       ->apply()
 *       ->save('redacted.pdf');
 *
 * @api
 */
final class TextRedactor
{
    private string $originalBytes;

    /** @var list<string> */
    private array $lastVersionWarnings = [];

    private float $redactR = 0.0;
    private float $redactG = 0.0;
    private float $redactB = 0.0;
    private bool $applied = false;

    /** @var list<RedactionArea> */
    private array $areas = [];

    /** @var list<array{type: string, text: string, pages: ?PageSelector}> */
    private array $textSearches = [];

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
    // Marking
    // -----------------------------------------------------------------------

    /**
     * Mark a text string for redaction across pages.
     *
     * Text positions are approximated — redaction rectangles cover the
     * approximate line area where the text was found.
     */
    public function redactText(string $text, ?PageSelector $pages = null): self
    {
        $this->textSearches[] = ['type' => 'literal', 'text' => $text, 'pages' => $pages];
        return $this;
    }

    public function redactPattern(string $regex, ?PageSelector $pages = null): self
    {
        $this->textSearches[] = ['type' => 'regex', 'text' => $regex, 'pages' => $pages];
        return $this;
    }

    /**
     * Mark a specific area for redaction.
     *
     * @param int   $pageNumber 1-based page number
     * @param float $x          Left edge in points
     * @param float $y          Bottom edge in points
     * @param float $width      Width in points
     * @param float $height     Height in points
     */
    public function redactArea(int $pageNumber, float $x, float $y, float $width, float $height): self
    {
        $this->areas[] = new RedactionArea($pageNumber - 1, $x, $y, $width, $height);
        return $this;
    }

    public function setRedactionColor(float $r, float $g, float $b): self
    {
        $this->redactR = $r;
        $this->redactG = $g;
        $this->redactB = $b;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Apply
    // -----------------------------------------------------------------------

    /**
     * Apply all marked redactions. Must be called before save/toBytes.
     */
    public function apply(): self
    {
        // Resolve text searches into areas
        $totalPages = $this->reader->getPageCount();
        foreach ($this->textSearches as $search) {
            for ($i = 0; $i < $totalPages; $i++) {
                $pageNum = $i + 1;
                if ($search['pages'] !== null && !$search['pages']->matches($pageNum, $totalPages)) {
                    continue;
                }

                $pageText = $this->reader->extractText($i);
                $matches = [];

                if ($search['type'] === 'literal') {
                    $offset = 0;
                    while (($pos = strpos($pageText, $search['text'], $offset)) !== false) {
                        $matches[] = ['offset' => $pos, 'length' => strlen($search['text'])];
                        $offset = $pos + strlen($search['text']);
                    }
                } else {
                    if (preg_match_all($search['text'], $pageText, $m, PREG_OFFSET_CAPTURE) > 0) {
                        foreach ($m[0] as [$matchText, $matchOffset]) {
                            $matches[] = ['offset' => $matchOffset, 'length' => strlen($matchText)];
                        }
                    }
                }

                // Approximate text positions:
                // Without content stream position tracking, we use a rough heuristic:
                // estimate ~6 pts per character at ~12pt font, starting from top-left margin
                $pageDict = $this->reader->getPage($i);
                $dims = PageResolver::getPageDimensions($pageDict, $this->reader);
                $charsPerLine = (int) (($dims['width'] - 144) / 6); // 72pt margins, ~6pt/char
                if ($charsPerLine < 1) {
                    $charsPerLine = 80;
                }

                foreach ($matches as $match) {
                    $line = (int) ($match['offset'] / $charsPerLine);
                    $col = $match['offset'] % $charsPerLine;
                    $x = 72 + $col * 6;
                    $y = $dims['height'] - 72 - ($line + 1) * 14; // ~14pt line height
                    $w = $match['length'] * 6;
                    $h = 14;

                    $this->areas[] = new RedactionArea($i, $x, $y, $w, $h);
                }
            }
        }

        $this->applied = true;
        return $this;
    }

    public function getRedactionCount(): int
    {
        return count($this->areas);
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
        if (empty($this->areas)) {
            return $this->originalBytes;
        }

        if (!$this->applied) {
            throw new \RuntimeException('Call apply() before save/toBytes');
        }

        $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);
        $pageRefs = PageResolver::getPageReferences($this->reader);

        // Group areas by page
        /** @var array<int, list<RedactionArea>> $byPage */
        $byPage = [];
        foreach ($this->areas as $area) {
            $byPage[$area->pageIndex][] = $area;
        }

        foreach ($byPage as $pageIdx => $areas) {
            if (!isset($pageRefs[$pageIdx])) {
                continue;
            }

            // Build redaction operators
            $ops = ['q'];
            $ops[] = sprintf('%.3f %.3f %.3f rg', $this->redactR, $this->redactG, $this->redactB);
            foreach ($areas as $area) {
                $ops[] = sprintf('%.2f %.2f %.2f %.2f re f', $area->x, $area->y, $area->width, $area->height);
            }
            $ops[] = 'Q';

            $cs = new ContentStream();
            $cs->raw(implode("\n", $ops));
            $csRef = $writer->addNewObject($cs);

            // Add content stream to page
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

            $pageObj = new class ($pageDict) extends PdfObject {
                public function __construct(private readonly PdfDictionary $dict) {}
                public function toPdf(): string { return $this->dict->toPdf(); }
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
}
