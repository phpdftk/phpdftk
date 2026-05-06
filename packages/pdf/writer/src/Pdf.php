<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Encoding\WinAnsiTable;
use Phpdftk\FontMetrics\AfmData;
use Phpdftk\FontMetrics\StandardFontMetrics;
use Phpdftk\ImageMetadata\ImageParser;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Font\StandardFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\Pdf\Core\PdfVersion;

/**
 * High-level PDF document builder — **zero PDF object-model knowledge
 * required**.
 *
 * `Pdf` is a stateful, cursor-driven builder on top of {@see PdfWriter}.
 * It maintains a current page, a text cursor, and a default font; each
 * call flows content downward from the top margin, automatically
 * breaking to a new page when the content column fills up.
 *
 * ### Example
 *
 * ```php
 * $pdf = new Pdf();                              // Letter, 72pt margins, Helvetica 11
 * $pdf->addHeading('Welcome', 1);
 * $pdf->addText('This is body text. It wraps automatically to the content');
 * $pdf->addText('column width, and overflowing content starts a new page.');
 * $pdf->addSpacer(12);
 * $pdf->addImage('/path/to/photo.jpg', width: 300);
 * $pdf->save('/out.pdf');
 * ```
 *
 * ### Output modes
 *
 * Three mutually-exclusive ways to emit the finished document:
 *
 *   - `save(string $path)` — write to a file
 *   - `toBytes(): string` — get the bytes as a string
 *   - `writeTo($resource): int` — write to an open stream resource
 *
 * ### Scope
 *
 * Phase 1 handles the 80% case: word-wrapped body text, H1–H6 headings,
 * images with auto-scaling, spacers, horizontal rules, explicit and
 * automatic page breaks, and Left/Center/Right alignment. Fonts are
 * limited to the 14 standard PDF fonts (Helvetica / Times / Courier
 * families plus Symbol and ZapfDingbats). For custom TrueType fonts,
 * embedded images with precise transforms, tables, or absolute-positioned
 * graphics, drop to the underlying {@see PdfWriter} via {@see writer()}.
 *
 * @api
 */
class Pdf
{
    private PdfWriter $writer;
    private Theme $theme;
    private PageSize $pageSize;

    private ?Page $currentPage = null;

    /**
     * Direct content-stream handle for cursor-based text rendering.
     * Retrieved from the Writer\Page escape hatch.
     */
    private ?ContentStream $currentStream = null;

    /** Current font family (resolved to standard-14 PostScript name family) */
    private string $font;
    private float $fontSize;
    private bool $bold = false;
    private bool $italic = false;

    /**
     * Current cursor, top-down from the page top-left corner.
     * `$cursorY` decreases as content is added. A fresh page starts with
     * `cursorY = pageHeight - theme->margin`.
     */
    private float $cursorY = 0.0;

    /** Remember current fill color so we only emit it when it changes. */
    private ?string $lastFillColor = null;

    /** @var array<string, string> family+variant key => registered font resource name ("F1") */
    private array $fontResourceCache = [];

    /** @var array<string, AfmData> family+variant key => AFM metrics for width measurement */
    private array $fontMetricsCache = [];

    /** @var array<int, string> WinAnsi byte → glyph name */
    private readonly array $winAnsi;

    public function __construct(
        PageSize $pageSize = PageSize::Letter,
        ?Theme $theme = null,
        bool $compressStreams = true,
    ) {
        $this->writer = new PdfWriter($compressStreams);
        $this->pageSize = $pageSize;
        $this->theme = $theme ?? new Theme();
        $this->font = $this->theme->family;
        $this->fontSize = $this->theme->fontSize;
        $this->winAnsi = WinAnsiTable::getTable();
    }

    // -----------------------------------------------------------------------
    // Theme / font state
    // -----------------------------------------------------------------------

    /**
     * Set the default body font for subsequent content. Accepts one of
     * the standard 14 PDF font families: Helvetica, Times, Courier,
     * Symbol, ZapfDingbats.
     */
    public function setFont(string $family, float $size, bool $bold = false, bool $italic = false): self
    {
        $this->font = $family;
        $this->fontSize = $size;
        $this->bold = $bold;
        $this->italic = $italic;
        return $this;
    }

    public function setTheme(Theme $theme): self
    {
        $this->theme = $theme;
        $this->font = $theme->family;
        $this->fontSize = $theme->fontSize;
        return $this;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function getPdfVersion(): PdfVersion
    {
        return $this->writer->getPdfVersion();
    }

    /**
     * Escape hatch: returns the underlying {@see PdfWriter} so callers
     * can mix high-level flow with low-level object-model operations.
     * Reaching for this means you're stepping outside the "no PDF
     * knowledge required" guarantee — which is fine, that's what it's
     * for.
     */
    public function writer(): PdfWriter
    {
        return $this->writer;
    }

    // -----------------------------------------------------------------------
    // Pages
    // -----------------------------------------------------------------------

    /**
     * Start a new page. The first `add*` call will also start a page
     * automatically if one has not yet been created, so calling this
     * explicitly is only required when you want to force a page break
     * or use a non-default size.
     */
    public function addPage(?PageSize $size = null): self
    {
        $size ??= $this->pageSize;
        $this->pageSize = $size;
        $this->currentPage = $this->writer->addPage($size->width(), $size->height());
        $this->currentStream = $this->currentPage->contentStream();
        $this->cursorY = $size->height() - $this->theme->margin;
        $this->lastFillColor = null;
        return $this;
    }

    /** Force a page break. Equivalent to `addPage()` with the current size. */
    public function newPage(): self
    {
        return $this->addPage();
    }

    // -----------------------------------------------------------------------
    // Content
    // -----------------------------------------------------------------------

    /**
     * Add a paragraph of body text. Text is word-wrapped at the current
     * content column width and flows downward from the cursor. If a
     * paragraph runs past the bottom margin, the remaining lines
     * continue on a new automatically-created page.
     */
    public function addText(string $text, ?TextStyle $style = null): self
    {
        $this->ensurePage();

        $style ??= new TextStyle();
        $family = $style->family ?? $this->font;
        $size   = $style->size   ?? $this->fontSize;
        $bold   = $style->bold   ?? $this->bold;
        $italic = $style->italic ?? $this->italic;
        $color  = $style->color  ?? $this->theme->color;
        $align  = $style->alignment ?? Alignment::Left;

        $postScriptName = $this->resolveFontName($family, $bold, $italic);
        $metrics = $this->getMetrics($postScriptName);
        $resourceName = $this->ensureFontResource($postScriptName);

        $lineHeight = $size * $this->theme->lineHeight;
        $columnWidth = $this->contentWidth();

        $lines = $this->wrapText($text, $metrics, $size, $columnWidth);

        $this->applyFillColor($color);

        foreach ($lines as $line) {
            // Need room for one more line? If not, paginate.
            if ($this->cursorY - $lineHeight < $this->theme->margin) {
                $this->newPage();
                $this->applyFillColor($color);
            }

            // Empty lines (explicit paragraph breaks within the input
            // string) still consume one line of vertical space.
            if ($line === '') {
                $this->cursorY -= $lineHeight;
                continue;
            }

            $lineWidth = $this->measureText($line, $metrics, $size);
            $x = $this->theme->margin + match ($align) {
                Alignment::Left   => 0.0,
                Alignment::Center => ($columnWidth - $lineWidth) / 2.0,
                Alignment::Right  => $columnWidth - $lineWidth,
            };
            // PDF text origin is at the baseline, so we drop an additional
            // font size to land the top of the glyph at the cursor.
            $baselineY = $this->cursorY - $size;

            $this->currentStream
                ->beginText()
                ->setFont($resourceName, $size)
                ->moveTextPosition($x, $baselineY)
                ->showText($line)
                ->endText();

            $this->cursorY -= $lineHeight;
        }

        $this->cursorY -= $this->theme->paragraphSpacing;
        return $this;
    }

    /**
     * Add a heading (H1–H6) using the theme's heading style for the
     * given level.
     */
    public function addHeading(string $text, int $level = 1): self
    {
        $style = $this->theme->heading($level);

        $this->addSpacer($style['spaceAbove']);
        $this->addText(
            $text,
            new TextStyle(
                size: $style['size'],
                bold: $style['bold'],
            ),
        );
        // addText already left us one paragraphSpacing below; replace
        // it with the heading's own spaceBelow.
        $this->cursorY += $this->theme->paragraphSpacing;
        $this->cursorY -= $style['spaceBelow'];
        return $this;
    }

    /**
     * Add vertical whitespace (points).
     */
    public function addSpacer(float $points): self
    {
        $this->ensurePage();
        $this->cursorY -= $points;
        if ($this->cursorY < $this->theme->margin) {
            $this->newPage();
        }
        return $this;
    }

    /**
     * Add a horizontal rule spanning the content column.
     */
    public function addRule(float $lineWidth = 0.5): self
    {
        $this->ensurePage();
        $y = $this->cursorY - $lineWidth;
        if ($y < $this->theme->margin) {
            $this->newPage();
            $y = $this->cursorY - $lineWidth;
        }

        $this->currentStream
            ->saveGraphicsState()
            ->setLineWidth($lineWidth)
            ->setStrokeColorRGB(0, 0, 0)
            ->moveTo($this->theme->margin, $y)
            ->lineTo($this->theme->margin + $this->contentWidth(), $y)
            ->stroke()
            ->restoreGraphicsState();

        $this->cursorY -= $lineWidth * 2 + $this->theme->paragraphSpacing;
        return $this;
    }

    /**
     * Add an image. If neither width nor height is given, the image is
     * placed at its natural size in points (1 image pixel = 1 point).
     * If one dimension is given the other is scaled proportionally. If
     * both are given the image is stretched to fit.
     */
    public function addImage(
        string $path,
        ?float $width = null,
        ?float $height = null,
        Alignment $align = Alignment::Left,
    ): self {
        $this->ensurePage();

        $info = ImageParser::parse($path);
        $naturalW = (float) $info->width;
        $naturalH = (float) $info->height;

        if ($width === null && $height === null) {
            $w = $naturalW;
            $h = $naturalH;
        } elseif ($width !== null && $height === null) {
            $w = $width;
            $h = $naturalH * ($width / $naturalW);
        } elseif ($width === null && $height !== null) {
            $h = $height;
            $w = $naturalW * ($height / $naturalH);
        } else {
            $w = (float) $width;
            $h = (float) $height;
        }

        // Paginate if the image is taller than the remaining column room.
        if ($this->cursorY - $h < $this->theme->margin) {
            $this->newPage();
        }

        $columnWidth = $this->contentWidth();
        $x = $this->theme->margin + match ($align) {
            Alignment::Left   => 0.0,
            Alignment::Center => ($columnWidth - $w) / 2.0,
            Alignment::Right  => $columnWidth - $w,
        };
        $y = $this->cursorY - $h;

        $this->currentPage->drawImage($path, $x, $y, $w, $h);

        $this->cursorY -= $h + $this->theme->paragraphSpacing;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    public function save(string $path): void
    {
        $this->writer->save($path);
    }

    public function toBytes(): string
    {
        return $this->writer->toBytes();
    }

    /** @param resource $stream */
    public function writeTo($stream): int
    {
        return $this->writer->writeTo($stream);
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    private function ensurePage(): void
    {
        if ($this->currentPage === null) {
            $this->addPage();
        }
    }

    private function contentWidth(): float
    {
        return $this->pageSize->width() - (2 * $this->theme->margin);
    }

    /**
     * Resolve a (family, bold, italic) tuple to a standard-14 PostScript
     * name. Falls back to the regular variant if the bold/italic
     * combination is not a standard font.
     */
    private function resolveFontName(string $family, bool $bold, bool $italic): string
    {
        $map = [
            'Helvetica' => [
                '00' => 'Helvetica',
                '10' => 'Helvetica-Bold',
                '01' => 'Helvetica-Oblique',
                '11' => 'Helvetica-BoldOblique',
            ],
            'Times' => [
                '00' => 'Times-Roman',
                '10' => 'Times-Bold',
                '01' => 'Times-Italic',
                '11' => 'Times-BoldItalic',
            ],
            'Courier' => [
                '00' => 'Courier',
                '10' => 'Courier-Bold',
                '01' => 'Courier-Oblique',
                '11' => 'Courier-BoldOblique',
            ],
            'Symbol' => [
                '00' => 'Symbol', '10' => 'Symbol', '01' => 'Symbol', '11' => 'Symbol',
            ],
            'ZapfDingbats' => [
                '00' => 'ZapfDingbats', '10' => 'ZapfDingbats',
                '01' => 'ZapfDingbats', '11' => 'ZapfDingbats',
            ],
        ];
        if (!isset($map[$family])) {
            throw new \InvalidArgumentException(
                "Unknown standard font family: $family (expected Helvetica, Times, Courier, Symbol, or ZapfDingbats)",
            );
        }
        $key = ($bold ? '1' : '0') . ($italic ? '1' : '0');
        return $map[$family][$key];
    }

    private function getMetrics(string $postScriptName): AfmData
    {
        return $this->fontMetricsCache[$postScriptName]
            ??= StandardFontMetrics::get($postScriptName);
    }

    /**
     * Ensure the given standard font is registered in the underlying
     * writer and return the assigned resource name (F1, F2, …).
     */
    private function ensureFontResource(string $postScriptName): string
    {
        if (isset($this->fontResourceCache[$postScriptName])) {
            return $this->fontResourceCache[$postScriptName];
        }
        $standardCase = StandardFont::from($postScriptName);
        $fontHandle = $this->writer->addFont(new Type1Font($standardCase));
        $name = $fontHandle->getResourceName();
        $this->fontResourceCache[$postScriptName] = $name;
        return $name;
    }

    /**
     * Greedy word-wrap: split on whitespace, pack words onto lines until
     * the next word would overflow the column width. A single word
     * wider than the column is emitted on its own line without mid-word
     * breaking.
     *
     * Empty input and explicit newlines produce paragraph breaks within
     * the returned line list.
     *
     * @return list<string>
     */
    private function wrapText(string $text, AfmData $metrics, float $size, float $columnWidth): array
    {
        $out = [];
        // Normalize line endings and keep explicit paragraph breaks.
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = explode("\n", $text);

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            if ($words === [''] || $words === []) {
                $out[] = '';
                continue;
            }
            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : ($line . ' ' . $word);
                if ($this->measureText($candidate, $metrics, $size) <= $columnWidth) {
                    $line = $candidate;
                } else {
                    if ($line !== '') {
                        $out[] = $line;
                    }
                    $line = $word;
                }
            }
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }

    /**
     * Measure a line of text in points at the given size, using the
     * WinAnsi byte → glyph name mapping and AFM widths.
     */
    private function measureText(string $text, AfmData $metrics, float $size): float
    {
        $units = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($text[$i]);
            $glyph = $this->winAnsi[$byte] ?? '.notdef';
            $units += $metrics->getWidth($glyph);
        }
        return ($units / 1000.0) * $size;
    }

    /** @param array{float,float,float} $color */
    private function applyFillColor(array $color): void
    {
        $key = sprintf('%.4f %.4f %.4f', $color[0], $color[1], $color[2]);
        if ($this->lastFillColor === $key) {
            return;
        }
        $this->currentStream->setFillColorRGB($color[0], $color[1], $color[2]);
        $this->lastFillColor = $key;
    }
}
