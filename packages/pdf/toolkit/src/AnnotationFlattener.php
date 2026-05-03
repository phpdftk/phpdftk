<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\File\IncrementalWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Reader\PdfReader;
use Phpdftk\Pdf\Toolkit\Internal\PageResolver;

/**
 * Flatten annotations into page content, making them non-interactive.
 *
 * Usage:
 *   AnnotationFlattener::open('form.pdf')
 *       ->flattenAll()
 *       ->save('flat.pdf');
 *
 * @api
 */
final class AnnotationFlattener
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
    // Operations
    // -----------------------------------------------------------------------

    public function flattenAll(?PageSelector $pages = null): self
    {
        $this->operations[] = ['type' => 'all', 'args' => ['pages' => $pages]];
        return $this;
    }

    public function flattenType(string ...$subtypes): self
    {
        $this->operations[] = ['type' => 'subtypes', 'args' => ['subtypes' => $subtypes, 'pages' => null]];
        return $this;
    }

    public function flattenForms(?PageSelector $pages = null): self
    {
        $this->operations[] = ['type' => 'subtypes', 'args' => ['subtypes' => ['Widget'], 'pages' => $pages]];
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

        $writer = IncrementalWriter::fromReader($this->reader, $this->originalBytes);
        $pageRefs = PageResolver::getPageReferences($this->reader);
        $totalPages = count($pageRefs);

        for ($i = 0; $i < $totalPages; $i++) {
            $pageNum = $i + 1;

            // Check if any operation targets this page
            $shouldFlatten = false;
            $allowedSubtypes = null; // null = all

            foreach ($this->operations as $op) {
                $selector = $op['args']['pages'] ?? null;
                if ($selector !== null && !$selector->matches($pageNum, $totalPages)) {
                    continue;
                }
                $shouldFlatten = true;
                if ($op['type'] === 'subtypes' && $allowedSubtypes !== null) {
                    $allowedSubtypes = array_merge($allowedSubtypes, $op['args']['subtypes']);
                } elseif ($op['type'] === 'all') {
                    $allowedSubtypes = null; // flatten everything
                } elseif ($op['type'] === 'subtypes' && $allowedSubtypes === null) {
                    // First subtype filter
                    $allowedSubtypes = $op['args']['subtypes'];
                }
            }

            if (!$shouldFlatten) {
                continue;
            }

            $pageDict = $this->reader->getPage($i);
            $annots = $pageDict->get('Annots');
            if (!$annots instanceof PdfArray || empty($annots->items)) {
                continue;
            }

            $flattenedOps = [];
            $remainingAnnots = [];
            $xObjectResources = [];
            $xoCounter = 0;

            foreach ($annots->items as $annotRef) {
                if (!$annotRef instanceof PdfReference) {
                    $remainingAnnots[] = $annotRef;
                    continue;
                }

                $annotDict = $this->reader->resolveReference($annotRef);
                if (!$annotDict instanceof PdfDictionary) {
                    $remainingAnnots[] = $annotRef;
                    continue;
                }

                // Check subtype filter
                $subtype = $annotDict->get('Subtype');
                $subtypeStr = $subtype instanceof PdfName ? $subtype->value : '';

                if ($allowedSubtypes !== null && !in_array($subtypeStr, $allowedSubtypes, true)) {
                    $remainingAnnots[] = $annotRef;
                    continue;
                }

                // Get the annotation's normal appearance stream
                $ap = $annotDict->get('AP');
                if (!$ap instanceof PdfDictionary) {
                    // No appearance — keep as-is
                    $remainingAnnots[] = $annotRef;
                    continue;
                }

                $normalAp = $ap->get('N');

                // For checkboxes/radios, /N might be a dict of states; use /AS to pick
                if ($normalAp instanceof PdfDictionary && !$normalAp->has('Type')) {
                    $as = $annotDict->get('AS');
                    if ($as instanceof PdfName && $normalAp->has($as->value)) {
                        $normalAp = $normalAp->get($as->value);
                    } else {
                        // No matching state
                        $remainingAnnots[] = $annotRef;
                        continue;
                    }
                }

                if (!$normalAp instanceof PdfReference) {
                    $remainingAnnots[] = $annotRef;
                    continue;
                }

                // Get the rect for positioning
                $rect = $annotDict->get('Rect');
                if (!$rect instanceof PdfArray || count($rect->items) < 4) {
                    $remainingAnnots[] = $annotRef;
                    continue;
                }

                $x1 = $this->toFloat($rect->items[0]);
                $y1 = $this->toFloat($rect->items[1]);
                $x2 = $this->toFloat($rect->items[2]);
                $y2 = $this->toFloat($rect->items[3]);
                $w = abs($x2 - $x1);
                $h = abs($y2 - $y1);
                $xMin = min($x1, $x2);
                $yMin = min($y1, $y2);

                // Register the appearance XObject and invoke it
                $xoName = 'FlatXO' . $xoCounter++;
                $xObjectResources[$xoName] = $normalAp;

                // Resolve the appearance's BBox to compute the transformation matrix
                $apStream = $this->reader->resolveReference($normalAp);
                $apBBox = null;
                if ($apStream instanceof PdfDictionary) {
                    $bBox = $apStream->get('BBox');
                    if ($bBox instanceof PdfArray && count($bBox->items) >= 4) {
                        $apBBox = [
                            $this->toFloat($bBox->items[0]),
                            $this->toFloat($bBox->items[1]),
                            $this->toFloat($bBox->items[2]),
                            $this->toFloat($bBox->items[3]),
                        ];
                    }
                } elseif ($apStream instanceof PdfStream) {
                    $bBox = $apStream->dictionary->get('BBox');
                    if ($bBox instanceof PdfArray && count($bBox->items) >= 4) {
                        $apBBox = [
                            $this->toFloat($bBox->items[0]),
                            $this->toFloat($bBox->items[1]),
                            $this->toFloat($bBox->items[2]),
                            $this->toFloat($bBox->items[3]),
                        ];
                    }
                }

                // Build transformation matrix to map BBox to Rect
                if ($apBBox !== null) {
                    $bw = abs($apBBox[2] - $apBBox[0]);
                    $bh = abs($apBBox[3] - $apBBox[1]);
                    $sx = $bw > 0 ? $w / $bw : 1;
                    $sy = $bh > 0 ? $h / $bh : 1;
                    $tx = $xMin - $apBBox[0] * $sx;
                    $ty = $yMin - $apBBox[1] * $sy;
                    $flattenedOps[] = sprintf(
                        "q %.4f 0 0 %.4f %.4f %.4f cm /%s Do Q",
                        $sx, $sy, $tx, $ty, $xoName,
                    );
                } else {
                    $flattenedOps[] = sprintf(
                        "q 1 0 0 1 %.4f %.4f cm /%s Do Q",
                        $xMin, $yMin, $xoName,
                    );
                }
            }

            if (empty($flattenedOps)) {
                continue;
            }

            // Create new content stream with the flattened appearances
            $cs = new ContentStream();
            $cs->raw(implode("\n", $flattenedOps));
            $csRef = $writer->addNewObject($cs);

            // Add content stream to page
            $existingContents = $pageDict->get('Contents');
            $contentsArray = [];
            if ($existingContents instanceof PdfReference) {
                $contentsArray[] = $existingContents;
            } elseif ($existingContents instanceof PdfArray) {
                $contentsArray = $existingContents->items;
            }
            $contentsArray[] = $csRef;
            $pageDict->set('Contents', new PdfArray($contentsArray));

            // Update annotations
            if (empty($remainingAnnots)) {
                $pageDict->entries = array_filter(
                    $pageDict->entries,
                    fn($k) => $k !== 'Annots',
                    ARRAY_FILTER_USE_KEY,
                );
            } else {
                $pageDict->set('Annots', new PdfArray($remainingAnnots));
            }

            // Add XObject resources to page
            $existingRes = $pageDict->get('Resources');
            if ($existingRes instanceof PdfDictionary) {
                $xoDict = $existingRes->get('XObject');
                if (!$xoDict instanceof PdfDictionary) {
                    $xoDict = new PdfDictionary();
                    $existingRes->set('XObject', $xoDict);
                }
                foreach ($xObjectResources as $name => $ref) {
                    $xoDict->set($name, $ref);
                }
            }

            // Create modified page object
            $pageObj = new class ($pageDict) extends PdfObject {
                public function __construct(private readonly PdfDictionary $dict) {}
                public function toPdf(): string { return $this->dict->toPdf(); }
            };
            $pageObj->objectNumber = $pageRefs[$i]->objectNumber;
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

    private function toFloat(mixed $val): float
    {
        if ($val instanceof PdfNumber) {
            return (float) $val->toPdf();
        }
        return (float) $val;
    }
}
