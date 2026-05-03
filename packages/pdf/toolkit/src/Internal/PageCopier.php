<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Toolkit\Internal;

use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Document\Page;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Reader\PdfReader;

/**
 * Deep-copies pages from a PdfReader source into a PdfFileWriter target.
 *
 * Handles resolving indirect references, re-registering objects,
 * and maintaining reference integrity.
 *
 * @internal
 */
final class PageCopier
{
    /** @var array<int, int> Maps source object numbers to target object numbers */
    private array $objectMap = [];

    public function __construct(
        private readonly PdfReader $reader,
        private readonly PdfFileWriter $writer,
    ) {}

    /**
     * Copy specified pages from source to target.
     *
     * @param list<int> $pageIndices 0-based page indices to copy
     * @param PdfReference $pageTreeRef Reference to the target PageTree
     * @return list<PdfReference> References to the newly registered Page objects
     */
    public function copyPages(array $pageIndices, PdfReference $pageTreeRef): array
    {
        $sourcePages = $this->reader->getPages();
        $pageRefs = [];

        foreach ($pageIndices as $idx) {
            if (!isset($sourcePages[$idx])) {
                throw new \OutOfRangeException("Page index $idx out of range");
            }
            $pageDict = $sourcePages[$idx];
            $page = $this->copyPage($pageDict, $pageTreeRef);
            $this->writer->register($page);
            $pageRefs[] = new PdfReference($page->objectNumber);
        }

        return $pageRefs;
    }

    private function copyPage(PdfDictionary $sourceDict, PdfReference $pageTreeRef): Page
    {
        $page = new Page();
        $page->parent = $pageTreeRef;

        // Copy MediaBox
        $mediaBox = $sourceDict->get('MediaBox');
        if ($mediaBox instanceof PdfArray) {
            $page->mediaBox = $mediaBox;
        }

        // Copy CropBox
        $cropBox = $sourceDict->get('CropBox');
        if ($cropBox instanceof PdfArray) {
            $page->cropBox = $cropBox;
        }

        // Copy Rotate
        $rotate = $sourceDict->get('Rotate');
        if ($rotate instanceof PdfNumber) {
            $page->rotate = (int) $rotate->toPdf();
        }

        // Copy content streams
        $contents = $sourceDict->get('Contents');
        if ($contents instanceof PdfReference) {
            $ref = $this->copyIndirectObject($contents);
            if ($ref !== null) {
                $page->contents = [$ref];
            }
        } elseif ($contents instanceof PdfArray) {
            $contentRefs = [];
            foreach ($contents->items as $ref) {
                if ($ref instanceof PdfReference) {
                    $newRef = $this->copyIndirectObject($ref);
                    if ($newRef !== null) {
                        $contentRefs[] = $newRef;
                    }
                }
            }
            $page->contents = $contentRefs;
        }

        // Copy resources
        $resources = $sourceDict->get('Resources');
        if ($resources instanceof PdfDictionary) {
            $page->resources = $this->buildResources($resources);
        } elseif ($resources instanceof PdfReference) {
            $resolved = $this->reader->resolveReference($resources);
            if ($resolved instanceof PdfDictionary) {
                $page->resources = $this->buildResources($resolved);
            }
        }

        return $page;
    }

    private function copyIndirectObject(PdfReference $ref): ?PdfReference
    {
        // Check if already copied
        if (isset($this->objectMap[$ref->objectNumber])) {
            return new PdfReference($this->objectMap[$ref->objectNumber]);
        }

        $resolved = $this->reader->resolveReference($ref);

        if ($resolved instanceof PdfStream) {
            $newStream = new class ($resolved) extends PdfStream {
                public function __construct(PdfStream $source)
                {
                    parent::__construct(clone $source->dictionary, $source->data);
                }
            };
            $this->writer->register($newStream);
            $this->objectMap[$ref->objectNumber] = $newStream->objectNumber;
            return new PdfReference($newStream->objectNumber);
        }

        if ($resolved instanceof PdfObject) {
            $clone = clone $resolved;
            $clone->objectNumber = 0;
            $this->writer->register($clone);
            $this->objectMap[$ref->objectNumber] = $clone->objectNumber;
            return new PdfReference($clone->objectNumber);
        }

        if ($resolved instanceof PdfDictionary) {
            $wrapper = new class ($resolved) extends PdfObject {
                public function __construct(private readonly PdfDictionary $d) {}
                public function toPdf(): string { return $this->d->toPdf(); }
            };
            $this->writer->register($wrapper);
            $this->objectMap[$ref->objectNumber] = $wrapper->objectNumber;
            return new PdfReference($wrapper->objectNumber);
        }

        return null;
    }

    private function buildResources(PdfDictionary $res): Resources
    {
        $resources = new Resources();

        // Copy Font references
        $fontDict = $res->get('Font');
        if ($fontDict instanceof PdfDictionary) {
            foreach (array_keys($fontDict->entries) as $name) {
                $ref = $fontDict->entries[$name];
                if ($ref instanceof PdfReference) {
                    $newRef = $this->copyIndirectObject($ref);
                    if ($newRef !== null) {
                        $resources->font[$name] = $newRef;
                    }
                }
            }
        }

        // Copy XObject references
        $xoDict = $res->get('XObject');
        if ($xoDict instanceof PdfDictionary) {
            foreach (array_keys($xoDict->entries) as $name) {
                $ref = $xoDict->entries[$name];
                if ($ref instanceof PdfReference) {
                    $newRef = $this->copyIndirectObject($ref);
                    if ($newRef !== null) {
                        $resources->xObject[$name] = $newRef;
                    }
                }
            }
        }

        // Copy ExtGState references
        $gsDict = $res->get('ExtGState');
        if ($gsDict instanceof PdfDictionary) {
            foreach (array_keys($gsDict->entries) as $name) {
                $ref = $gsDict->entries[$name];
                if ($ref instanceof PdfReference) {
                    $newRef = $this->copyIndirectObject($ref);
                    if ($newRef !== null) {
                        $resources->extGState[$name] = $newRef;
                    }
                }
            }
        }

        return $resources;
    }

    private function copyResourceDict(PdfDictionary $res): PdfDictionary
    {
        $newRes = new PdfDictionary();

        foreach (array_keys($res->entries) as $key) {
            $val = $res->entries[$key];
            if ($val instanceof PdfDictionary) {
                $newSubDict = new PdfDictionary();
                foreach (array_keys($val->entries) as $subKey) {
                    $subVal = $val->entries[$subKey];
                    if ($subVal instanceof PdfReference) {
                        $newRef = $this->copyIndirectObject($subVal);
                        $newSubDict->set($subKey, $newRef ?? $subVal);
                    } else {
                        $newSubDict->set($subKey, $subVal);
                    }
                }
                $newRes->set($key, $newSubDict);
            } elseif ($val instanceof PdfReference) {
                $newRef = $this->copyIndirectObject($val);
                $newRes->set($key, $newRef ?? $val);
            } else {
                $newRes->set($key, $val);
            }
        }

        return $newRes;
    }
}
