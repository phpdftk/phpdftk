<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Font;

use Phpdftk\FontParser\TrueTypeData;
use Phpdftk\FontParser\TrueTypeSubsetter;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfStream;

/**
 * Factory that builds a complete Type 0 composite font stack from TrueType font data.
 *
 * Produces the object graph:
 *   Type0Font (top-level)
 *     /Encoding /Identity-H
 *     /ToUnicode -> CMap stream (GID -> Unicode)
 *     /DescendantFonts [CIDFontType2]
 *       /CIDSystemInfo (Adobe-Identity-0)
 *       /FontDescriptor -> FontDescriptor
 *         /FontFile2 -> embedded font stream
 *       /DW (default width)
 *       /W (per-CID widths array)
 *       /CIDToGIDMap /Identity
 */
class Type0FontFactory
{
    /**
     * Build the complete Type 0 font stack from parsed TrueType data.
     *
     * @param TrueTypeData $data           Parsed font data
     * @param int[]        $usedCodepoints Unicode codepoints used in the document
     * @param bool         $vertical       Use Identity-V encoding for vertical writing mode
     * @return array{0: Type0Font, 1: list<PdfObject>, 2: PdfStream, 3: FontDescriptor, 4: CIDFontType2Font, 5: PdfStream}
     */
    public static function fromTrueTypeData(TrueTypeData $data, array $usedCodepoints, bool $vertical = false): array
    {
        $additionalObjects = [];

        // Determine which GIDs we need
        $usedGids = [];
        $cidToUnicode = []; // GID => Unicode codepoint
        foreach ($usedCodepoints as $cp) {
            if (isset($data->fullUnicodeToGid[$cp])) {
                $gid = $data->fullUnicodeToGid[$cp];
                $usedGids[] = $gid;
                $cidToUnicode[$gid] = $cp;
            }
        }
        $usedGids = array_unique($usedGids);
        sort($usedGids);

        // Subset the font
        $subsetter = new TrueTypeSubsetter();
        $subsetBytes = $subsetter->subset($data->fontBytes, $usedGids, $data->fullUnicodeToGid);

        // 1. Font program stream
        $streamDict = new PdfDictionary(['Length1' => new PdfNumber(strlen($subsetBytes))]);
        $fontStream = new PdfStream($streamDict, $subsetBytes);
        $additionalObjects[] = $fontStream;

        // 2. FontDescriptor
        $descriptor = new FontDescriptor(new PdfName($data->postScriptName));
        $descriptor->flags = $data->flags;
        $descriptor->fontBBox = new PdfArray([
            new PdfNumber($data->fontBBox[0]),
            new PdfNumber($data->fontBBox[1]),
            new PdfNumber($data->fontBBox[2]),
            new PdfNumber($data->fontBBox[3]),
        ]);
        $descriptor->italicAngle = $data->italicAngle;
        $descriptor->ascent = $data->ascent;
        $descriptor->descent = $data->descent;
        $descriptor->capHeight = $data->capHeight;
        $descriptor->xHeight = $data->xHeight;
        $descriptor->stemV = $data->stemV;
        $additionalObjects[] = $descriptor;

        // 3. CIDFontType2 (descendant)
        $cidSystemInfo = new CIDSystemInfo('Adobe', 'Identity', 0);
        $cidFont = new CIDFontType2Font($data->postScriptName, $cidSystemInfo);
        $cidFont->cidToGidMap = new PdfName('Identity');

        // Build /W array (compact format)
        $wArray = self::buildWidthsArray($usedGids, $data);
        if ($wArray !== []) {
            $cidFont->w = new PdfArray($wArray);
        }

        // Calculate default width (GID 0 width)
        $defaultWidth = 0;
        if (isset($data->glyphWidths[0])) {
            $defaultWidth = (int) round($data->glyphWidths[0] * 1000 / $data->unitsPerEm);
        }
        $cidFont->dw = $defaultWidth;
        $additionalObjects[] = $cidFont;

        // 4. ToUnicode CMap
        $toUnicodeCmap = self::buildToUnicodeCMap($cidToUnicode);
        $toUnicodeStream = new PdfStream(new PdfDictionary(), $toUnicodeCmap);
        $additionalObjects[] = $toUnicodeStream;

        // 5. Type0Font (top-level) - descendantFonts will be wired after registration
        $type0Font = new Type0Font(
            $data->postScriptName,
            new PdfArray([]), // placeholder, will be set after CIDFont is registered
            $vertical ? 'Identity-V' : 'Identity-H',
        );

        return [$type0Font, $additionalObjects, $fontStream, $descriptor, $cidFont, $toUnicodeStream];
    }

    /**
     * Build the /W widths array in compact format.
     *
     * Format: [cid [w1 w2 ...] cid [w1 w2 ...] ...]
     * Groups consecutive CIDs together.
     *
     * @param int[] $gids
     * @return list<PdfNumber|PdfArray>
     */
    private static function buildWidthsArray(array $gids, TrueTypeData $data): array
    {
        if ($gids === []) {
            return [];
        }

        $scale = fn(int $v): int => (int) round($v * 1000 / $data->unitsPerEm);

        // Build GID => scaled width
        $widths = [];
        foreach ($gids as $gid) {
            $rawWidth = $data->glyphWidths[$gid] ?? 0;
            $widths[$gid] = $scale($rawWidth);
        }

        sort($gids);
        $result = [];
        $i = 0;

        while ($i < count($gids)) {
            $startGid = $gids[$i];
            $group = [new PdfNumber($widths[$startGid])];

            while ($i + 1 < count($gids) && $gids[$i + 1] === $gids[$i] + 1) {
                $i++;
                $group[] = new PdfNumber($widths[$gids[$i]]);
            }

            $result[] = new PdfNumber($startGid);
            $result[] = new PdfArray($group);
            $i++;
        }

        return $result;
    }

    /**
     * Build a ToUnicode CMap for the GID->Unicode mapping.
     *
     * @param array<int, int> $cidToUnicode GID => Unicode codepoint
     */
    private static function buildToUnicodeCMap(array $cidToUnicode): string
    {
        ksort($cidToUnicode);
        $entries = [];
        foreach ($cidToUnicode as $gid => $unicode) {
            $entries[] = sprintf('<%04X> <%04X>', $gid, $unicode);
        }

        $chunks = array_chunk($entries, 100);
        $blocks = '';
        foreach ($chunks as $chunk) {
            $blocks .= count($chunk) . " beginbfchar\n"
                     . implode("\n", $chunk) . "\n"
                     . "endbfchar\n";
        }

        return "/CIDInit /ProcSet findresource begin\n"
             . "12 dict begin\n"
             . "begincmap\n"
             . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
             . "/CMapName /Adobe-Identity-UCS def\n"
             . "/CMapType 2 def\n"
             . "1 begincodespacerange\n"
             . "<0000> <FFFF>\n"
             . "endcodespacerange\n"
             . $blocks
             . "endcmap\n"
             . "CMap end\n"
             . "end";
    }
}
