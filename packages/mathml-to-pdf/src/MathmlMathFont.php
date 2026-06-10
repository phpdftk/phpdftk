<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\FontParser\MathGlyphInfo;
use Phpdftk\FontParser\MathVariants;
use Phpdftk\Pdf\Writer\Font;

/**
 * Bundles everything the painter needs to render a glyph via the
 * math font's Type-0 / CIDFontType0 stack:
 *
 *   - The PDF font handle to install on the content stream.
 *   - A Unicode → post-subset GID map so `showText`-style emit can
 *     translate UTF-8 to the hex GID strings the Type-0 font wants.
 *   - Per-GID design-unit widths so the painter can advance the
 *     cursor by the real glyph advance, not an AFM approximation.
 *   - `unitsPerEm` so callers can convert design units → points.
 *
 * Constructed once per renderer.draw() call when a math font is
 * loaded; null otherwise.
 *
 * Width lookup falls back to {@see DEFAULT_WIDTH} for any GID that
 * isn't in the widths map (a font's notdef is rare but possible).
 */
final readonly class MathmlMathFont
{
    public const int DEFAULT_WIDTH = 500;

    /**
     * @param Font $font PDF font handle returned by PdfWriter::addOpenTypeFont().
     * @param array<int, int> $unicodeToGid Unicode codepoint → post-subset GID.
     * @param array<int, int> $oldToNewGid Pre-subset → post-subset GID. Needed
     *        to translate MathGlyphInfo / MathVariants keys (which are
     *        pre-subset) into the GIDs the painter actually emits.
     * @param array<int, int> $glyphWidths post-subset GID → design-unit advance.
     * @param int $unitsPerEm Font's design units per em.
     * @param ?MathGlyphInfo $glyphInfo Parsed MathGlyphInfo sub-table. Keys
     *        are pre-subset GIDs.
     * @param ?MathVariants $variants Parsed MathVariants sub-table. Keys
     *        are pre-subset GIDs.
     */
    public function __construct(
        public Font $font,
        public array $unicodeToGid,
        public array $oldToNewGid,
        public array $glyphWidths,
        public int $unitsPerEm,
        public ?MathGlyphInfo $glyphInfo = null,
        public ?MathVariants $variants = null,
    ) {}

    /**
     * Italic correction (in FUnit) for the glyph identified by its
     * post-subset GID. Translates back to pre-subset via the
     * `oldToNewGid` map since MathGlyphInfo's keys are pre-subset.
     * Returns 0 when no italic correction is registered.
     */
    public function italicCorrectionFor(int $postSubsetGid): int
    {
        if ($this->glyphInfo === null) {
            return 0;
        }
        $oldGid = $this->postSubsetToPreSubset($postSubsetGid);
        if ($oldGid === null) {
            return 0;
        }
        return $this->glyphInfo->italicCorrections[$oldGid] ?? 0;
    }

    /**
     * Top accent attachment offset (FUnit) for the glyph. Returns
     * null when no attachment is registered so the caller can fall
     * back to half-advance (the OpenType spec default).
     */
    public function topAccentAttachmentFor(int $postSubsetGid): ?int
    {
        if ($this->glyphInfo === null) {
            return null;
        }
        $oldGid = $this->postSubsetToPreSubset($postSubsetGid);
        if ($oldGid === null) {
            return null;
        }
        return $this->glyphInfo->topAccentAttachments[$oldGid] ?? null;
    }

    /**
     * Pick the smallest vertical-stretch variant of the given glyph
     * whose advance (in FUnit) is >= `$requiredFunits`. Returns null
     * when no MathVariants entry exists or no variant is large enough.
     *
     * @return ?array{glyphId: int, advance: int}
     *         glyphId is the PRE-subset GID; caller must translate to
     *         post-subset before emitting hex.
     */
    public function verticalVariantFor(int $postSubsetGid, int $requiredFunits): ?array
    {
        if ($this->variants === null) {
            return null;
        }
        $oldGid = $this->postSubsetToPreSubset($postSubsetGid);
        if ($oldGid === null) {
            return null;
        }
        $construction = $this->variants->verticalConstructions[$oldGid] ?? null;
        if ($construction === null) {
            return null;
        }
        // Variants are sorted smallest first per spec.
        foreach ($construction->variants as $variant) {
            if ($variant['advance'] >= $requiredFunits) {
                return $variant;
            }
        }
        // No variant is large enough - return the largest as a
        // best-effort. (Real font assembly belongs in a follow-up.)
        // `end()` would mutate the array's internal pointer which
        // PHP rejects on a readonly property; index by count instead.
        $count = count($construction->variants);
        return $count === 0 ? null : $construction->variants[$count - 1];
    }

    /**
     * Translate a pre-subset GID to its post-subset GID using the
     * map the PdfWriter built when subsetting. Returns null when
     * the GID wasn't carried into the subset.
     */
    public function preSubsetToPostSubset(int $preSubsetGid): ?int
    {
        return $this->oldToNewGid[$preSubsetGid] ?? null;
    }

    /**
     * Vertical-stretch assembly recipe for the given glyph. Returns
     * null when:
     *   - no MathVariants table is loaded,
     *   - the glyph has no vertical construction,
     *   - the construction has no assembly (only pre-drawn variants).
     *
     * The painter consults this when no variant in
     * {@see verticalVariantFor()} is large enough for the required
     * height - it then stacks the assembly parts with overlap
     * sized by {@see MathVariants::$minConnectorOverlap}.
     */
    public function verticalAssemblyFor(int $postSubsetGid): ?\Phpdftk\FontParser\MathGlyphAssembly
    {
        if ($this->variants === null) {
            return null;
        }
        $oldGid = $this->postSubsetToPreSubset($postSubsetGid);
        if ($oldGid === null) {
            return null;
        }
        $construction = $this->variants->verticalConstructions[$oldGid] ?? null;
        return $construction?->assembly;
    }

    /**
     * Inverse of {@see preSubsetToPostSubset()}. Subset maps are
     * small (typically <100 entries) so array_flip per call is
     * acceptable. A profiling-driven cache can land later.
     */
    private function postSubsetToPreSubset(int $postSubsetGid): ?int
    {
        return array_flip($this->oldToNewGid)[$postSubsetGid] ?? null;
    }

    /**
     * Translate UTF-8 to hex-encoded post-subset GIDs the Type 0
     * font expects. Codepoints absent from the cmap are skipped -
     * the painter would render a tofu glyph anyway.
     */
    public function utf8ToHexGids(string $utf8): string
    {
        $hex = '';
        foreach (mb_str_split($utf8, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) {
                continue;
            }
            $gid = $this->unicodeToGid[$cp] ?? null;
            if ($gid === null) {
                continue;
            }
            $hex .= sprintf('%04X', $gid);
        }
        return $hex;
    }

    /**
     * Compute the rendered width of a UTF-8 string in PDF points at
     * the given font size. Uses real per-GID hmtx widths so the
     * cursor mechanics line up with the ink.
     */
    public function measure(string $utf8, float $fontSize): float
    {
        if ($utf8 === '' || $fontSize <= 0.0) {
            return 0.0;
        }
        $units = 0;
        foreach (mb_str_split($utf8, 1, 'UTF-8') as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp === false) {
                continue;
            }
            $gid = $this->unicodeToGid[$cp] ?? null;
            if ($gid === null) {
                $units += self::DEFAULT_WIDTH;
                continue;
            }
            $units += $this->glyphWidths[$gid] ?? self::DEFAULT_WIDTH;
        }
        return ($units / (float) $this->unitsPerEm) * $fontSize;
    }
}
