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
     * The subsetter renumbers kept glyphs into a compact 0..N-1 range, so
     * the returned `unicodeToGidSubset` map points at the *post-subset*
     * GIDs that callers should emit in content streams. The /W widths and
     * /ToUnicode CMap are likewise built against post-subset GIDs so the
     * three views agree.
     *
     * @param TrueTypeData $data           Parsed font data
     * @param int[]        $usedCodepoints Unicode codepoints used in the document
     * @param bool         $vertical       Use Identity-V encoding for vertical writing mode
     * @return array{0: Type0Font, 1: list<PdfObject>, 2: PdfStream, 3: FontDescriptor, 4: CIDFontType2Font, 5: PdfStream, 6: array<int, int>, 7: array<int, int>}
     */
    public static function fromTrueTypeData(TrueTypeData $data, array $usedCodepoints, bool $vertical = false): array
    {
        $additionalObjects = [];

        // Resolve every requested codepoint to its pre-subset GID. We need
        // the pre-subset GIDs to drive the subsetter; the post-subset
        // numbering comes back via $subsetter->getGidMap().
        $oldGidByCodepoint = [];
        $usedOldGids = [];
        foreach ($usedCodepoints as $cp) {
            if (isset($data->fullUnicodeToGid[$cp])) {
                $oldGid = $data->fullUnicodeToGid[$cp];
                $oldGidByCodepoint[$cp] = $oldGid;
                $usedOldGids[] = $oldGid;
            }
        }
        $usedOldGids = array_values(array_unique($usedOldGids));
        sort($usedOldGids);

        // Subset the font.
        $subsetter = new TrueTypeSubsetter();
        $subsetBytes = $subsetter->subset($data->fontBytes, $usedOldGids, $data->fullUnicodeToGid);
        $gidMap = $subsetter->getGidMap();

        // Translate everything from pre-subset to post-subset GIDs so the
        // /W array, /ToUnicode CMap, and the caller-facing unicodeToGid all
        // index into the renumbered glyph table.
        $cidToUnicodeSubset = [];   // new GID => unicode
        $unicodeToGidSubset = [];   // unicode => new GID
        foreach ($oldGidByCodepoint as $cp => $oldGid) {
            $newGid = $gidMap[$oldGid] ?? null;
            if ($newGid === null) {
                continue;
            }
            $unicodeToGidSubset[$cp] = $newGid;
            $cidToUnicodeSubset[$newGid] = $cp;
        }

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

        // Build /W array (compact format) — indexed by post-subset GID so
        // the widths line up with what the content stream actually emits.
        $wArray = self::buildWidthsArray($usedOldGids, $gidMap, $data);
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

        // 4. ToUnicode CMap — keyed by post-subset GID so text extraction
        // sees the same identifiers the viewer renders against.
        $toUnicodeCmap = self::buildToUnicodeCMap($cidToUnicodeSubset);
        $toUnicodeStream = new PdfStream(new PdfDictionary(), $toUnicodeCmap);
        $additionalObjects[] = $toUnicodeStream;

        // 5. Type0Font (top-level) - descendantFonts will be wired after registration
        $type0Font = new Type0Font(
            $data->postScriptName,
            new PdfArray([]), // placeholder, will be set after CIDFont is registered
            $vertical ? 'Identity-V' : 'Identity-H',
        );

        return [$type0Font, $additionalObjects, $fontStream, $descriptor, $cidFont, $toUnicodeStream, $unicodeToGidSubset, $gidMap];
    }

    /**
     * Build the /W widths array in compact format, indexed by post-subset
     * CID (which equals the new GID under /CIDToGIDMap /Identity).
     *
     * Format: [cid [w1 w2 ...] cid [w1 w2 ...] ...]
     * Groups consecutive CIDs together.
     *
     * @param int[]            $oldGids Pre-subset GIDs that survived subsetting.
     * @param array<int, int>  $gidMap  Old GID → new GID, from the subsetter.
     * @return list<PdfNumber|PdfArray>
     */
    private static function buildWidthsArray(array $oldGids, array $gidMap, TrueTypeData $data): array
    {
        if ($oldGids === []) {
            return [];
        }

        $scale = fn(int $v): int => (int) round($v * 1000 / $data->unitsPerEm);

        // Build new-GID → scaled width using the original glyphWidths,
        // which are keyed by pre-subset GID.
        $widths = [];
        foreach ($oldGids as $oldGid) {
            $newGid = $gidMap[$oldGid] ?? null;
            if ($newGid === null) {
                continue;
            }
            $widths[$newGid] = $scale($data->glyphWidths[$oldGid] ?? 0);
        }

        $newGids = array_keys($widths);
        sort($newGids);
        $result = [];
        $i = 0;
        $count = count($newGids);

        while ($i < $count) {
            $startGid = $newGids[$i];
            $group = [new PdfNumber($widths[$startGid])];

            while ($i + 1 < $count && $newGids[$i + 1] === $newGids[$i] + 1) {
                $i++;
                $group[] = new PdfNumber($widths[$newGids[$i]]);
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
