<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader;

use Phpdftk\Encoding\CMapParser;
use Phpdftk\Encoding\GlyphList;
use Phpdftk\Encoding\MacExpertEncodingTable;
use Phpdftk\Encoding\MacRomanTable;
use Phpdftk\Encoding\StandardEncodingTable;
use Phpdftk\Encoding\WinAnsiTable;
use Phpdftk\FontMetrics\StandardFontMetrics;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Reader\Parser\ContentStreamOp;
use Phpdftk\Pdf\Reader\Parser\ContentStreamParser;

/**
 * Extracts text with precise positioning from a PDF page.
 *
 * Implements a full text state machine per ISO 32000-2 §9:
 * - Tracks the current transformation matrix (CTM) via `cm` operator
 * - Tracks the text matrix (Tm) and text line matrix
 * - Applies character spacing (Tc), word spacing (Tw), horizontal scaling (Tz),
 *   text leading (TL), and text rise (Ts)
 * - Resolves glyph widths from font /Widths arrays, /W arrays (CID fonts),
 *   embedded font data, and standard font metrics (14 built-in fonts)
 * - Computes per-span bounding boxes in user space coordinates
 *
 * Each text-showing operator (Tj, TJ, ', ") produces one or more TextSpan
 * objects with the computed position and dimensions.
 */
final class PositionedTextExtractor
{
    private readonly ContentStreamParser $parser;
    private readonly ObjectResolver $resolver;

    // --- Font state ---

    /** @var array<string, array<int, int>> Font name → char code → Unicode codepoint */
    private array $fontMaps = [];

    /** @var array<string, bool> Font names using 2-byte CID encoding */
    private array $cidFonts = [];

    /** @var array<string, array<int, float>> Font name → char code → width in 1/1000 units */
    private array $fontWidths = [];

    /** @var array<string, float> Font name → default/missing width in 1/1000 units */
    private array $fontDefaultWidths = [];

    /** @var array<string, string|null> Font name → base font (PostScript) name */
    private array $fontBaseNames = [];

    // --- Graphics state ---

    /** Current transformation matrix [a, b, c, d, e, f] */
    private array $ctm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /** @var list<array{ctm: array<float>}> */
    private array $graphicsStateStack = [];

    // --- Text state (persists across BT/ET) ---

    private float $charSpacing = 0.0;   // Tc
    private float $wordSpacing = 0.0;   // Tw
    private float $horizontalScaling = 100.0; // Tz (percentage)
    private float $textLeading = 0.0;   // TL
    private float $textRise = 0.0;      // Ts
    private string $currentFont = '';
    private float $fontSize = 12.0;

    // --- Text object state (reset at BT) ---

    /** Text matrix [a, b, c, d, e, f] */
    private array $textMatrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /** Text line matrix [a, b, c, d, e, f] */
    private array $textLineMatrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    // --- XObject state ---

    private ?PdfDictionary $currentResources = null;
    private int $xObjectDepth = 0;
    private const MAX_XOBJECT_DEPTH = 10;

    // --- Marked content ---

    /** @var list<array{actualText: string|null}> */
    private array $markedContentStack = [];
    private bool $suppressText = false;

    public function __construct(ObjectResolver $resolver)
    {
        $this->parser = new ContentStreamParser();
        $this->resolver = $resolver;
    }

    /**
     * Extract positioned text spans from a page dictionary.
     *
     * @return list<TextSpan>
     */
    public function extractFromPage(PdfDictionary $page): array
    {
        $this->loadFontData($page);

        $resources = $this->resolveValue($page->get('Resources'));
        $this->currentResources = $resources instanceof PdfDictionary ? $resources : null;

        $data = $this->getContentStreamData($page);
        if ($data === '') {
            return [];
        }

        $ops = $this->parser->parse($data);
        return $this->processOps($ops);
    }

    /**
     * Process content stream operations and produce positioned text spans.
     *
     * @param list<ContentStreamOp> $ops
     * @return list<TextSpan>
     */
    private function processOps(array $ops): array
    {
        $spans = [];

        foreach ($ops as $op) {
            switch ($op->operator) {
                // --- Graphics state ---
                case 'q':
                    $this->graphicsStateStack[] = ['ctm' => $this->ctm];
                    break;

                case 'Q':
                    if (!empty($this->graphicsStateStack)) {
                        $saved = array_pop($this->graphicsStateStack);
                        $this->ctm = $saved['ctm'];
                    }
                    break;

                case 'cm':
                    if (count($op->operands) >= 6) {
                        $m = array_map('floatval', array_slice($op->operands, 0, 6));
                        $this->ctm = $this->multiplyMatrices($m, $this->ctm);
                    }
                    break;

                    // --- Text object ---
                case 'BT':
                    $this->textMatrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
                    $this->textLineMatrix = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
                    $this->markedContentStack = [];
                    $this->suppressText = false;
                    break;

                case 'ET':
                    break;

                    // --- Text state operators ---
                case 'Tc':
                    if (count($op->operands) >= 1) {
                        $this->charSpacing = (float) $op->operands[0];
                    }
                    break;

                case 'Tw':
                    if (count($op->operands) >= 1) {
                        $this->wordSpacing = (float) $op->operands[0];
                    }
                    break;

                case 'Tz':
                    if (count($op->operands) >= 1) {
                        $this->horizontalScaling = (float) $op->operands[0];
                    }
                    break;

                case 'TL':
                    if (count($op->operands) >= 1) {
                        $this->textLeading = (float) $op->operands[0];
                    }
                    break;

                case 'Ts':
                    if (count($op->operands) >= 1) {
                        $this->textRise = (float) $op->operands[0];
                    }
                    break;

                case 'Tf':
                    if (count($op->operands) >= 2) {
                        $this->currentFont = $this->decodeName(ltrim($op->operands[0], '/'));
                        $this->fontSize = (float) $op->operands[1];
                    }
                    break;

                    // --- Text positioning ---
                case 'Td':
                    if (count($op->operands) >= 2) {
                        $tx = (float) $op->operands[0];
                        $ty = (float) $op->operands[1];
                        $m = [1.0, 0.0, 0.0, 1.0, $tx, $ty];
                        $this->textLineMatrix = $this->multiplyMatrices($m, $this->textLineMatrix);
                        $this->textMatrix = $this->textLineMatrix;
                    }
                    break;

                case 'TD':
                    if (count($op->operands) >= 2) {
                        $tx = (float) $op->operands[0];
                        $ty = (float) $op->operands[1];
                        $this->textLeading = -$ty;
                        $m = [1.0, 0.0, 0.0, 1.0, $tx, $ty];
                        $this->textLineMatrix = $this->multiplyMatrices($m, $this->textLineMatrix);
                        $this->textMatrix = $this->textLineMatrix;
                    }
                    break;

                case 'Tm':
                    if (count($op->operands) >= 6) {
                        $tm = array_map('floatval', array_slice($op->operands, 0, 6));
                        $this->textMatrix = $tm;
                        $this->textLineMatrix = $tm;
                    }
                    break;

                case 'T*':
                    $m = [1.0, 0.0, 0.0, 1.0, 0.0, -$this->textLeading];
                    $this->textLineMatrix = $this->multiplyMatrices($m, $this->textLineMatrix);
                    $this->textMatrix = $this->textLineMatrix;
                    break;

                    // --- Marked content ---
                case 'BMC':
                    $this->markedContentStack[] = ['actualText' => null];
                    break;

                case 'BDC':
                    $actualText = null;
                    if (count($op->operands) >= 2) {
                        $actualText = $this->extractActualText($op->operands[1]);
                    }
                    $this->markedContentStack[] = ['actualText' => $actualText];
                    if ($actualText !== null) {
                        $this->suppressText = true;
                    }
                    break;

                case 'EMC':
                    if (!empty($this->markedContentStack)) {
                        $entry = array_pop($this->markedContentStack);
                        if ($entry['actualText'] !== null) {
                            // Emit the ActualText as a span at the current position
                            $span = $this->buildSpanForText($entry['actualText']);
                            if ($span !== null) {
                                $spans[] = $span;
                            }
                            $this->suppressText = false;
                            foreach ($this->markedContentStack as $stackEntry) {
                                if ($stackEntry['actualText'] !== null) {
                                    $this->suppressText = true;
                                    break;
                                }
                            }
                        }
                    }
                    break;

                    // --- Text showing operators ---
                case 'Tj':
                    if (!$this->suppressText && count($op->operands) >= 1) {
                        $span = $this->showString($op->operands[0]);
                        if ($span !== null) {
                            $spans[] = $span;
                        }
                    }
                    break;

                case 'TJ':
                    if (!$this->suppressText && count($op->operands) >= 1) {
                        $newSpans = $this->showTJArray($op->operands[0]);
                        array_push($spans, ...$newSpans);
                    }
                    break;

                case "'":
                    // T* then Tj
                    $mStar = [1.0, 0.0, 0.0, 1.0, 0.0, -$this->textLeading];
                    $this->textLineMatrix = $this->multiplyMatrices($mStar, $this->textLineMatrix);
                    $this->textMatrix = $this->textLineMatrix;
                    if (!$this->suppressText && count($op->operands) >= 1) {
                        $span = $this->showString($op->operands[0]);
                        if ($span !== null) {
                            $spans[] = $span;
                        }
                    }
                    break;

                case '"':
                    // Set Tw, Tc, then T* then Tj
                    if (count($op->operands) >= 3) {
                        $this->wordSpacing = (float) $op->operands[0];
                        $this->charSpacing = (float) $op->operands[1];
                        $mStar = [1.0, 0.0, 0.0, 1.0, 0.0, -$this->textLeading];
                        $this->textLineMatrix = $this->multiplyMatrices($mStar, $this->textLineMatrix);
                        $this->textMatrix = $this->textLineMatrix;
                        if (!$this->suppressText) {
                            $span = $this->showString($op->operands[2]);
                            if ($span !== null) {
                                $spans[] = $span;
                            }
                        }
                    }
                    break;

                    // --- XObject invocation ---
                case 'Do':
                    if (count($op->operands) >= 1) {
                        $xobjSpans = $this->extractFromXObject(ltrim($op->operands[0], '/'));
                        array_push($spans, ...$xobjSpans);
                    }
                    break;
            }
        }

        return $spans;
    }

    /**
     * Show a single string operand (Tj operator) and advance the text matrix.
     *
     * Returns a TextSpan with the decoded text and computed position/dimensions,
     * or null if the string decodes to empty.
     */
    private function showString(string $operand): ?TextSpan
    {
        $bytes = $this->parseStringOperand($operand);
        $text = $this->mapBytesToUnicode($bytes);

        if ($text === '') {
            return null;
        }

        // Compute position in user space before advancing
        $userPos = $this->textToUserSpace($this->textMatrix);
        $x = $userPos[0];
        $y = $userPos[1];

        // Compute total displacement for this string
        $width = $this->computeStringDisplacement($bytes);

        // Build the span
        $effectiveFontSize = $this->getEffectiveFontSize();
        $span = new TextSpan(
            text: $text,
            x: $x,
            y: $y,
            width: abs($width),
            height: $effectiveFontSize,
            fontSize: $this->fontSize,
            fontName: $this->currentFont,
        );

        // Advance text matrix by the displacement
        $this->advanceTextMatrix($width);

        return $span;
    }

    /**
     * Process a TJ array: [(string) num (string) num ...]
     *
     * String elements produce spans. Numeric elements adjust positioning
     * (in thousandths of a unit of text space, negative = advance right).
     *
     * Adjacent string elements separated only by small adjustments are
     * merged into a single span for usability.
     *
     * @return list<TextSpan>
     */
    private function showTJArray(string $arrayStr): array
    {
        $spans = [];
        $arrayStr = trim($arrayStr, '[] ');
        $len = strlen($arrayStr);
        $pos = 0;

        // Track current run for merging
        $runText = '';
        $runStartX = 0.0;
        $runStartY = 0.0;
        $runWidth = 0.0;
        $runStarted = false;

        while ($pos < $len) {
            // Skip whitespace
            while ($pos < $len && ($arrayStr[$pos] === ' ' || $arrayStr[$pos] === "\n"
                || $arrayStr[$pos] === "\r" || $arrayStr[$pos] === "\t")) {
                $pos++;
            }
            if ($pos >= $len) {
                break;
            }

            $ch = $arrayStr[$pos];

            if ($ch === '(' || $ch === '<') {
                // String element
                if ($ch === '(') {
                    $str = $this->extractLiteralString($arrayStr, $pos);
                    $bytes = $this->unescapeLiteralString($str);
                } else {
                    $hex = $this->extractHexString($arrayStr, $pos);
                    $bytes = hex2bin($hex) ?: '';
                }

                $text = $this->mapBytesToUnicode($bytes);
                if ($text === '') {
                    continue;
                }

                if (!$runStarted) {
                    $userPos = $this->textToUserSpace($this->textMatrix);
                    $runStartX = $userPos[0];
                    $runStartY = $userPos[1];
                    $runStarted = true;
                }

                $displacement = $this->computeStringDisplacement($bytes);
                $runText .= $text;
                $runWidth += $displacement;
                $this->advanceTextMatrix($displacement);

            } elseif ($ch === '-' || $ch === '+' || $ch === '.' || ($ch >= '0' && $ch <= '9')) {
                // Numeric adjustment
                $numStr = '';
                while ($pos < $len && ($arrayStr[$pos] === '-' || $arrayStr[$pos] === '+'
                    || $arrayStr[$pos] === '.' || ($arrayStr[$pos] >= '0' && $arrayStr[$pos] <= '9'))) {
                    $numStr .= $arrayStr[$pos];
                    $pos++;
                }
                $num = (float) $numStr;

                // Convert to text space displacement:
                // TJ numbers are in thousandths of text space, negative = advance right
                $displacement = -$num / 1000.0 * $this->fontSize * ($this->horizontalScaling / 100.0);

                if ($runStarted) {
                    // Large negative number (>100 in magnitude) typically means word space
                    if ($num > 100) {
                        // Flush current run as a span, then start new run
                        if ($runText !== '') {
                            $spans[] = new TextSpan(
                                text: $runText,
                                x: $runStartX,
                                y: $runStartY,
                                width: abs($runWidth),
                                height: $this->getEffectiveFontSize(),
                                fontSize: $this->fontSize,
                                fontName: $this->currentFont,
                            );
                        }
                        $runText = '';
                        $runWidth = 0.0;
                        $runStarted = false;
                    } else {
                        $runWidth += $displacement;
                    }
                }

                $this->advanceTextMatrix($displacement);
            } else {
                $pos++;
            }
        }

        // Flush remaining run
        if ($runStarted && $runText !== '') {
            $spans[] = new TextSpan(
                text: $runText,
                x: $runStartX,
                y: $runStartY,
                width: abs($runWidth),
                height: $this->getEffectiveFontSize(),
                fontSize: $this->fontSize,
                fontName: $this->currentFont,
            );
        }

        return $spans;
    }

    /**
     * Build a TextSpan for a given text string at the current text matrix position.
     *
     * Used for /ActualText where we don't have raw bytes to measure widths.
     * The width is estimated from the text length and a default glyph width.
     */
    private function buildSpanForText(string $text): ?TextSpan
    {
        if ($text === '') {
            return null;
        }

        $userPos = $this->textToUserSpace($this->textMatrix);
        $effectiveFontSize = $this->getEffectiveFontSize();

        // Estimate width: use average glyph width * number of characters
        $charCount = mb_strlen($text, 'UTF-8');
        $defaultWidth = ($this->fontDefaultWidths[$this->currentFont] ?? 500.0);
        $estimatedWidth = $charCount * $defaultWidth / 1000.0 * $this->fontSize
            * ($this->horizontalScaling / 100.0);

        return new TextSpan(
            text: $text,
            x: $userPos[0],
            y: $userPos[1],
            width: abs($estimatedWidth),
            height: $effectiveFontSize,
            fontSize: $this->fontSize,
            fontName: $this->currentFont,
        );
    }

    // -----------------------------------------------------------------------
    // Text displacement computation
    // -----------------------------------------------------------------------

    /**
     * Compute the total horizontal displacement for a string in text space.
     *
     * Per ISO 32000-2 §9.4.4, for each character code c in the string:
     *   tx = ((w0 - Tj/1000) * Tfs + Tc + Tw_if_space) * Th
     *
     * Returns the displacement in user-space points (after scaling).
     */
    private function computeStringDisplacement(string $bytes): float
    {
        $isCid = $this->cidFonts[$this->currentFont] ?? false;
        $widths = $this->fontWidths[$this->currentFont] ?? [];
        $defaultWidth = $this->fontDefaultWidths[$this->currentFont] ?? ($isCid ? 1000.0 : 500.0);
        $th = $this->horizontalScaling / 100.0;

        $totalDisplacement = 0.0;
        $len = strlen($bytes);

        if ($isCid) {
            for ($i = 0; $i + 1 < $len; $i += 2) {
                $code = (ord($bytes[$i]) << 8) | ord($bytes[$i + 1]);
                $w0 = ($widths[$code] ?? $defaultWidth) / 1000.0;
                $tx = ($w0 * $this->fontSize + $this->charSpacing) * $th;
                $totalDisplacement += $tx;
            }
        } else {
            for ($i = 0; $i < $len; $i++) {
                $code = ord($bytes[$i]);
                $w0 = ($widths[$code] ?? $defaultWidth) / 1000.0;
                $tx = ($w0 * $this->fontSize + $this->charSpacing) * $th;
                // Add word spacing for space character (code 32)
                if ($code === 32) {
                    $tx += $this->wordSpacing * $th;
                }
                $totalDisplacement += $tx;
            }
        }

        return $totalDisplacement;
    }

    /**
     * Advance the text matrix by a horizontal displacement.
     */
    private function advanceTextMatrix(float $tx): void
    {
        // Translate text matrix: Tm = [1 0 0 1 tx 0] * Tm
        $this->textMatrix[4] += $tx * $this->textMatrix[0];
        $this->textMatrix[5] += $tx * $this->textMatrix[1];
    }

    /**
     * Convert a text-space position to user-space via CTM.
     *
     * The rendering matrix is: Trm = Tm × CTM
     * The position in user space is (Trm[4], Trm[5] + Ts).
     *
     * @return array{float, float} [x, y]
     */
    private function textToUserSpace(array $tm): array
    {
        // First apply text rise to the text matrix position
        $tmWithRise = $tm;
        $tmWithRise[4] += $this->textRise * $tm[2]; // rise affects y via text matrix
        $tmWithRise[5] += $this->textRise * $tm[3];

        // Multiply Tm × CTM
        $trm = $this->multiplyMatrices($tmWithRise, $this->ctm);

        return [$trm[4], $trm[5]];
    }

    /**
     * Get the effective font size in user space (accounts for text matrix scaling and CTM).
     */
    private function getEffectiveFontSize(): float
    {
        // The effective size is |fontSize * Tm[3] * CTM[3]| approximately
        // For a more accurate computation, use the full matrix scale factor
        $tm = $this->textMatrix;
        $scaleY = sqrt($tm[2] * $tm[2] + $tm[3] * $tm[3]);
        $ctmScaleY = sqrt($this->ctm[2] * $this->ctm[2] + $this->ctm[3] * $this->ctm[3]);
        return abs($this->fontSize * $scaleY * $ctmScaleY);
    }

    // -----------------------------------------------------------------------
    // Matrix math
    // -----------------------------------------------------------------------

    /**
     * Multiply two 3×3 matrices (represented as [a,b,c,d,e,f]).
     *
     * [a1 b1 0]   [a2 b2 0]
     * [c1 d1 0] × [c2 d2 0]
     * [e1 f1 1]   [e2 f2 1]
     *
     * @param array<float> $m1
     * @param array<float> $m2
     * @return array<float>
     */
    private function multiplyMatrices(array $m1, array $m2): array
    {
        return [
            $m1[0] * $m2[0] + $m1[1] * $m2[2],
            $m1[0] * $m2[1] + $m1[1] * $m2[3],
            $m1[2] * $m2[0] + $m1[3] * $m2[2],
            $m1[2] * $m2[1] + $m1[3] * $m2[3],
            $m1[4] * $m2[0] + $m1[5] * $m2[2] + $m2[4],
            $m1[4] * $m2[1] + $m1[5] * $m2[3] + $m2[5],
        ];
    }

    // -----------------------------------------------------------------------
    // Form XObject handling
    // -----------------------------------------------------------------------

    /**
     * Extract positioned text from a Form XObject.
     *
     * @return list<TextSpan>
     */
    private function extractFromXObject(string $name): array
    {
        if ($this->currentResources === null || $this->xObjectDepth >= self::MAX_XOBJECT_DEPTH) {
            return [];
        }

        $xobjects = $this->resolveValue($this->currentResources->get('XObject'));
        if (!$xobjects instanceof PdfDictionary) {
            return [];
        }

        $xobjRef = $xobjects->get($name);
        if ($xobjRef === null) {
            return [];
        }

        $xobj = $this->resolveValue($xobjRef);
        if (!$xobj instanceof PdfStream) {
            return [];
        }

        $subtype = $xobj->dictionary->get('Subtype');
        if (!$subtype instanceof PdfName || $subtype->value !== 'Form') {
            return [];
        }

        // Save state
        $savedFontMaps = $this->fontMaps;
        $savedCidFonts = $this->cidFonts;
        $savedFontWidths = $this->fontWidths;
        $savedDefaultWidths = $this->fontDefaultWidths;
        $savedBaseNames = $this->fontBaseNames;
        $savedFont = $this->currentFont;
        $savedFontSize = $this->fontSize;
        $savedResources = $this->currentResources;
        $savedCtm = $this->ctm;

        // Apply the XObject's matrix if present
        $matrix = $xobj->dictionary->get('Matrix');
        if ($matrix instanceof PdfArray && count($matrix->items) >= 6) {
            $m = [];
            foreach ($matrix->items as $item) {
                $m[] = $item instanceof PdfNumber ? (float) $item->toPdf() : 0.0;
            }
            $this->ctm = $this->multiplyMatrices(array_slice($m, 0, 6), $this->ctm);
        }

        // Load XObject's resources
        $xobjResources = $this->resolveValue($xobj->dictionary->get('Resources'));
        if ($xobjResources instanceof PdfDictionary) {
            $this->currentResources = $xobjResources;
            $this->loadFontDataFromResources($xobjResources);
        }

        // Process
        $this->xObjectDepth++;
        $spans = [];
        if ($xobj->data !== '') {
            $ops = $this->parser->parse($xobj->data);
            $spans = $this->processOps($ops);
        }
        $this->xObjectDepth--;

        // Restore state
        $this->fontMaps = $savedFontMaps;
        $this->cidFonts = $savedCidFonts;
        $this->fontWidths = $savedFontWidths;
        $this->fontDefaultWidths = $savedDefaultWidths;
        $this->fontBaseNames = $savedBaseNames;
        $this->currentFont = $savedFont;
        $this->fontSize = $savedFontSize;
        $this->currentResources = $savedResources;
        $this->ctm = $savedCtm;

        return $spans;
    }

    // -----------------------------------------------------------------------
    // Font data loading
    // -----------------------------------------------------------------------

    /**
     * Decode PDF name `#XX` hex escapes (PDF 1.2+) so a content-stream name
     * like `/*Courier#20New` matches the literal-space resource key.
     */
    private function decodeName(string $name): string
    {
        return preg_replace_callback(
            '/#([0-9A-Fa-f]{2})/',
            static fn(array $m): string => chr((int) hexdec($m[1])),
            $name,
        );
    }

    /**
     * Load font encoding maps AND width data from the page's resources.
     */
    private function loadFontData(PdfDictionary $page): void
    {
        $this->fontMaps = [];
        $this->cidFonts = [];
        $this->fontWidths = [];
        $this->fontDefaultWidths = [];
        $this->fontBaseNames = [];

        $resources = $this->resolveValue($page->get('Resources'));
        if ($resources instanceof PdfDictionary) {
            $this->loadFontDataFromResources($resources);
        }
    }

    private function loadFontDataFromResources(PdfDictionary $resources): void
    {
        $fonts = $this->resolveValue($resources->get('Font'));
        if (!$fonts instanceof PdfDictionary) {
            return;
        }

        $cmapParser = new CMapParser();

        foreach ($fonts->entries as $fontName => $fontRef) {
            $fontDict = $this->resolveValue($fontRef);
            if (!$fontDict instanceof PdfDictionary) {
                continue;
            }

            $subtype = $fontDict->get('Subtype');
            $isType0 = $subtype instanceof PdfName && $subtype->value === 'Type0';
            if ($isType0) {
                $this->cidFonts[$fontName] = true;
            }

            // Base font name (for standard font metric lookup)
            $baseFont = $fontDict->get('BaseFont');
            $baseFontName = $baseFont instanceof PdfName ? $baseFont->value : null;
            $this->fontBaseNames[$fontName] = $baseFontName;

            // --- Load encoding map (same as TextExtractor) ---
            $this->loadEncodingMap($fontName, $fontDict, $cmapParser, $subtype);

            // --- Load glyph widths ---
            $this->loadGlyphWidths($fontName, $fontDict, $isType0, $baseFontName);
        }
    }

    private function loadEncodingMap(
        string $fontName,
        PdfDictionary $fontDict,
        CMapParser $cmapParser,
        ?PdfName $subtype,
    ): void {
        // ToUnicode CMap
        $toUnicode = $fontDict->get('ToUnicode');
        if ($toUnicode !== null) {
            $toUnicodeStream = $this->resolveValue($toUnicode);
            if ($toUnicodeStream instanceof PdfStream && $toUnicodeStream->data !== '') {
                $map = $cmapParser->parse($toUnicodeStream->data);
                if (!empty($map)) {
                    $this->fontMaps[$fontName] = $map;
                    return;
                }
            }
        }

        // /Encoding with /Differences
        $encoding = $fontDict->get('Encoding');
        if ($encoding !== null) {
            $map = $this->buildEncodingMap($encoding);
            if (!empty($map)) {
                $this->fontMaps[$fontName] = $map;
                return;
            }
        }

        // Fallback for simple fonts
        if ($subtype instanceof PdfName && in_array($subtype->value, ['Type1', 'MMType1', 'TrueType'], true)) {
            $fallbackTable = ($subtype->value === 'TrueType')
                ? WinAnsiTable::getTable()
                : StandardEncodingTable::getTable();
            $glyphList = GlyphList::getList();
            $map = [];
            foreach ($fallbackTable as $code => $glyphName) {
                if (isset($glyphList[$glyphName])) {
                    $map[$code] = $glyphList[$glyphName];
                }
            }
            if (!empty($map)) {
                $this->fontMaps[$fontName] = $map;
            }
        }
    }

    /**
     * Load glyph widths from the font dictionary.
     *
     * Tries in order:
     * 1. /Widths array (simple fonts)
     * 2. /DescendantFonts → /W array (CID fonts)
     * 3. /DescendantFonts → /DW (CID default width)
     * 4. Standard 14 font metrics
     * 5. FontDescriptor /MissingWidth
     */
    private function loadGlyphWidths(
        string $fontName,
        PdfDictionary $fontDict,
        bool $isType0,
        ?string $baseFontName,
    ): void {
        // 1. /Widths array (simple fonts: Type1, TrueType)
        $widthsArr = $fontDict->get('Widths');
        $firstChar = $fontDict->get('FirstChar');
        if ($widthsArr instanceof PdfArray && $firstChar instanceof PdfNumber) {
            $fc = (int) $firstChar->toPdf();
            $widths = [];
            foreach ($widthsArr->items as $i => $w) {
                if ($w instanceof PdfNumber) {
                    $widths[$fc + $i] = (float) $w->toPdf();
                }
            }
            if (!empty($widths)) {
                $this->fontWidths[$fontName] = $widths;
                $this->loadDefaultWidth($fontName, $fontDict, $baseFontName);
                return;
            }
        }

        // 2. CID font /W and /DW from /DescendantFonts
        if ($isType0) {
            $descendants = $fontDict->get('DescendantFonts');
            if ($descendants instanceof PdfArray && !empty($descendants->items)) {
                $cidFontDict = $this->resolveValue($descendants->items[0]);
                if ($cidFontDict instanceof PdfDictionary) {
                    $this->loadCidWidths($fontName, $cidFontDict);

                    // CID base font name for standard metrics
                    $cidBaseFont = $cidFontDict->get('BaseFont');
                    if ($cidBaseFont instanceof PdfName) {
                        $baseFontName = $cidBaseFont->value;
                        $this->fontBaseNames[$fontName] = $baseFontName;
                    }

                    // /DW default width
                    $dw = $cidFontDict->get('DW');
                    if ($dw instanceof PdfNumber) {
                        $this->fontDefaultWidths[$fontName] = (float) $dw->toPdf();
                    } else {
                        $this->fontDefaultWidths[$fontName] = 1000.0;
                    }
                    return;
                }
            }
        }

        // 3. Standard 14 font metrics
        if ($baseFontName !== null) {
            $this->tryLoadStandardFontWidths($fontName, $baseFontName);
            if (isset($this->fontWidths[$fontName])) {
                return;
            }
        }

        // 4. FontDescriptor /MissingWidth fallback
        $this->loadDefaultWidth($fontName, $fontDict, $baseFontName);
    }

    /**
     * Load CID font /W array into fontWidths.
     *
     * The /W array format is: [cid_first cid_last width] or [cid [w1 w2 ...]]
     */
    private function loadCidWidths(string $fontName, PdfDictionary $cidFontDict): void
    {
        $wArray = $cidFontDict->get('W');
        if (!$wArray instanceof PdfArray) {
            return;
        }

        $widths = [];
        $items = $wArray->items;
        $count = count($items);
        $i = 0;

        while ($i < $count) {
            $first = $items[$i] ?? null;
            if (!$first instanceof PdfNumber) {
                $i++;
                continue;
            }
            $firstCid = (int) $first->toPdf();

            $second = $items[$i + 1] ?? null;
            if ($second instanceof PdfArray) {
                // [cid [w1 w2 ...]]
                foreach ($second->items as $j => $w) {
                    if ($w instanceof PdfNumber) {
                        $widths[$firstCid + $j] = (float) $w->toPdf();
                    }
                }
                $i += 2;
            } elseif ($second instanceof PdfNumber) {
                $lastCid = (int) $second->toPdf();
                $width = $items[$i + 2] ?? null;
                if ($width instanceof PdfNumber) {
                    $w = (float) $width->toPdf();
                    for ($c = $firstCid; $c <= $lastCid; $c++) {
                        $widths[$c] = $w;
                    }
                }
                $i += 3;
            } else {
                $i++;
            }
        }

        if (!empty($widths)) {
            $this->fontWidths[$fontName] = $widths;
        }
    }

    /**
     * Try to load widths from the 14 standard PDF fonts.
     */
    private function tryLoadStandardFontWidths(string $fontName, string $baseFontName): void
    {
        // Strip subset prefix (e.g., "ABCDEF+Helvetica" → "Helvetica")
        $cleanName = preg_replace('/^[A-Z]{6}\+/', '', $baseFontName) ?? $baseFontName;

        try {
            $afm = StandardFontMetrics::get($cleanName);
        } catch (\InvalidArgumentException) {
            return;
        }

        // Convert glyph-name-keyed widths to char-code-keyed widths via WinAnsi encoding
        $winAnsi = WinAnsiTable::getTable();
        $widths = [];
        foreach ($winAnsi as $code => $glyphName) {
            $w = $afm->widths[$glyphName] ?? null;
            if ($w !== null) {
                $widths[$code] = (float) $w;
            }
        }

        if (!empty($widths)) {
            $this->fontWidths[$fontName] = $widths;
            $this->fontDefaultWidths[$fontName] = $afm->missingWidth;
        }
    }

    /**
     * Set the default/missing width for a font from its FontDescriptor or
     * standard metrics.
     */
    private function loadDefaultWidth(string $fontName, PdfDictionary $fontDict, ?string $baseFontName): void
    {
        if (isset($this->fontDefaultWidths[$fontName])) {
            return;
        }

        // Try FontDescriptor /MissingWidth
        $descriptor = $this->resolveValue($fontDict->get('FontDescriptor'));
        if ($descriptor instanceof PdfDictionary) {
            $mw = $descriptor->get('MissingWidth');
            if ($mw instanceof PdfNumber) {
                $this->fontDefaultWidths[$fontName] = (float) $mw->toPdf();
                return;
            }
        }

        // Standard font fallback
        if ($baseFontName !== null) {
            $cleanName = preg_replace('/^[A-Z]{6}\+/', '', $baseFontName) ?? $baseFontName;
            try {
                $afm = StandardFontMetrics::get($cleanName);
                $this->fontDefaultWidths[$fontName] = $afm->missingWidth;
                return;
            } catch (\InvalidArgumentException) {
                // Not a standard font
            }
        }

        $this->fontDefaultWidths[$fontName] = 500.0;
    }

    // -----------------------------------------------------------------------
    // String / encoding helpers (mirrored from TextExtractor)
    // -----------------------------------------------------------------------

    private function parseStringOperand(string $operand): string
    {
        $operand = trim($operand);

        if (str_starts_with($operand, '<') && str_ends_with($operand, '>')) {
            $hex = substr($operand, 1, -1);
            $hex = preg_replace('/\s+/', '', $hex) ?? $hex;
            return hex2bin($hex) ?: '';
        }

        if (str_starts_with($operand, '(') && str_ends_with($operand, ')')) {
            $inner = substr($operand, 1, -1);
            return $this->unescapeLiteralString($inner);
        }

        return $operand;
    }

    private function mapBytesToUnicode(string $bytes): string
    {
        if ($this->containsMultibyte($bytes) && mb_check_encoding($bytes, 'UTF-8')) {
            return $bytes;
        }

        $fontMap = $this->fontMaps[$this->currentFont] ?? null;
        $isCid = $this->cidFonts[$this->currentFont] ?? false;

        if ($fontMap !== null && $isCid) {
            $result = '';
            $len = strlen($bytes);
            for ($i = 0; $i + 1 < $len; $i += 2) {
                $code = (ord($bytes[$i]) << 8) | ord($bytes[$i + 1]);
                if (isset($fontMap[$code])) {
                    $result .= mb_chr($fontMap[$code], 'UTF-8');
                } else {
                    $result .= "\u{FFFD}";
                }
            }
            return $result;
        }

        if ($fontMap !== null) {
            $result = '';
            $len = strlen($bytes);
            for ($i = 0; $i < $len; $i++) {
                $code = ord($bytes[$i]);
                if (isset($fontMap[$code])) {
                    $result .= mb_chr($fontMap[$code], 'UTF-8');
                } else {
                    $result .= mb_chr($code, 'UTF-8');
                }
            }
            return $result;
        }

        return $this->winAnsiFallback($bytes);
    }

    private function containsMultibyte(string $bytes): bool
    {
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            if (ord($bytes[$i]) > 127) {
                return true;
            }
        }
        return false;
    }

    private function winAnsiFallback(string $bytes): string
    {
        static $winAnsi = null;
        static $glyphList = null;
        if ($winAnsi === null) {
            $winAnsi = WinAnsiTable::getTable();
            $glyphList = GlyphList::getList();
        }

        $result = '';
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $code = ord($bytes[$i]);
            $glyphName = $winAnsi[$code] ?? null;
            if ($glyphName !== null && isset($glyphList[$glyphName])) {
                $result .= mb_chr($glyphList[$glyphName], 'UTF-8');
            } else {
                $result .= mb_chr($code, 'UTF-8');
            }
        }
        return $result;
    }

    /**
     * @return array<int, int>
     */
    private function buildEncodingMap(mixed $encoding): array
    {
        $glyphList = GlyphList::getList();
        $map = [];

        if ($encoding instanceof PdfName) {
            $table = $this->getNamedEncodingTable($encoding->value);
            if ($table !== null) {
                foreach ($table as $code => $glyphName) {
                    if (isset($glyphList[$glyphName])) {
                        $map[$code] = $glyphList[$glyphName];
                    }
                }
            }
            return $map;
        }

        $encodingDict = $this->resolveValue($encoding);
        if (!$encodingDict instanceof PdfDictionary) {
            return $map;
        }

        $baseEnc = $encodingDict->get('BaseEncoding');
        if ($baseEnc instanceof PdfName) {
            $table = $this->getNamedEncodingTable($baseEnc->value);
            if ($table !== null) {
                foreach ($table as $code => $glyphName) {
                    if (isset($glyphList[$glyphName])) {
                        $map[$code] = $glyphList[$glyphName];
                    }
                }
            }
        }

        $diffs = $encodingDict->get('Differences');
        if ($diffs instanceof PdfArray) {
            $code = 0;
            foreach ($diffs->items as $item) {
                if ($item instanceof PdfNumber) {
                    $code = (int) $item->toPdf();
                } elseif ($item instanceof PdfName) {
                    if (isset($glyphList[$item->value])) {
                        $map[$code] = $glyphList[$item->value];
                    }
                    $code++;
                }
            }
        }

        return $map;
    }

    /** @return array<int, string>|null */
    private function getNamedEncodingTable(string $name): ?array
    {
        return match ($name) {
            'WinAnsiEncoding' => WinAnsiTable::getTable(),
            'MacRomanEncoding' => MacRomanTable::getTable(),
            'StandardEncoding' => StandardEncodingTable::getTable(),
            'MacExpertEncoding' => MacExpertEncodingTable::getTable(),
            default => null,
        };
    }

    private function extractActualText(string $operand): ?string
    {
        $operand = trim($operand);
        if (!str_starts_with($operand, '<<')) {
            return null;
        }

        if (preg_match('/\/ActualText\s+\(/', $operand, $matches, PREG_OFFSET_CAPTURE)) {
            $startPos = (int) $matches[0][1];
            $parenPos = strpos($operand, '(', $startPos);
            if ($parenPos !== false) {
                $pos = $parenPos;
                $str = $this->extractLiteralString($operand, $pos);
                return $this->unescapeLiteralString($str);
            }
        }

        if (preg_match('/\/ActualText\s+<([0-9A-Fa-f\s]+)>/', $operand, $matches)) {
            $hex = preg_replace('/\s+/', '', $matches[1]) ?? $matches[1];
            $bytes = hex2bin($hex);
            return $bytes !== false ? $bytes : null;
        }

        return null;
    }

    private function resolveValue(mixed $value): mixed
    {
        if ($value instanceof PdfReference) {
            return $this->resolver->resolveReference($value);
        }
        return $value;
    }

    private function getContentStreamData(PdfDictionary $page): string
    {
        $contents = $page->get('Contents');
        if ($contents === null) {
            return '';
        }

        if ($contents instanceof PdfReference) {
            $obj = $this->resolver->resolveReference($contents);
            if ($obj instanceof PdfStream) {
                return $obj->data;
            }
            if ($obj instanceof PdfArray) {
                $contents = $obj;
            } else {
                return '';
            }
        }

        if ($contents instanceof PdfArray) {
            $data = '';
            foreach ($contents->items as $ref) {
                if ($ref instanceof PdfReference) {
                    $stream = $this->resolver->resolveReference($ref);
                    if ($stream instanceof PdfStream) {
                        $data .= $stream->data . "\n";
                    }
                }
            }
            return $data;
        }

        return '';
    }

    private function unescapeLiteralString(string $str): string
    {
        $result = '';
        $len = strlen($str);
        $i = 0;

        while ($i < $len) {
            $ch = $str[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $i++;
                $next = $str[$i];
                $result .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\x0C",
                    '(' => '(',
                    ')' => ')',
                    '\\' => '\\',
                    default => $this->readOctalOrLiteral($str, $i, $next),
                };
            } else {
                $result .= $ch;
            }
            $i++;
        }

        return $result;
    }

    private function readOctalOrLiteral(string $str, int &$i, string $ch): string
    {
        if ($ch >= '0' && $ch <= '7') {
            $octal = $ch;
            $len = strlen($str);
            for ($j = 0; $j < 2 && $i + 1 < $len; $j++) {
                $next = $str[$i + 1];
                if ($next >= '0' && $next <= '7') {
                    $octal .= $next;
                    $i++;
                } else {
                    break;
                }
            }
            return chr((int) octdec($octal));
        }
        return $ch;
    }

    private function extractLiteralString(string $data, int &$pos): string
    {
        $pos++; // skip (
        $result = '';
        $depth = 1;
        $len = strlen($data);

        while ($pos < $len && $depth > 0) {
            $ch = $data[$pos];
            if ($ch === '(') {
                $depth++;
                $result .= '(';
            } elseif ($ch === ')') {
                $depth--;
                if ($depth > 0) {
                    $result .= ')';
                }
            } elseif ($ch === '\\') {
                $result .= '\\';
                $pos++;
                if ($pos < $len) {
                    $result .= $data[$pos];
                }
            } else {
                $result .= $ch;
            }
            $pos++;
        }

        return $result;
    }

    private function extractHexString(string $data, int &$pos): string
    {
        $pos++; // skip <
        $hex = '';
        $len = strlen($data);

        while ($pos < $len && $data[$pos] !== '>') {
            if (!ctype_space($data[$pos])) {
                $hex .= $data[$pos];
            }
            $pos++;
        }
        if ($pos < $len) {
            $pos++; // skip >
        }
        return $hex;
    }
}
