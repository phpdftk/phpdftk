<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Internal;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Reader\PdfReader;

/**
 * Resolves page references (object numbers) from a PdfReader.
 *
 * @internal
 */
final class PageResolver
{
    /**
     * Get page object references by traversing the page tree.
     *
     * @return list<PdfReference> 0-indexed page references
     */
    public static function getPageReferences(PdfReader $reader): array
    {
        $catalog = $reader->getCatalog();
        $pagesRef = $catalog->get('Pages');
        if (!$pagesRef instanceof PdfReference) {
            return [];
        }
        $pagesDict = $reader->resolveReference($pagesRef);
        if (!$pagesDict instanceof PdfDictionary) {
            return [];
        }

        $result = [];
        self::collectPageRefs($reader, $pagesDict, $result);
        return $result;
    }

    /**
     * Get the MediaBox dimensions for a page dictionary.
     *
     * @return array{width: float, height: float}
     */
    public static function getPageDimensions(PdfDictionary $page, PdfReader $reader): array
    {
        $mediaBox = $page->get('MediaBox');
        if (!$mediaBox instanceof PdfArray) {
            // Try to inherit from parent
            $parent = $page->get('Parent');
            if ($parent instanceof PdfReference) {
                $parentDict = $reader->resolveReference($parent);
                if ($parentDict instanceof PdfDictionary) {
                    $mediaBox = $parentDict->get('MediaBox');
                }
            }
        }

        if ($mediaBox instanceof PdfArray && count($mediaBox->items) >= 4) {
            $x1 = self::toFloat($mediaBox->items[0]);
            $y1 = self::toFloat($mediaBox->items[1]);
            $x2 = self::toFloat($mediaBox->items[2]);
            $y2 = self::toFloat($mediaBox->items[3]);
            return ['width' => abs($x2 - $x1), 'height' => abs($y2 - $y1)];
        }

        // Default letter size
        return ['width' => 612.0, 'height' => 792.0];
    }

    private static function collectPageRefs(PdfReader $reader, PdfDictionary $node, array &$result): void
    {
        $kids = $node->get('Kids');
        if (!$kids instanceof PdfArray) {
            return;
        }
        foreach ($kids->items as $kidRef) {
            if (!$kidRef instanceof PdfReference) {
                continue;
            }
            $kid = $reader->resolveReference($kidRef);
            if (!$kid instanceof PdfDictionary) {
                continue;
            }
            $type = $kid->get('Type');
            if ($type instanceof PdfName && $type->value === 'Pages') {
                self::collectPageRefs($reader, $kid, $result);
            } else {
                $result[] = $kidRef;
            }
        }
    }

    private static function toFloat(mixed $val): float
    {
        if ($val instanceof PdfNumber) {
            return (float) $val->toPdf();
        }
        return (float) $val;
    }
}
