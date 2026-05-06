<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Reader\PdfReader;

/**
 * Transform page geometry — rotate, scale, and set page boxes.
 *
 * Uses incremental updates so the original PDF content is preserved
 * intact and only modified page dictionaries are appended.
 *
 * Usage:
 *   PageTransformer::open('input.pdf')
 *       ->rotate(90)
 *       ->setCropBox(0, 0, 300, 400, PageSelector::pages(1))
 *       ->save('output.pdf');
 *
 * @api
 */
final class PageTransformer
{
    private string $originalBytes;

    /** @var list<array{op: string, args: array<string, mixed>, pages: ?PageSelector}> */
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
    // Transform operations (fluent)
    // -----------------------------------------------------------------------

    /**
     * Rotate pages by the given angle.
     *
     * @param int $degrees Must be 0, 90, 180, or 270
     */
    public function rotate(int $degrees, ?PageSelector $pages = null): self
    {
        if ($degrees % 90 !== 0) {
            throw new \InvalidArgumentException('Rotation must be a multiple of 90 degrees');
        }
        // Normalize to 0..359
        $degrees = (($degrees % 360) + 360) % 360;
        $this->operations[] = ['op' => 'rotate', 'args' => ['degrees' => $degrees], 'pages' => $pages];
        return $this;
    }

    /**
     * Scale pages by a uniform factor.
     *
     * Multiplies all page box dimensions (MediaBox, CropBox, etc.) by the factor.
     */
    public function scale(float $factor, ?PageSelector $pages = null): self
    {
        if ($factor <= 0) {
            throw new \InvalidArgumentException('Scale factor must be positive');
        }
        $this->operations[] = ['op' => 'scale', 'args' => ['factor' => $factor], 'pages' => $pages];
        return $this;
    }

    /**
     * Scale pages to fit the given dimensions.
     *
     * Computes the uniform scale factor from the MediaBox and applies it.
     */
    public function scaleTo(float $width, float $height, ?PageSelector $pages = null): self
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Target dimensions must be positive');
        }
        $this->operations[] = ['op' => 'scaleTo', 'args' => ['width' => $width, 'height' => $height], 'pages' => $pages];
        return $this;
    }

    /**
     * Set the CropBox on selected pages.
     */
    public function setCropBox(float $x, float $y, float $w, float $h, ?PageSelector $pages = null): self
    {
        $this->operations[] = ['op' => 'setBox', 'args' => ['box' => 'CropBox', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h], 'pages' => $pages];
        return $this;
    }

    /**
     * Set the MediaBox on selected pages.
     */
    public function setMediaBox(float $x, float $y, float $w, float $h, ?PageSelector $pages = null): self
    {
        $this->operations[] = ['op' => 'setBox', 'args' => ['box' => 'MediaBox', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h], 'pages' => $pages];
        return $this;
    }

    /**
     * Set the TrimBox on selected pages.
     */
    public function setTrimBox(float $x, float $y, float $w, float $h, ?PageSelector $pages = null): self
    {
        $this->operations[] = ['op' => 'setBox', 'args' => ['box' => 'TrimBox', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h], 'pages' => $pages];
        return $this;
    }

    /**
     * Set the BleedBox on selected pages.
     */
    public function setBleedBox(float $x, float $y, float $w, float $h, ?PageSelector $pages = null): self
    {
        $this->operations[] = ['op' => 'setBox', 'args' => ['box' => 'BleedBox', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h], 'pages' => $pages];
        return $this;
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

        // Resolve page tree to get (objectNumber, PdfDictionary) pairs
        $pageEntries = $this->resolvePageEntries();
        $totalPages = count($pageEntries);

        // Track which pages have been modified (by 0-based index)
        /** @var array<int, PdfDictionary> */
        $modifiedPages = [];

        foreach ($this->operations as $operation) {
            $selector = $operation['pages'] ?? PageSelector::all();
            $indices = $selector->resolve($totalPages);

            foreach ($indices as $pageIndex) {
                // Clone the dict on first modification
                if (!isset($modifiedPages[$pageIndex])) {
                    $modifiedPages[$pageIndex] = $this->cloneDict($pageEntries[$pageIndex]['dict']);
                }
                $dict = $modifiedPages[$pageIndex];

                match ($operation['op']) {
                    'rotate' => $this->applyRotate($dict, $operation['args']['degrees']),
                    'scale' => $this->applyScale($dict, $operation['args']['factor']),
                    'scaleTo' => $this->applyScaleTo($dict, $operation['args']['width'], $operation['args']['height']),
                    'setBox' => $this->applySetBox(
                        $dict,
                        $operation['args']['box'],
                        $operation['args']['x'],
                        $operation['args']['y'],
                        $operation['args']['w'],
                        $operation['args']['h'],
                    ),
                    default => throw new \LogicException("Unknown operation: {$operation['op']}"),
                };
            }
        }

        if (empty($modifiedPages)) {
            return $this->originalBytes;
        }

        // Create incremental writer and add modified page objects
        $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);

        foreach ($modifiedPages as $pageIndex => $dict) {
            $objNum = $pageEntries[$pageIndex]['objectNumber'];
            $obj = new class ($dict) extends PdfObject {
                public function __construct(private readonly PdfDictionary $dict) {}
                public function toPdf(): string
                {
                    return $this->dict->toPdf();
                }
            };
            $obj->objectNumber = $objNum;
            $obj->generationNumber = 0;
            $writer->addModifiedObject($obj);
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
    // Internal — page tree traversal
    // -----------------------------------------------------------------------

    /**
     * Traverse the page tree and return each leaf page with its object number.
     *
     * @return list<array{objectNumber: int, dict: PdfDictionary}>
     */
    private function resolvePageEntries(): array
    {
        $catalog = $this->reader->getCatalog();
        $pagesRef = $catalog->get('Pages');
        if (!$pagesRef instanceof PdfReference) {
            return [];
        }
        $pagesDict = $this->reader->resolveReference($pagesRef);
        if (!$pagesDict instanceof PdfDictionary) {
            return [];
        }

        $result = [];
        $this->collectPageEntries($pagesDict, $result);
        return $result;
    }

    /**
     * Recursively collect page entries from a Pages tree node.
     *
     * @param list<array{objectNumber: int, dict: PdfDictionary}> $result
     */
    private function collectPageEntries(PdfDictionary $node, array &$result): void
    {
        $kids = $node->get('Kids');
        if (!$kids instanceof PdfArray) {
            return;
        }
        foreach ($kids->items as $kidRef) {
            if (!$kidRef instanceof PdfReference) {
                continue;
            }
            $kid = $this->reader->resolveReference($kidRef);
            if (!$kid instanceof PdfDictionary) {
                continue;
            }
            $type = $kid->get('Type');
            if ($type instanceof PdfName && $type->value === 'Pages') {
                $this->collectPageEntries($kid, $result);
            } else {
                $result[] = [
                    'objectNumber' => $kidRef->objectNumber,
                    'dict' => $kid,
                ];
            }
        }
    }

    // -----------------------------------------------------------------------
    // Internal — operations
    // -----------------------------------------------------------------------

    private function applyRotate(PdfDictionary $dict, int $degrees): void
    {
        $existing = $dict->get('Rotate');
        $current = $existing instanceof PdfNumber ? ((int) $existing->toPdf()) : 0;
        $new = (($current + $degrees) % 360 + 360) % 360;
        $dict->set('Rotate', new PdfNumber($new));
    }

    private function applyScale(PdfDictionary $dict, float $factor): void
    {
        foreach (['MediaBox', 'CropBox', 'TrimBox', 'BleedBox', 'ArtBox'] as $boxName) {
            $box = $dict->get($boxName);
            if ($box instanceof PdfArray && count($box->items) === 4) {
                $dict->set($boxName, $this->scaleBox($box, $factor));
            }
        }
    }

    private function applyScaleTo(PdfDictionary $dict, float $targetWidth, float $targetHeight): void
    {
        $mediaBox = $dict->get('MediaBox');
        if (!$mediaBox instanceof PdfArray || count($mediaBox->items) !== 4) {
            return;
        }

        $currentWidth = $this->numVal($mediaBox->items[2]) - $this->numVal($mediaBox->items[0]);
        $currentHeight = $this->numVal($mediaBox->items[3]) - $this->numVal($mediaBox->items[1]);

        if ($currentWidth <= 0 || $currentHeight <= 0) {
            return;
        }

        $factor = min($targetWidth / $currentWidth, $targetHeight / $currentHeight);
        $this->applyScale($dict, $factor);
    }

    private function applySetBox(PdfDictionary $dict, string $boxName, float $x, float $y, float $w, float $h): void
    {
        $dict->set($boxName, new PdfArray([
            new PdfNumber($x),
            new PdfNumber($y),
            new PdfNumber($x + $w),
            new PdfNumber($y + $h),
        ]));
    }

    // -----------------------------------------------------------------------
    // Internal — helpers
    // -----------------------------------------------------------------------

    private function scaleBox(PdfArray $box, float $factor): PdfArray
    {
        return new PdfArray([
            new PdfNumber($this->numVal($box->items[0]) * $factor),
            new PdfNumber($this->numVal($box->items[1]) * $factor),
            new PdfNumber($this->numVal($box->items[2]) * $factor),
            new PdfNumber($this->numVal($box->items[3]) * $factor),
        ]);
    }

    private function numVal(mixed $item): float
    {
        if ($item instanceof PdfNumber) {
            return (float) $item->value;
        }
        return (float) (string) $item;
    }

    private function cloneDict(PdfDictionary $dict): PdfDictionary
    {
        $clone = new PdfDictionary();
        foreach ($dict->entries as $key => $value) {
            $clone->set($key, $value);
        }
        return $clone;
    }
}
