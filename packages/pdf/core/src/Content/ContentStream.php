<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Content;

use Phpdftk\Color\CmykColor;
use Phpdftk\Color\GrayColor;
use Phpdftk\Color\RgbColor;
use Phpdftk\Encoding\TextEncoder;
use Phpdftk\Pdf\Core\Font\RegisteredFont;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Geometry\Matrix;
use Phpdftk\Geometry\Rectangle;

/**
 * Content stream for page graphics and text.
 *
 * Provides a fluent API covering all PDF content stream operators
 * as defined in the PDF 1.7 specification (ISO 32000-1).
 */
class ContentStream extends PdfStream
{
    /** @var array<int, string> */
    private array $operators = [];

    /**
     * Encoder for the currently-active single-byte font. When set, showText
     * and showTextArray convert their UTF-8 input to the font's byte
     * encoding before emitting Tj/TJ. Null when no font has been set, or
     * when the active font is a composite/CID font (those use the
     * showTextHex / showUnicodeText path).
     */
    private ?TextEncoder $activeEncoder = null;

    public function __construct()
    {
        parent::__construct(new PdfDictionary(), '');
    }

    /** @return array<int, string> */
    public function getOperators(): array
    {
        return $this->operators;
    }

    /**
     * Clear the operators array. After this, toPdf() will use
     * $this->data directly instead of regenerating from operators.
     */
    public function clearOperators(): void
    {
        $this->operators = [];
    }

    // -----------------------------------------------------------------------
    // Text state operators
    // -----------------------------------------------------------------------

    /** BT - Begin text object */
    public function beginText(): self
    {
        $this->operators[] = 'BT';
        return $this;
    }

    /** ET - End text object */
    public function endText(): self
    {
        $this->operators[] = 'ET';
        return $this;
    }

    /**
     * Tf - Set font and size.
     *
     * Accepts either a resource name string (legacy / raw-bytes mode — the
     * caller is responsible for emitting bytes in the font's encoding) or
     * a `RegisteredFont` handle from `PdfWriter::addFont()`. When a handle
     * is passed, the stream remembers the font's text encoder so subsequent
     * showText/showTextArray calls accept UTF-8 directly.
     */
    public function setFont(RegisteredFont|string $name, float $size): self
    {
        if ($name instanceof RegisteredFont) {
            $this->activeEncoder = $name->getTextEncoder();
            $name = $name->getResourceName();
        } else {
            $this->activeEncoder = null;
        }
        $this->operators[] = sprintf('/%s %s Tf', $name, $this->num($size));
        return $this;
    }

    /** Td - Move text position */
    public function moveTextPosition(float $x, float $y): self
    {
        $this->operators[] = sprintf('%s %s Td', $this->num($x), $this->num($y));
        return $this;
    }

    /** TD - Move text position and set leading */
    public function moveTextPositionNewLine(float $tx, float $ty): self
    {
        $this->operators[] = sprintf('%s %s TD', $this->num($tx), $this->num($ty));
        return $this;
    }

    /** Tj - Show text */
    public function showText(string $text): self
    {
        $this->operators[] = $this->escapeString($this->encodeForActiveFont($text)) . ' Tj';
        return $this;
    }

    /** TJ - Show text with individual character positioning
     * @param array<int, string|int|float> $texts
     */
    public function showTextArray(array $texts): self
    {
        $parts = [];
        foreach ($texts as $item) {
            if (is_string($item)) {
                $parts[] = $this->escapeString($this->encodeForActiveFont($item));
            } elseif (is_int($item) || is_float($item)) {
                $parts[] = $this->num($item);
            }
        }
        $this->operators[] = '[ ' . implode(' ', $parts) . ' ] TJ';
        return $this;
    }

    /**
     * Run text through the active font's encoder if one was set via
     * setFont(RegisteredFont). Otherwise return it verbatim so callers
     * that pre-encoded their bytes continue to work.
     */
    private function encodeForActiveFont(string $text): string
    {
        return $this->activeEncoder?->encode($text) ?? $text;
    }

    /** Tj with hex-encoded string - Show text using hex-encoded glyph IDs (for CID fonts) */
    public function showTextHex(string $hexEncodedGids): self
    {
        $this->operators[] = '<' . $hexEncodedGids . '> Tj';
        return $this;
    }

    /**
     * Show Unicode text using a CID font's GID mapping.
     *
     * Converts UTF-8 text to hex-encoded 2-byte GID sequences and
     * emits a Tj operator. Requires a unicode-to-GID map (typically
     * from TrueTypeData::$fullUnicodeToGid or OpenTypeData::$fullUnicodeToGid).
     *
     * @param string $text UTF-8 text
     * @param array<int, int> $unicodeToGid Unicode codepoint → GID mapping
     */
    public function showUnicodeText(string $text, array $unicodeToGid): self
    {
        $hex = '';
        foreach (mb_str_split($text) as $char) {
            $cp = mb_ord($char);
            $gid = $unicodeToGid[$cp] ?? 0;
            $hex .= sprintf('%04X', $gid);
        }
        $this->operators[] = '<' . $hex . '> Tj';
        return $this;
    }

    /**
     * Emit a TJ array with kerning adjustments from GPOS data.
     *
     * Positive kern values in font units are negated per PDF convention
     * (negative TJ displacements move the cursor right). Falls back to
     * plain Tj when no kerning pairs apply.
     *
     * @param string $text UTF-8 text
     * @param array<int, int> $unicodeToGid Unicode codepoint => GID mapping
     * @param array<int, array<int, int>> $kernPairs leftGid => [rightGid => xAdvanceAdjust (design units)]
     * @param int $unitsPerEm Font design units per em
     */
    public function showUnicodeTextKerned(
        string $text,
        array $unicodeToGid,
        array $kernPairs,
        int $unitsPerEm,
    ): self {
        $chars = mb_str_split($text);
        if ($chars === []) {
            return $this;
        }

        // Build array of GIDs
        $gids = [];
        foreach ($chars as $char) {
            $cp = mb_ord($char);
            $gids[] = $unicodeToGid[$cp] ?? 0;
        }

        // Build TJ array: interleave hex strings with kern adjustments
        // TJ numeric values are in thousandths of a unit of text space.
        // Positive = move left (loosen), negative = move right (tighten).
        // Font kern values: negative = tighten. TJ wants the opposite sign
        // for tightening, BUT in PDF spec the TJ displacement subtracts the
        // value from the current point. So a positive TJ value moves LEFT
        // (tightens). Font kern is negative for tightening, so we negate.
        $tjParts = [];
        $currentHex = sprintf('%04X', $gids[0]);

        for ($i = 1, $count = count($gids); $i < $count; $i++) {
            $prevGid = $gids[$i - 1];
            $curGid = $gids[$i];
            $kern = $kernPairs[$prevGid][$curGid] ?? 0;

            if ($kern !== 0) {
                $tjParts[] = '<' . $currentHex . '>';
                // Scale from design units to 1/1000 em and negate
                // (font kern negative=tighten, TJ positive=tighten)
                $tjParts[] = (string) (int) round(-$kern * 1000 / $unitsPerEm);
                $currentHex = sprintf('%04X', $curGid);
            } else {
                $currentHex .= sprintf('%04X', $curGid);
            }
        }
        $tjParts[] = '<' . $currentHex . '>';

        if (count($tjParts) === 1) {
            $this->operators[] = $tjParts[0] . ' Tj';
        } else {
            $this->operators[] = '[ ' . implode(' ', $tjParts) . ' ] TJ';
        }

        return $this;
    }

    /**
     * Apply OpenType GSUB ligature substitutions before kerning.
     *
     * Ligatures reduce the glyph count (e.g., "fi" -> single glyph),
     * so the GID sequence may be shorter than the input. If kern pairs
     * are provided, kerning adjustments are applied between the shaped
     * glyphs using TJ; otherwise a plain Tj is emitted.
     *
     * @param string $text UTF-8 text to render
     * @param array<int, int> $unicodeToGid Unicode codepoint → GID map
     * @param array<int, list<array{components: int[], ligature: int}>> $ligatures GSUB ligature rules
     * @param array<int, array<int, int>> $kernPairs Kern pairs (optional)
     * @param int $unitsPerEm Font design units per em
     */
    public function showUnicodeTextShaped(
        string $text,
        array $unicodeToGid,
        array $ligatures,
        array $kernPairs = [],
        int $unitsPerEm = 1000,
    ): self {
        $chars = mb_str_split($text);
        if ($chars === []) {
            return $this;
        }

        // Convert Unicode to GIDs
        $gids = [];
        foreach ($chars as $char) {
            $cp = mb_ord($char);
            $gids[] = $unicodeToGid[$cp] ?? 0;
        }

        // Apply ligature substitutions
        $gids = \Phpdftk\FontParser\TextShaper::applyLigatures($gids, $ligatures);

        if ($gids === []) {
            return $this;
        }

        // If we have kern pairs, emit TJ with adjustments
        if ($kernPairs !== []) {
            $tjParts = [];
            $currentHex = sprintf('%04X', $gids[0]);

            for ($i = 1, $count = count($gids); $i < $count; $i++) {
                $prevGid = $gids[$i - 1];
                $curGid = $gids[$i];
                $kern = $kernPairs[$prevGid][$curGid] ?? 0;

                if ($kern !== 0) {
                    $tjParts[] = '<' . $currentHex . '>';
                    $tjParts[] = (string) (int) round(-$kern * 1000 / $unitsPerEm);
                    $currentHex = sprintf('%04X', $curGid);
                } else {
                    $currentHex .= sprintf('%04X', $curGid);
                }
            }
            $tjParts[] = '<' . $currentHex . '>';

            if (count($tjParts) === 1) {
                $this->operators[] = $tjParts[0] . ' Tj';
            } else {
                $this->operators[] = '[ ' . implode(' ', $tjParts) . ' ] TJ';
            }
        } else {
            // No kerning — simple hex string
            $hex = '';
            foreach ($gids as $gid) {
                $hex .= sprintf('%04X', $gid);
            }
            $this->operators[] = '<' . $hex . '> Tj';
        }

        return $this;
    }

    /** T* - Move to next line */
    public function nextLine(): self
    {
        $this->operators[] = 'T*';
        return $this;
    }

    /** Tm - Set text matrix and text line matrix */
    public function setTextMatrix(float $a, float $b, float $c, float $d, float $e, float $f): self
    {
        $this->operators[] = sprintf(
            '%s %s %s %s %s %s Tm',
            $this->num($a),
            $this->num($b),
            $this->num($c),
            $this->num($d),
            $this->num($e),
            $this->num($f),
        );
        return $this;
    }

    /** Tc - Set character spacing */
    public function setCharSpacing(float $cs): self
    {
        $this->operators[] = sprintf('%s Tc', $this->num($cs));
        return $this;
    }

    /** Tw - Set word spacing */
    public function setWordSpacing(float $ws): self
    {
        $this->operators[] = sprintf('%s Tw', $this->num($ws));
        return $this;
    }

    /** Tz - Set horizontal scaling */
    public function setHorizontalScaling(float $hs): self
    {
        $this->operators[] = sprintf('%s Tz', $this->num($hs));
        return $this;
    }

    /** TL - Set text leading */
    public function setTextLeading(float $tl): self
    {
        $this->operators[] = sprintf('%s TL', $this->num($tl));
        return $this;
    }

    /** Tr - Set text rendering mode */
    public function setTextRenderingMode(int $mode): self
    {
        $this->operators[] = sprintf('%d Tr', $mode);
        return $this;
    }

    /** Ts - Set text rise */
    public function setTextRise(float $rise): self
    {
        $this->operators[] = sprintf('%s Ts', $this->num($rise));
        return $this;
    }

    // -----------------------------------------------------------------------
    // Graphics state operators
    // -----------------------------------------------------------------------

    /** q - Save graphics state */
    public function saveGraphicsState(): self
    {
        $this->operators[] = 'q';
        return $this;
    }

    /** Q - Restore graphics state */
    public function restoreGraphicsState(): self
    {
        $this->operators[] = 'Q';
        return $this;
    }

    /** w - Set line width */
    public function setLineWidth(float $w): self
    {
        $this->operators[] = sprintf('%s w', $this->num($w));
        return $this;
    }

    /** J - Set line cap style */
    public function setLineCap(int $cap): self
    {
        $this->operators[] = sprintf('%d J', $cap);
        return $this;
    }

    /** j - Set line join style */
    public function setLineJoin(int $join): self
    {
        $this->operators[] = sprintf('%d j', $join);
        return $this;
    }

    /** M - Set miter limit */
    public function setMiterLimit(float $limit): self
    {
        $this->operators[] = sprintf('%s M', $this->num($limit));
        return $this;
    }

    /** d - Set line dash pattern
     * @param array<int, int|float> $dash
     */
    public function setDashPattern(array $dash, int $phase): self
    {
        $dashStr = '[ ' . implode(' ', array_map([$this, 'num'], $dash)) . ' ]';
        $this->operators[] = sprintf('%s %d d', $dashStr, $phase);
        return $this;
    }

    /** ri - Set rendering intent */
    public function setRenderingIntent(string $intent): self
    {
        $this->operators[] = sprintf('/%s ri', $intent);
        return $this;
    }

    /** i - Set flatness tolerance */
    public function setFlatness(float $flatness): self
    {
        $this->operators[] = sprintf('%s i', $this->num($flatness));
        return $this;
    }

    /** gs - Set graphics state from ExtGState resource */
    public function setGraphicsState(string $name): self
    {
        $this->operators[] = sprintf('/%s gs', $name);
        return $this;
    }

    /** cm - Concatenate matrix to current transformation matrix */
    public function concatMatrix(float $a, float $b, float $c, float $d, float $e, float $f): self
    {
        $this->operators[] = sprintf(
            '%s %s %s %s %s %s cm',
            $this->num($a),
            $this->num($b),
            $this->num($c),
            $this->num($d),
            $this->num($e),
            $this->num($f),
        );
        return $this;
    }

    // -----------------------------------------------------------------------
    // Path construction operators
    // -----------------------------------------------------------------------

    /** m - Move to */
    public function moveTo(float $x, float $y): self
    {
        $this->operators[] = sprintf('%s %s m', $this->num($x), $this->num($y));
        return $this;
    }

    /** l - Line to */
    public function lineTo(float $x, float $y): self
    {
        $this->operators[] = sprintf('%s %s l', $this->num($x), $this->num($y));
        return $this;
    }

    /** c - Curve to (full cubic Bezier) */
    public function curveTo(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): self
    {
        $this->operators[] = sprintf(
            '%s %s %s %s %s %s c',
            $this->num($x1),
            $this->num($y1),
            $this->num($x2),
            $this->num($y2),
            $this->num($x3),
            $this->num($y3),
        );
        return $this;
    }

    /** v - Curve to (current point replicated as first control point) */
    public function curveToV(float $x2, float $y2, float $x3, float $y3): self
    {
        $this->operators[] = sprintf(
            '%s %s %s %s v',
            $this->num($x2),
            $this->num($y2),
            $this->num($x3),
            $this->num($y3),
        );
        return $this;
    }

    /** y - Curve to (final point replicated as second control point) */
    public function curveToY(float $x1, float $y1, float $x3, float $y3): self
    {
        $this->operators[] = sprintf(
            '%s %s %s %s y',
            $this->num($x1),
            $this->num($y1),
            $this->num($x3),
            $this->num($y3),
        );
        return $this;
    }

    /** h - Close path */
    public function closePath(): self
    {
        $this->operators[] = 'h';
        return $this;
    }

    /** re - Rectangle */
    public function rectangle(float $x, float $y, float $w, float $h): self
    {
        $this->operators[] = sprintf(
            '%s %s %s %s re',
            $this->num($x),
            $this->num($y),
            $this->num($w),
            $this->num($h),
        );
        return $this;
    }

    // -----------------------------------------------------------------------
    // Path painting operators
    // -----------------------------------------------------------------------

    /** S - Stroke path */
    public function stroke(): self
    {
        $this->operators[] = 'S';
        return $this;
    }

    /** s - Close and stroke */
    public function closeAndStroke(): self
    {
        $this->operators[] = 's';
        return $this;
    }

    /** f - Fill path (non-zero winding) */
    public function fill(): self
    {
        $this->operators[] = 'f';
        return $this;
    }

    /** f* - Fill path (even-odd rule) */
    public function fillEvenOdd(): self
    {
        $this->operators[] = 'f*';
        return $this;
    }

    /** B - Fill and stroke (non-zero winding) */
    public function fillAndStroke(): self
    {
        $this->operators[] = 'B';
        return $this;
    }

    /** B* - Fill and stroke (even-odd rule) */
    public function fillAndStrokeEvenOdd(): self
    {
        $this->operators[] = 'B*';
        return $this;
    }

    /** b - Close, fill, and stroke (non-zero winding) */
    public function closeFillAndStroke(): self
    {
        $this->operators[] = 'b';
        return $this;
    }

    /** b* - Close, fill, and stroke (even-odd rule) */
    public function closeFillAndStrokeEvenOdd(): self
    {
        $this->operators[] = 'b*';
        return $this;
    }

    /** n - End path without painting */
    public function endPath(): self
    {
        $this->operators[] = 'n';
        return $this;
    }

    /** W - Modify clipping path (non-zero winding) */
    public function clip(): self
    {
        $this->operators[] = 'W';
        return $this;
    }

    /** W* - Modify clipping path (even-odd rule) */
    public function clipEvenOdd(): self
    {
        $this->operators[] = 'W*';
        return $this;
    }

    // -----------------------------------------------------------------------
    // Color operators
    // -----------------------------------------------------------------------

    /** RG - Set stroke color (DeviceRGB) */
    public function setStrokeColorRGB(float $r, float $g, float $b): self
    {
        $this->operators[] = sprintf('%s %s %s RG', $this->num($r), $this->num($g), $this->num($b));
        return $this;
    }

    /** rg - Set fill color (DeviceRGB) */
    public function setFillColorRGB(float $r, float $g, float $b): self
    {
        $this->operators[] = sprintf('%s %s %s rg', $this->num($r), $this->num($g), $this->num($b));
        return $this;
    }

    /** K - Set stroke color (DeviceCMYK) */
    public function setStrokeColorCMYK(float $c, float $m, float $y, float $k): self
    {
        $this->operators[] = sprintf(
            '%s %s %s %s K',
            $this->num($c),
            $this->num($m),
            $this->num($y),
            $this->num($k),
        );
        return $this;
    }

    /** k - Set fill color (DeviceCMYK) */
    public function setFillColorCMYK(float $c, float $m, float $y, float $k): self
    {
        $this->operators[] = sprintf(
            '%s %s %s %s k',
            $this->num($c),
            $this->num($m),
            $this->num($y),
            $this->num($k),
        );
        return $this;
    }

    /** G - Set stroke color (DeviceGray) */
    public function setStrokeColorGray(float $g): self
    {
        $this->operators[] = sprintf('%s G', $this->num($g));
        return $this;
    }

    /** g - Set fill color (DeviceGray) */
    public function setFillColorGray(float $g): self
    {
        $this->operators[] = sprintf('%s g', $this->num($g));
        return $this;
    }

    /** CS - Set stroke color space */
    public function setStrokeColorSpace(string $name): self
    {
        $this->operators[] = sprintf('/%s CS', $name);
        return $this;
    }

    /** cs - Set fill color space */
    public function setFillColorSpace(string $name): self
    {
        $this->operators[] = sprintf('/%s cs', $name);
        return $this;
    }

    /** SCN - Set stroke color (for special color spaces) */
    public function setStrokeColor(float|int|string ...$components): self
    {
        $parts = array_map(fn($c) => is_string($c) ? '/' . $c : $this->num($c), $components);
        $this->operators[] = implode(' ', $parts) . ' SCN';
        return $this;
    }

    /** scn - Set fill color (for special color spaces) */
    public function setFillColor(float|int|string ...$components): self
    {
        $parts = array_map(fn($c) => is_string($c) ? '/' . $c : $this->num($c), $components);
        $this->operators[] = implode(' ', $parts) . ' scn';
        return $this;
    }

    // -----------------------------------------------------------------------
    // XObject operator
    // -----------------------------------------------------------------------

    /** Do - Invoke named XObject */
    public function doXObject(string $name): self
    {
        $this->operators[] = sprintf('/%s Do', $name);
        return $this;
    }

    // -----------------------------------------------------------------------
    // Inline image
    // -----------------------------------------------------------------------

    /**
     * BI...ID...EI - Inline image.
     *
     * @param array<string, string> $params Associative array of image dictionary entries (abbreviated keys).
     * @param string $data   Raw image data.
     */
    public function inlineImage(array $params, string $data): self
    {
        $lines = ['BI'];
        foreach ($params as $key => $value) {
            $lines[] = '/' . $key . ' ' . $value;
        }
        $lines[] = 'ID';
        $lines[] = $data;
        $lines[] = 'EI';
        $this->operators[] = implode("\n", $lines);
        return $this;
    }

    // -----------------------------------------------------------------------
    // Typed color methods using phpdftk/color objects
    // -----------------------------------------------------------------------

    /** Set fill color using an RgbColor value object */
    public function setFillRgbColor(RgbColor $color): self
    {
        return $this->setFillColorRGB($color->r, $color->g, $color->b);
    }

    /** Set stroke color using an RgbColor value object */
    public function setStrokeRgbColor(RgbColor $color): self
    {
        return $this->setStrokeColorRGB($color->r, $color->g, $color->b);
    }

    /** Set fill color using a CmykColor value object */
    public function setFillCmykColor(CmykColor $color): self
    {
        return $this->setFillColorCMYK($color->c, $color->m, $color->y, $color->k);
    }

    /** Set stroke color using a CmykColor value object */
    public function setStrokeCmykColor(CmykColor $color): self
    {
        return $this->setStrokeColorCMYK($color->c, $color->m, $color->y, $color->k);
    }

    /** Set fill color using a GrayColor value object */
    public function setFillGrayColor(GrayColor $color): self
    {
        return $this->setFillColorGray($color->gray);
    }

    /** Set stroke color using a GrayColor value object */
    public function setStrokeGrayColor(GrayColor $color): self
    {
        return $this->setStrokeColorGray($color->gray);
    }

    // -----------------------------------------------------------------------
    // Geometry object helpers
    // -----------------------------------------------------------------------

    /** re - Rectangle using a Geometry Rectangle value object */
    public function rectangleObject(Rectangle $r): self
    {
        return $this->rectangle($r->x, $r->y, $r->width, $r->height);
    }

    /** cm - Concatenate matrix using a Geometry Matrix value object */
    public function concatMatrixObject(Matrix $m): self
    {
        return $this->concatMatrix($m->a, $m->b, $m->c, $m->d, $m->e, $m->f);
    }

    // -----------------------------------------------------------------------
    // Shorthand text operators
    // -----------------------------------------------------------------------

    /** ' - Move to next line and show text (equivalent to T* string Tj) */
    public function moveToNextLineAndShowText(string $text): self
    {
        $this->operators[] = $this->escapeString($text) . " '";
        return $this;
    }

    /**
     * " - Set word/char spacing, move to next line, and show text
     * (equivalent to aw Tw ac Tc T* string Tj)
     */
    public function setSpacingMoveAndShowText(float $aw, float $ac, string $text): self
    {
        $this->operators[] = sprintf(
            '%s %s %s "',
            $this->num($aw),
            $this->num($ac),
            $this->escapeString($text),
        );
        return $this;
    }

    // -----------------------------------------------------------------------
    // Shading operator
    // -----------------------------------------------------------------------

    /** sh - Paint the shape and colour defined by a shading pattern */
    public function paintShading(string $name): self
    {
        $this->operators[] = sprintf('/%s sh', $name);
        return $this;
    }

    // -----------------------------------------------------------------------
    // Type 3 font glyph operators
    // -----------------------------------------------------------------------

    /** d0 - Set glyph width in a Type 3 font (coloured glyph) */
    public function setGlyphWidth(float $wx, float $wy): self
    {
        $this->operators[] = sprintf('%s %s d0', $this->num($wx), $this->num($wy));
        return $this;
    }

    /** d1 - Set glyph width and bounding box in a Type 3 font (uncoloured glyph) */
    public function setGlyphWidthAndBoundingBox(
        float $wx,
        float $wy,
        float $llx,
        float $lly,
        float $urx,
        float $ury,
    ): self {
        $this->operators[] = sprintf(
            '%s %s %s %s %s %s d1',
            $this->num($wx),
            $this->num($wy),
            $this->num($llx),
            $this->num($lly),
            $this->num($urx),
            $this->num($ury),
        );
        return $this;
    }

    // -----------------------------------------------------------------------
    // Marked content operators
    // -----------------------------------------------------------------------

    /** MP - Define a marked-content point */
    public function markedContentPoint(string $tag): self
    {
        $this->operators[] = sprintf('/%s MP', $tag);
        return $this;
    }

    /**
     * DP - Define a marked-content point with property list.
     * $properties is either a resource name (string) or an inline dict string.
     */
    public function markedContentPointWithProperties(string $tag, string $properties): self
    {
        $this->operators[] = sprintf('/%s %s DP', $tag, $properties);
        return $this;
    }

    /** BMC - Begin a marked-content sequence */
    public function beginMarkedContent(string $tag): self
    {
        $this->operators[] = sprintf('/%s BMC', $tag);
        return $this;
    }

    /**
     * BDC - Begin a marked-content sequence with property list.
     * $properties is either a resource name (e.g. '/MC0') or an inline dict string.
     */
    public function beginMarkedContentWithProperties(string $tag, string $properties): self
    {
        $this->operators[] = sprintf('/%s %s BDC', $tag, $properties);
        return $this;
    }

    /** EMC - End a marked-content sequence */
    public function endMarkedContent(): self
    {
        $this->operators[] = 'EMC';
        return $this;
    }

    // -----------------------------------------------------------------------
    // Compatibility operators
    // -----------------------------------------------------------------------

    /** BX - Begin a compatibility section (unknown operators are ignored) */
    public function beginCompatibility(): self
    {
        $this->operators[] = 'BX';
        return $this;
    }

    /** EX - End a compatibility section */
    public function endCompatibility(): self
    {
        $this->operators[] = 'EX';
        return $this;
    }

    // -----------------------------------------------------------------------
    // Raw operator
    // -----------------------------------------------------------------------

    /** Emit a raw PDF operator string verbatim. */
    public function raw(string $operator): self
    {
        $this->operators[] = $operator;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Serialization
    // -----------------------------------------------------------------------

    public function toPdf(): string
    {
        $this->data = implode("\n", $this->operators);
        return parent::toPdf();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Format a number for PDF output (no trailing zeros for floats).
     */
    private function num(int|float $n): string
    {
        if (is_int($n)) {
            return (string) $n;
        }
        $s = rtrim(rtrim(sprintf('%.6f', $n), '0'), '.');
        return $s === '' || $s === '-0' ? '0' : $s;
    }

    /**
     * Return a PDF literal string including the outer parentheses.
     *
     * Callers must NOT wrap the result again. Escapes `(`, `)`, `\`,
     * and control characters per ISO 32000-2 S 7.3.4.2.
     */
    private function escapeString(string $text): string
    {
        $escaped = '';
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = $text[$i];
            $escaped .= match ($c) {
                '\\' => '\\\\',
                '('  => '\\(',
                ')'  => '\\)',
                "\n" => '\\n',
                "\r" => '\\r',
                "\t" => '\\t',
                default => $c,
            };
        }
        return '(' . $escaped . ')';
    }
}
