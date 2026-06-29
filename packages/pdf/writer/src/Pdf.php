<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

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
    private PdfDoc $doc;
    private PdfWriter $writer;
    private Theme $theme;
    private PageSize $pageSize;

    private ?Page $currentPage = null;

    /**
     * Every page added through {@see addPage()}, in order. Each entry
     * is `[Page, width, height]` so the deferred decorator pass has
     * page geometry without re-parsing mediaBox entries.
     *
     * @var list<array{Page, float, float}>
     */
    private array $pages = [];

    /**
     * Per-page hooks (header / footer / watermark). Applied in a
     * deferred pass right before {@see toBytes()}, {@see save()}, or
     * {@see writeTo()} produces output.
     */
    private PageDecorator $decorator;

    /** Guard so the deferred decorator pass only runs once per document. */
    private bool $decoratorsApplied = false;

    /** Whether auto-outline is active — set via {@see enableOutline()}. */
    private bool $outlineEnabled = false;

    /** Lazily-created outline root once auto-outline is enabled. */
    private ?\Phpdftk\Pdf\Core\Document\Outline $outlineRoot = null;

    /**
     * The most recent `OutlineItem` seen at each heading level, used
     * both to find the parent for a deeper-level heading and to chain
     * siblings at the same level.
     *
     * @var array<int, \Phpdftk\Pdf\Core\Document\OutlineItem>
     */
    private array $outlineLastAtLevel = [];

    /** Running count of all outline entries — written to Outline::$count. */
    private int $outlineCount = 0;

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

    /** Number of columns the body region is split into (default 1). */
    private int $columnCount = 1;

    /** Gap between columns in points. */
    private float $columnGutter = 12.0;

    /** Zero-based index of the column the cursor is currently in. */
    private int $currentColumnIndex = 0;

    /** Remember current fill color so we only emit it when it changes. */
    private ?string $lastFillColor = null;

    /** @var array<string, Font> family+variant key => registered font handle */
    private array $fontResourceCache = [];

    /** @var array<string, AfmData> family+variant key => AFM metrics for width measurement */
    private array $fontMetricsCache = [];

    /**
     * Optional `phpdftk/resource-loader` for resolving `http(s)://`
     * URLs in author content — currently consumed by
     * `Phpdftk\SvgToPdf\SvgRenderer::addToPdf` for `<image>` hrefs.
     * Wire via {@see withResourceLoader()} (or pass to the
     * constructor). When null the legacy "drop network hrefs
     * silently" behaviour is preserved per the SVG 2 §12.6 / image-
     * loading no-image outcome.
     */
    private ?\Phpdftk\ResourceLoader\ResourceLoader $resourceLoader = null;

    public function __construct(
        PageSize $pageSize = PageSize::Letter,
        ?Theme $theme = null,
        bool $compressStreams = true,
        ?\Phpdftk\ResourceLoader\ResourceLoader $resourceLoader = null,
    ) {
        $this->doc = new PdfDoc($compressStreams);
        $this->writer = $this->doc->writer();
        $this->pageSize = $pageSize;
        $this->theme = $theme ?? new Theme();
        $this->font = $this->theme->family;
        $this->fontSize = $this->theme->fontSize;
        $this->decorator = new PageDecorator();
        $this->resourceLoader = $resourceLoader;
    }

    /**
     * Attach a {@see \Phpdftk\ResourceLoader\ResourceLoader} for
     * network resource resolution. Mutating fluent setter — returns
     * `$this` so chains like
     *
     *   (new Pdf())->withResourceLoader($loader)->setFont('Inter', 12)
     *
     * work the same way as the existing setFont / setTheme family.
     */
    public function withResourceLoader(?\Phpdftk\ResourceLoader\ResourceLoader $loader): self
    {
        $this->resourceLoader = $loader;
        return $this;
    }

    /**
     * The currently-attached ResourceLoader, or null. Consumed by
     * downstream translators (e.g. `SvgRenderer::addToPdf` picks
     * this up when no explicit loader is passed) so a single
     * `$pdf->withResourceLoader($loader)` call wires the whole
     * document.
     */
    public function resourceLoader(): ?\Phpdftk\ResourceLoader\ResourceLoader
    {
        return $this->resourceLoader;
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

    // -----------------------------------------------------------------------
    // Document metadata (forwarders to PdfDoc)
    // -----------------------------------------------------------------------

    public function setTitle(string $title): self
    {
        $this->doc->setTitle($title);
        return $this;
    }

    public function setAuthor(string $author): self
    {
        $this->doc->setAuthor($author);
        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->doc->setSubject($subject);
        return $this;
    }

    public function setKeywords(string $keywords): self
    {
        $this->doc->setKeywords($keywords);
        return $this;
    }

    public function setCreator(string $creator): self
    {
        $this->doc->setCreator($creator);
        return $this;
    }

    /**
     * Set the document's viewer preferences (display options the
     * reader honours when opening the file). Forwards to
     * {@see PdfDoc::setViewerPreferences()}.
     */
    public function setViewerPreferences(
        \Phpdftk\Pdf\Core\Document\ViewerPreferences|\Closure $prefs,
    ): self {
        $this->doc->setViewerPreferences($prefs);
        return $this;
    }

    /**
     * Attach a file from disk to the document. Forwards to
     * {@see PdfDoc::attachFile()}.
     */
    public function attachFile(
        string $path,
        ?string $description = null,
        ?string $mimeType = null,
        ?string $relationship = null,
    ): self {
        $this->doc->attachFile($path, $description, $mimeType, $relationship);
        return $this;
    }

    /**
     * Set the document's open action — executed by the viewer when
     * the file is loaded. Forwards to {@see PdfDoc::setOpenAction()}.
     */
    public function setOpenAction(\Phpdftk\Pdf\Core\Action\Action $action): self
    {
        $this->doc->setOpenAction($action);
        return $this;
    }

    // -----------------------------------------------------------------------
    // Per-page render hooks (header / footer / watermark)
    // -----------------------------------------------------------------------

    /**
     * Register a closure invoked on every page after flow content is
     * placed. The closure receives a {@see PageContext} with the
     * current page number, total page count, and a {@see Page} handle
     * for drawing into the header region.
     *
     * The body region shrinks by `Theme::headerHeight` to leave room
     * for the header; configure that on your theme if you want a
     * non-zero reserved area.
     */
    public function setHeader(\Closure $header): self
    {
        $this->decorator = $this->decorator->withHeader($header);
        return $this;
    }

    /**
     * Register a closure invoked on every page after flow content is
     * placed, to draw the footer region. See {@see setHeader()}.
     */
    public function setFooter(\Closure $footer): self
    {
        $this->decorator = $this->decorator->withFooter($footer);
        return $this;
    }

    /**
     * Enable automatic outline (bookmarks) generation from `addHeading()`
     * calls. Each heading registers an `OutlineItem` with a destination
     * pointing at the current page + y; heading level controls the
     * parent → child nesting (level 2 nests under the previous level 1,
     * etc.).
     *
     * No-op when called before any heading exists. Disable later with
     * `enableOutline(false)` to stop recording further headings.
     */
    public function enableOutline(bool $enabled = true): self
    {
        $this->outlineEnabled = $enabled;
        if ($enabled && $this->outlineRoot === null) {
            $this->outlineRoot = new \Phpdftk\Pdf\Core\Document\Outline();
            $this->doc->setOutline($this->outlineRoot);
        }
        return $this;
    }

    /**
     * Show page numbers in the footer of every page. Sugar over
     * {@see setFooter()} that uses `PageContext::$totalPages` from the
     * deferred decorator pass, so `'Page %d of %d'`-style formats work
     * without manual two-pass logic.
     *
     * Set `Theme::footerHeight` to reserve space so the page number
     * doesn't overlap body content.
     */
    public function showPageNumbers(
        string $format = 'Page %d of %d',
        Alignment $align = Alignment::Center,
        float $fontSize = 9.0,
    ): self {
        $this->setFooter(function (PageContext $ctx) use ($format, $align, $fontSize): void {
            $text = sprintf($format, $ctx->pageNumber, $ctx->totalPages);

            $postScriptName = $this->resolveFontName($this->font, $this->bold, $this->italic);
            $font = $this->ensureFontResource($postScriptName);
            $metrics = $this->getMetrics($postScriptName);
            $encoded = $font->getTextEncoder()?->encode($text) ?? $text;
            $width = TextLayout::measure($encoded, $metrics, $fontSize);

            $contentWidth = $ctx->pageWidth - 2.0 * $ctx->theme->margin;
            $x = $ctx->theme->margin + match ($align) {
                Alignment::Left   => 0.0,
                Alignment::Center => ($contentWidth - $width) / 2.0,
                Alignment::Right  => $contentWidth - $width,
            };
            // Anchor inside the bottom margin (or the footer reserve if set).
            $y = $ctx->theme->footerHeight > 0
                ? $ctx->theme->margin + $ctx->theme->footerHeight / 2.0 - $fontSize / 2.0
                : $ctx->theme->margin / 2.0;

            $ctx->page->contentStream()
                ->beginText()
                ->setFont($font->getResourceName(), $fontSize)
                ->moveTextPosition($x, $y)
                ->showText($encoded)
                ->endText();
        });
        return $this;
    }

    /**
     * Set a watermark drawn on every page. A string is rendered as
     * centered diagonal grey text; a closure is invoked per page with
     * a {@see PageContext} for full control.
     */
    public function setWatermark(
        string|\Closure $textOrFn,
        float $opacity = 0.2,
        float $angleDeg = 45.0,
    ): self {
        if ($textOrFn instanceof \Closure) {
            $closure = $textOrFn;
        } else {
            $text = $textOrFn;
            $closure = function (PageContext $ctx) use ($text, $opacity, $angleDeg): void {
                $this->drawDefaultWatermark($ctx, $text, $opacity, $angleDeg);
            };
        }
        $this->decorator = $this->decorator->withWatermark($closure);
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
     * Escape hatch to Level 2: returns the underlying {@see PdfDoc} so
     * callers can use friendly wrappers (annotations, form fields,
     * file attachments, viewer prefs, etc.) without leaving the
     * flow-builder context.
     */
    public function doc(): PdfDoc
    {
        return $this->doc;
    }

    /**
     * Escape hatch to Level 1: returns the underlying {@see PdfWriter}
     * for byte/resource control (custom fonts, encryption, signing,
     * conformance). Equivalent to `doc()->writer()`.
     */
    public function writer(): PdfWriter
    {
        return $this->writer;
    }

    /**
     * Codepoints that were substituted with `?` because the active font's
     * encoding could not represent them. Useful after building a document
     * to confirm that no unintended replacement characters slipped in.
     *
     * @return list<string>
     */
    public function getEncodingWarnings(): array
    {
        return $this->writer->getEncodingWarnings();
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
        $this->cursorY = $size->height() - $this->theme->margin - $this->theme->headerHeight;
        $this->lastFillColor = null;
        $this->currentColumnIndex = 0;
        $this->pages[] = [$this->currentPage, $size->width(), $size->height()];
        return $this;
    }

    /** Force a page break. Equivalent to `addPage()` with the current size. */
    public function newPage(): self
    {
        return $this->addPage();
    }

    /**
     * Render an HTML + CSS document into the PDF as a sequence of fresh
     * pages, then invalidate the cursor so subsequent `addText` /
     * `addHeading` / etc. start on a new page.
     *
     * The HTML renderer ships in `phpdftk/html-to-pdf` and depends on
     * `phpdftk/pdf-writer` — so to avoid a circular composer dependency,
     * this method only works when that package is installed. The class
     * lookup happens lazily on first call; absent the package, the
     * method throws a helpful `RuntimeException`.
     *
     * Note: this *does not* try to fit content under the current cursor.
     * For inline HTML rendering at the cursor position, drop down to
     * `Phpdftk\HtmlToPdf\Renderer` directly and pass `Pdf::writer()` to
     * `renderInto()`.
     */
    public function addHtml(
        string $html,
        ?string $css = null,
        ?\Phpdftk\FontParser\OpenTypeData $font = null,
    ): self {
        $rendererClass = '\\Phpdftk\\HtmlToPdf\\Renderer';
        $optionsClass = '\\Phpdftk\\HtmlToPdf\\RendererOptions';
        if (!class_exists($rendererClass)) {
            throw new \RuntimeException(
                'Pdf::addHtml() requires the phpdftk/html-to-pdf package — '
                . 'install it via Composer or use `composer require phpdftk/pdf`.',
            );
        }
        $options = (new $optionsClass())
            ->withPageSize($this->pageSize->width(), $this->pageSize->height());
        if ($font !== null) {
            $options = $options->withDefaultFont($font);
        }
        $renderer = new $rendererClass($options);
        $renderer->renderInto($this->writer, $html, $css);
        // Subsequent flow-API calls (`addText` etc.) trigger a fresh page
        // via `ensurePage`, so we don't try to share the post-HTML page
        // with cursor-driven content (the HTML renderer manages its own
        // pages, and the cursor model assumes a margin-based layout).
        $this->currentPage = null;
        $this->currentStream = null;
        return $this;
    }

    /**
     * Split the body region into `$count` columns separated by
     * `$gutter` points. Flow content (text, lists, tables, callouts)
     * fills the current column first, then advances to the next
     * column when overflow occurs; a page break only happens after
     * the last column on a page overflows.
     *
     * Set `$count = 1` to return to single-column flow. Calling this
     * mid-document is allowed but only affects content added *after*
     * the call; already-rendered content stays where it was placed.
     */
    public function setColumns(int $count, float $gutter = 12.0): self
    {
        if ($count < 1) {
            throw new \InvalidArgumentException("Column count must be >= 1, got {$count}.");
        }
        if ($gutter < 0) {
            throw new \InvalidArgumentException("Column gutter must be >= 0, got {$gutter}.");
        }
        $this->columnCount = $count;
        $this->columnGutter = $gutter;
        $this->currentColumnIndex = 0;
        return $this;
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
        $link   = $style->link;
        $underline = $style->underline;
        $strikethrough = $style->strikethrough;

        $postScriptName = $this->resolveFontName($family, $bold, $italic);
        $metrics = $this->getMetrics($postScriptName);
        $fontHandle = $this->ensureFontResource($postScriptName);

        $lineHeight = $size * $this->theme->lineHeight;
        $columnWidth = $this->contentWidth();

        // Encode UTF-8 to the font's byte encoding up front so wrapText /
        // measureText (both of which index by byte into a WinAnsi width
        // table) operate on the correct bytes. With pre-encoded text we
        // hand showText the string-form setFont so it doesn't double-encode.
        $encoded = $fontHandle->getTextEncoder()?->encode($text) ?? $text;
        $lines = $this->wrapText($encoded, $metrics, $size, $columnWidth);

        $this->applyFillColor($color);

        foreach ($lines as $line) {
            // Need room for one more line? If not, advance — to the next
            // column when one is available, otherwise to a new page.
            if ($this->advanceOnOverflow($lineHeight)) {
                $this->applyFillColor($color);
            }

            // Empty lines (explicit paragraph breaks within the input
            // string) still consume one line of vertical space.
            if ($line === '') {
                $this->cursorY -= $lineHeight;
                continue;
            }

            $lineWidth = $this->measureText($line, $metrics, $size);
            $x = $this->columnLeftX() + match ($align) {
                Alignment::Left   => 0.0,
                Alignment::Center => ($columnWidth - $lineWidth) / 2.0,
                Alignment::Right  => $columnWidth - $lineWidth,
            };
            // PDF text origin is at the baseline, so we drop an additional
            // font size to land the top of the glyph at the cursor.
            $baselineY = $this->cursorY - $size;

            $this->currentStream
                ->beginText()
                ->setFont($fontHandle->getResourceName(), $size)
                ->moveTextPosition($x, $baselineY)
                ->showText($line)
                ->endText();

            // Underline / strikethrough decoration lines, drawn in the
            // same fill color as the text. Conventions: underline sits
            // ~12% of the font size below the baseline; strikethrough
            // sits ~28% above (through x-height).
            if ($underline || $strikethrough) {
                $strokeW = max(0.5, $size * 0.05);
                $this->currentStream
                    ->saveGraphicsState()
                    ->setStrokeColorRGB($color[0], $color[1], $color[2])
                    ->setLineWidth($strokeW);
                if ($underline) {
                    $uy = $baselineY - $size * 0.12;
                    $this->currentStream
                        ->moveTo($x, $uy)
                        ->lineTo($x + $lineWidth, $uy)
                        ->stroke();
                }
                if ($strikethrough) {
                    $sy = $baselineY + $size * 0.28;
                    $this->currentStream
                        ->moveTo($x, $sy)
                        ->lineTo($x + $lineWidth, $sy)
                        ->stroke();
                }
                $this->currentStream->restoreGraphicsState();
                $this->lastFillColor = null;
            }

            // For a linked paragraph, register one link annotation per
            // rendered line on the current page. The clickable area
            // hugs the text (slightly taller than the font to give some
            // forgiveness around descenders).
            if ($link !== null) {
                $rect = new \Phpdftk\Geometry\Rectangle(
                    $x,
                    $baselineY - $size * 0.2,
                    $lineWidth,
                    $size * 1.2,
                );
                $this->doc->addLink($this->currentPage, $rect, $link);
            }

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
        // Capture the destination Y *before* the heading text is drawn:
        // viewers scroll to land this y near the top of the viewport.
        $this->recordOutlineEntry($text, $level, $this->cursorY);
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
        if ($this->cursorY < $this->bottomMargin()) {
            // Spacer that overflows behaves like a hard advance to the
            // next column / page.
            $this->advanceOnOverflow(0.0);
        }
        return $this;
    }

    /**
     * Add a horizontal rule spanning the current content column.
     */
    public function addRule(float $lineWidth = 0.5): self
    {
        $this->ensurePage();
        $y = $this->cursorY - $lineWidth;
        if ($y < $this->bottomMargin()) {
            $this->advanceOnOverflow($lineWidth);
            $y = $this->cursorY - $lineWidth;
        }

        $this->currentStream
            ->saveGraphicsState()
            ->setLineWidth($lineWidth)
            ->setStrokeColorRGB(0, 0, 0)
            ->moveTo($this->columnLeftX(), $y)
            ->lineTo($this->columnLeftX() + $this->contentWidth(), $y)
            ->stroke()
            ->restoreGraphicsState();

        $this->cursorY -= $lineWidth * 2 + $this->theme->paragraphSpacing;
        return $this;
    }

    /**
     * Add a callout block at the current cursor — a coloured panel
     * with a left bar, optional title row, and wrapped body text. The
     * built-in {@see CalloutType} cases (`Note`, `Tip`, `Warning`,
     * `Danger`) carry default bar / background colours; override any of
     * them via {@see CalloutStyle}.
     *
     * In v1, callouts render on a single page — they auto-advance to
     * a new page if the current one can't fit them, but they don't
     * split mid-content. Callouts taller than a single page throw.
     */
    public function addCallout(
        string $text,
        CalloutType $type = CalloutType::Note,
        ?CalloutStyle $style = null,
    ): self {
        $this->ensurePage();
        $style ??= new CalloutStyle();

        $bodyPSN = $this->resolveFontName($this->font, $this->bold, $this->italic);
        $bodyFont = $this->ensureFontResource($bodyPSN);
        $bodyMetrics = $this->getMetrics($bodyPSN);

        $titlePSN = $this->resolveFontName($this->font, bold: true, italic: false);
        $titleFont = $this->ensureFontResource($titlePSN);

        $size = $this->fontSize;
        $lineHeight = $size * $this->theme->lineHeight;
        $padding = $style->padding;
        $barWidth = $style->barWidth;

        $textX = $this->columnLeftX() + $barWidth + $padding;
        $textWidth = max(0.0, $this->contentWidth() - $barWidth - 2.0 * $padding);

        $encoded = $bodyFont->getTextEncoder()?->encode($text) ?? $text;
        $bodyLines = $this->wrapText($encoded, $bodyMetrics, $size, $textWidth);
        $bodyHeight = count($bodyLines) * $lineHeight;

        $titleHeight = 0.0;
        $titleLabel = null;
        if ($style->showLabel) {
            $titleLabel = $style->resolveLabel($type);
            $titleHeight = $lineHeight;
        }

        $totalHeight = 2.0 * $padding + $titleHeight + $bodyHeight;
        $availableHeight = $this->pageSize->height() - 2.0 * $this->theme->margin
            - $this->theme->headerHeight - $this->theme->footerHeight;
        if ($totalHeight > $availableHeight) {
            throw new \RuntimeException(
                'Callout content is too tall to fit on a single page '
                . "({$totalHeight} > {$availableHeight}); v1 does not split callouts across pages.",
            );
        }

        $this->advanceOnOverflow($totalHeight);

        $topY = $this->cursorY;
        $bottomY = $topY - $totalHeight;
        $totalWidth = $this->contentWidth();
        $left = $this->columnLeftX();

        [$br, $bg, $bb] = $style->resolveBarColor($type);
        [$bgR, $bgG, $bgB] = $style->resolveBgColor($type);
        $textColor = $style->textColor ?? $this->theme->color;

        $cs = $this->currentStream;
        $cs->saveGraphicsState();

        // Background tint covering the whole callout rectangle.
        $cs->setFillColorRGB($bgR, $bgG, $bgB)
            ->rectangle($left, $bottomY, $totalWidth, $totalHeight)
            ->fill();

        // Solid left-edge bar in the type's accent colour.
        $cs->setFillColorRGB($br, $bg, $bb)
            ->rectangle($left, $bottomY, $barWidth, $totalHeight)
            ->fill();

        // Body / title text colour.
        $cs->setFillColorRGB($textColor[0], $textColor[1], $textColor[2]);
        $y = $topY - $padding;

        if ($titleLabel !== null) {
            $encodedTitle = $titleFont->getTextEncoder()?->encode($titleLabel) ?? $titleLabel;
            $titleBaseline = $y - $size;
            $cs->beginText()
                ->setFont($titleFont->getResourceName(), $size)
                ->moveTextPosition($textX, $titleBaseline)
                ->showText($encodedTitle)
                ->endText();
            $y -= $lineHeight;
        }

        foreach ($bodyLines as $line) {
            if ($line === '') {
                $y -= $lineHeight;
                continue;
            }
            $baseline = $y - $size;
            $cs->beginText()
                ->setFont($bodyFont->getResourceName(), $size)
                ->moveTextPosition($textX, $baseline)
                ->showText($line)
                ->endText();
            $y -= $lineHeight;
        }

        $cs->restoreGraphicsState();

        $this->cursorY = $bottomY - $this->theme->paragraphSpacing;
        $this->lastFillColor = null;
        return $this;
    }

    /**
     * Add a blockquote at the current cursor: indented text in italic
     * with a coloured vertical bar down the left side. The body
     * paginates like `addText`; the bar is drawn once per page the
     * quote occupies.
     *
     * Override font / colour / alignment via `TextStyle`. If the style
     * doesn't specify italic, italic is applied by default — that's
     * the visual signature of a blockquote.
     */
    public function addQuote(string $text, ?TextStyle $style = null): self
    {
        $this->ensurePage();
        $style ??= new TextStyle();

        $family = $style->family ?? $this->font;
        $size   = $style->size   ?? $this->fontSize;
        $bold   = $style->bold   ?? $this->bold;
        $italic = $style->italic ?? true;
        $color  = $style->color  ?? $this->theme->color;
        $align  = $style->alignment ?? Alignment::Left;

        $postScriptName = $this->resolveFontName($family, $bold, $italic);
        $metrics = $this->getMetrics($postScriptName);
        $fontHandle = $this->ensureFontResource($postScriptName);

        $lineHeight = $size * $this->theme->lineHeight;
        $indent = $this->theme->quoteIndent;
        $textWidth = max(0.0, $this->contentWidth() - $indent);

        $encoded = $fontHandle->getTextEncoder()?->encode($text) ?? $text;
        $lines = $this->wrapText($encoded, $metrics, $size, $textWidth);

        $this->applyFillColor($color);

        // Track per-segment bar runs. A segment ends when the cursor
        // transitions to another column or another page mid-quote.
        $segments = [];
        $segmentStartY = $this->cursorY;
        $segmentPage = $this->currentPage;
        $segmentLeft = $this->columnLeftX();

        foreach ($lines as $line) {
            if ($this->cursorY - $lineHeight < $this->bottomMargin()) {
                $segments[] = [$segmentPage, $segmentLeft, $segmentStartY, $this->cursorY];
                $this->advanceOnOverflow($lineHeight);
                $this->applyFillColor($color);
                $segmentStartY = $this->cursorY;
                $segmentPage = $this->currentPage;
                $segmentLeft = $this->columnLeftX();
            }

            if ($line === '') {
                $this->cursorY -= $lineHeight;
                continue;
            }

            $textX = $this->columnLeftX() + $indent;
            $lineWidth = $this->measureText($line, $metrics, $size);
            $lineX = $textX + match ($align) {
                Alignment::Left   => 0.0,
                Alignment::Center => ($textWidth - $lineWidth) / 2.0,
                Alignment::Right  => $textWidth - $lineWidth,
            };
            $baselineY = $this->cursorY - $size;

            $this->currentStream
                ->beginText()
                ->setFont($fontHandle->getResourceName(), $size)
                ->moveTextPosition($lineX, $baselineY)
                ->showText($line)
                ->endText();

            $this->cursorY -= $lineHeight;
        }
        $segments[] = [$segmentPage, $segmentLeft, $segmentStartY, $this->cursorY];

        [$br, $bg, $bb] = $this->theme->quoteBarColor;
        foreach ($segments as [$page, $left, $top, $bottom]) {
            $barX = $left + $this->theme->quoteBarWidth / 2.0;
            $cs = $page->contentStream();
            $cs->saveGraphicsState()
                ->setStrokeColorRGB($br, $bg, $bb)
                ->setLineWidth($this->theme->quoteBarWidth)
                ->moveTo($barX, $top - 2.0)
                ->lineTo($barX, $bottom + 2.0)
                ->stroke()
                ->restoreGraphicsState();
        }

        $this->cursorY -= $this->theme->paragraphSpacing;
        $this->lastFillColor = null;
        return $this;
    }

    /**
     * Add a bullet list at the current cursor. Items are plain strings
     * or nested {@see ListBlock}s; nested blocks indent one level deeper.
     *
     * Long items wrap at the available column width; lists auto-paginate
     * item-by-item.
     *
     * @param list<string|ListBlock> $items
     */
    public function addList(array $items, ?ListStyle $style = null): self
    {
        return $this->addListInternal(new ListBlock($items, numbered: false), $style);
    }

    /**
     * Add a numbered list (`1. … 2. …`). Numbering restarts at each
     * nested level.
     *
     * @param list<string|ListBlock> $items
     */
    public function addNumberedList(array $items, ?ListStyle $style = null): self
    {
        return $this->addListInternal(new ListBlock($items, numbered: true), $style);
    }

    private function addListInternal(ListBlock $block, ?ListStyle $style): self
    {
        if ($block->items === []) {
            return $this;
        }
        $this->ensurePage();
        $style ??= new ListStyle();

        $postScriptName = $this->resolveFontName($this->font, $this->bold, $this->italic);
        $font = $this->ensureFontResource($postScriptName);
        $metrics = $this->getMetrics($postScriptName);
        $renderer = new ListRenderer();
        $maxWidth = $this->contentWidth();

        $itemNumber = 1;
        foreach ($block->items as $item) {
            $h = $renderer->measureItem(
                $item,
                $maxWidth,
                $font,
                $metrics,
                $this->fontSize,
                $this->theme->lineHeight,
                $style,
            );
            $this->advanceOnOverflow($h);
            $consumed = $renderer->drawItem(
                $this->currentStream,
                $this->columnLeftX(),
                $this->cursorY,
                $item,
                $maxWidth,
                $font,
                $metrics,
                $this->fontSize,
                $this->theme->lineHeight,
                $style,
                $block->numbered ? $itemNumber : null,
            );
            $this->cursorY -= $consumed;
            $itemNumber++;
        }

        $this->cursorY -= $this->theme->paragraphSpacing;
        $this->lastFillColor = null;
        return $this;
    }

    /**
     * Add a tabular block of content. The table is rendered at the
     * current cursor, with rows auto-paginating across page breaks.
     * When `$headerRow` is provided, it repeats at the top of every
     * page the table occupies.
     *
     * `$columnWidths` is a list of absolute point widths; pass `null`
     * to split the content column evenly across the inferred column
     * count. The widths must sum to at most the content column width.
     *
     * @param list<list<string>>   $rows
     * @param list<float>|null     $columnWidths
     * @param list<string>|null    $headerRow
     */
    public function addTable(
        array $rows,
        ?array $columnWidths = null,
        ?array $headerRow = null,
        ?TableStyle $style = null,
    ): self {
        $this->ensurePage();
        $style ??= new TableStyle();

        $colCount = count($columnWidths ?? $headerRow ?? $rows[0] ?? []);
        if ($colCount === 0) {
            return $this; // empty table — no-op
        }
        $columnWidths ??= $this->equalColumns($colCount);

        $ctx = $this->tableContext($style);
        $renderer = new TableRenderer();

        $drawHeader = function () use ($renderer, $headerRow, $columnWidths, $ctx): void {
            if ($headerRow === null) {
                return;
            }
            $hh = $renderer->rowHeight($headerRow, $columnWidths, $ctx, isHeader: true);
            $this->advanceOnOverflow($hh);
            $renderer->drawRow(
                $this->currentStream,
                $this->columnLeftX(),
                $this->cursorY,
                $headerRow,
                $columnWidths,
                $ctx,
                isHeader: true,
            );
            $this->cursorY -= $hh;
        };

        $drawHeader();

        foreach ($rows as $row) {
            $h = $renderer->rowHeight($row, $columnWidths, $ctx, isHeader: false);
            if ($this->advanceOnOverflow($h)) {
                $drawHeader();
            }
            $renderer->drawRow(
                $this->currentStream,
                $this->columnLeftX(),
                $this->cursorY,
                $row,
                $columnWidths,
                $ctx,
                isHeader: false,
            );
            $this->cursorY -= $h;
        }

        $this->cursorY -= $this->theme->paragraphSpacing;
        $this->lastFillColor = null; // table reset graphics state
        return $this;
    }

    /**
     * Render a barcode in the flow at the current cursor. Width comes
     * from the rendered bitmap (modules × moduleWidth + quiet zones);
     * `align` controls horizontal placement within the column.
     *
     * For multi-document reuse, prefer
     * {@see PdfDoc::createBarcode()} + `Writer\Page::drawTemplate()`.
     */
    public function addBarcode(
        \Phpdftk\Barcode\Symbology $symbology,
        string $data,
        ?\Phpdftk\Barcode\BarcodeOptions $options = null,
        Alignment $align = Alignment::Left,
    ): self {
        $this->ensurePage();
        $options ??= new \Phpdftk\Barcode\BarcodeOptions();
        $bitmap = \Phpdftk\Barcode\BarcodeRenderer::render($symbology, $data, $options);

        $w = $bitmap->totalWidth();
        $h = $bitmap->totalHeight();

        $this->advanceOnOverflow($h);
        $columnWidth = $this->contentWidth();
        $x = $this->columnLeftX() + match ($align) {
            Alignment::Left   => 0.0,
            Alignment::Center => ($columnWidth - $w) / 2.0,
            Alignment::Right  => $columnWidth - $w,
        };
        $y = $this->cursorY - $h;

        $cs = $this->currentStream;
        $cs->saveGraphicsState();
        $cs->concatMatrix(1.0, 0.0, 0.0, 1.0, $x, $y);
        BarcodeRendering::renderInto($cs, $bitmap);
        $cs->restoreGraphicsState();

        $this->cursorY -= $h + $this->theme->paragraphSpacing;
        $this->lastFillColor = null;
        return $this;
    }

    /**
     * Drop a fixed-size block of caller-painted content at the current
     * cursor. The `$painter` closure runs at the position
     * `addImage` would have placed a same-sized image — same alignment
     * options, same overflow handling — but the caller is responsible
     * for putting bytes on the page. This is the integration hook
     * adapters (svg-to-pdf, future foreign-content renderers) use to
     * plug their own painter into the cursor / pagination flow without
     * Pdf itself growing a dependency on them.
     *
     * The closure receives `(Page $page, float $x, float $y, float $width, float $height)`.
     * `(x, y)` is the bottom-left of the destination rectangle in PDF
     * user space, matching the convention `Page::drawImage` and the
     * other low-level drawing methods already use.
     *
     * @param \Closure(Page, float, float, float, float): void $painter
     */
    public function addBlock(
        float $width,
        float $height,
        Alignment $align,
        \Closure $painter,
    ): self {
        $this->ensurePage();
        $this->advanceOnOverflow($height);

        $columnWidth = $this->contentWidth();
        $x = $this->columnLeftX() + match ($align) {
            Alignment::Left   => 0.0,
            Alignment::Center => ($columnWidth - $width) / 2.0,
            Alignment::Right  => $columnWidth - $width,
        };
        $y = $this->cursorY - $height;

        if ($this->currentPage === null) {
            // Defensive: ensurePage() should have set this; this is
            // just a static-analysis-friendly guard.
            return $this;
        }
        $painter($this->currentPage, $x, $y, $width, $height);

        $this->cursorY -= $height + $this->theme->paragraphSpacing;
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
        } elseif ($width === null) {
            // Reached only when $height is non-null (both-null and
            // width-only branches above are exhausted).
            $h = $height;
            $w = $naturalW * ($height / $naturalH);
        } else {
            $w = (float) $width;
            $h = (float) $height;
        }

        // Advance if the image won't fit in the remaining column / page.
        $this->advanceOnOverflow($h);

        $columnWidth = $this->contentWidth();
        $x = $this->columnLeftX() + match ($align) {
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
        $this->applyDecorators();
        $this->writer->save($path);
    }

    public function toBytes(): string
    {
        $this->applyDecorators();
        return $this->writer->toBytes();
    }

    /** @param resource $stream */
    public function writeTo($stream): int
    {
        $this->applyDecorators();
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

    /**
     * Register an OutlineItem for the current heading and wire it into
     * the hierarchy. Called from {@see addHeading()} when auto-outline
     * is enabled.
     */
    private function recordOutlineEntry(string $title, int $level, float $destY): void
    {
        if (!$this->outlineEnabled || $this->outlineRoot === null) {
            return;
        }
        $this->ensurePage();

        $item = new \Phpdftk\Pdf\Core\Document\OutlineItem($title);
        $pageRef = new \Phpdftk\Pdf\Core\PdfReference($this->currentPage->corePage()->objectNumber);
        $item->dest = new \Phpdftk\Pdf\Core\PdfArray([
            $pageRef,
            new \Phpdftk\Pdf\Core\PdfName('XYZ'),
            new \Phpdftk\Pdf\Core\PdfNumber(0),
            new \Phpdftk\Pdf\Core\PdfNumber($destY),
            new \Phpdftk\Pdf\Core\PdfNumber(0),
        ]);
        $ref = $this->doc->addOutlineItem($item);

        // Locate parent: most recent item at a shallower level.
        $parent = null;
        for ($l = $level - 1; $l >= 1; $l--) {
            if (isset($this->outlineLastAtLevel[$l])) {
                $parent = $this->outlineLastAtLevel[$l];
                break;
            }
        }
        $prevSibling = $this->outlineLastAtLevel[$level] ?? null;

        $parentRef = $parent !== null
            ? new \Phpdftk\Pdf\Core\PdfReference($parent->objectNumber)
            : new \Phpdftk\Pdf\Core\PdfReference($this->outlineRoot->objectNumber);
        $item->parent = $parentRef;

        if ($prevSibling !== null) {
            $prevSibling->next = $ref;
            $item->prev = new \Phpdftk\Pdf\Core\PdfReference($prevSibling->objectNumber);
        } else {
            // First child of its parent.
            if ($parent !== null) {
                $parent->first = $ref;
            } else {
                $this->outlineRoot->first = $ref;
            }
        }

        // The parent's last child is always the just-registered item.
        if ($parent !== null) {
            $parent->last = $ref;
        } else {
            $this->outlineRoot->last = $ref;
        }

        $this->outlineCount++;
        $this->outlineRoot->count = $this->outlineCount;

        // The new item becomes the latest at its level and breaks any
        // deeper-level sibling chains (they restart under the new item).
        $this->outlineLastAtLevel[$level] = $item;
        foreach (array_keys($this->outlineLastAtLevel) as $existing) {
            if ($existing > $level) {
                unset($this->outlineLastAtLevel[$existing]);
            }
        }
    }

    /**
     * Run the per-page render hooks once, after all flow content has
     * been placed but before bytes are emitted. Total-page count is
     * resolvable here, which is why it's deferred.
     */
    private function applyDecorators(): void
    {
        if ($this->decoratorsApplied || $this->decorator->isEmpty()) {
            $this->decoratorsApplied = true;
            return;
        }
        $this->decoratorsApplied = true;

        $total = count($this->pages);
        foreach ($this->pages as $i => [$page, $width, $height]) {
            $ctx = new PageContext(
                pageNumber: $i + 1,
                totalPages: $total,
                page: $page,
                pageWidth: $width,
                pageHeight: $height,
                theme: $this->theme,
            );

            if ($this->decorator->watermark !== null) {
                ($this->decorator->watermark)($ctx);
            }
            if ($this->decorator->header !== null) {
                ($this->decorator->header)($ctx);
            }
            if ($this->decorator->footer !== null) {
                ($this->decorator->footer)($ctx);
            }
        }
    }

    /**
     * Built-in watermark renderer used when {@see setWatermark()}
     * receives a string. Renders large grey diagonal text centered on
     * the page; the `$opacity` parameter is approximated by lightening
     * the fill color since opacity proper requires an ExtGState (Phase
     * 4.4's territory).
     */
    private function drawDefaultWatermark(
        PageContext $ctx,
        string $text,
        float $opacity,
        float $angleDeg,
    ): void {
        $postScriptName = 'Helvetica-Bold';
        $fontHandle = $this->ensureFontResource($postScriptName);
        $metrics = $this->getMetrics($postScriptName);
        $encoded = $fontHandle->getTextEncoder()?->encode($text) ?? $text;

        $fontSize = 72.0;
        $textWidth = $this->measureText($encoded, $metrics, $fontSize);

        $cx = $ctx->pageWidth / 2.0;
        $cy = $ctx->pageHeight / 2.0;
        $angleRad = $angleDeg * M_PI / 180.0;
        $cos = cos($angleRad);
        $sin = sin($angleRad);

        $gray = max(0.0, min(1.0, 1.0 - $opacity));

        $ctx->page->contentStream()
            ->saveGraphicsState()
            ->setFillColorRGB($gray, $gray, $gray)
            ->concatMatrix($cos, $sin, -$sin, $cos, $cx, $cy)
            ->beginText()
            ->setFont($fontHandle->getResourceName(), $fontSize)
            ->moveTextPosition(-$textWidth / 2.0, -$fontSize / 3.0)
            ->showText($encoded)
            ->endText()
            ->restoreGraphicsState();
    }

    /**
     * Width of one column of body content. When `columnCount = 1`
     * this is the full content area between left + right margins;
     * with multiple columns each column gets an equal share of the
     * remaining width after gutters.
     */
    private function contentWidth(): float
    {
        $full = $this->totalContentWidth();
        if ($this->columnCount <= 1) {
            return $full;
        }
        $gutters = $this->columnGutter * ($this->columnCount - 1);
        return max(0.0, ($full - $gutters) / $this->columnCount);
    }

    /** Full body width spanning all columns (e.g. for full-bleed headers). */
    private function totalContentWidth(): float
    {
        return $this->pageSize->width() - (2 * $this->theme->margin);
    }

    /**
     * Left X coordinate of the current column. With a single column
     * this is just the page's left margin.
     */
    private function columnLeftX(): float
    {
        return $this->theme->margin
            + $this->currentColumnIndex * ($this->contentWidth() + $this->columnGutter);
    }

    /**
     * Y coordinate the cursor returns to at the top of any column
     * (same for every column on a page — just below the header reserve).
     */
    private function topOfColumn(): float
    {
        return $this->pageSize->height() - $this->theme->margin - $this->theme->headerHeight;
    }

    /**
     * Ensure there's vertical room for `$h` points of body content.
     * Advance to the next column if one's available; otherwise start
     * a new page. Returns true if a transition happened.
     */
    private function advanceOnOverflow(float $h): bool
    {
        if ($this->cursorY - $h >= $this->bottomMargin()) {
            return false;
        }
        if ($this->columnCount > 1 && $this->currentColumnIndex < $this->columnCount - 1) {
            $this->currentColumnIndex++;
            $this->cursorY = $this->topOfColumn();
            $this->lastFillColor = null;
            return true;
        }
        $this->newPage();
        return true;
    }

    /**
     * Split the content column equally across `$n` columns.
     *
     * @return list<float>
     */
    private function equalColumns(int $n): array
    {
        if ($n <= 0) {
            return [];
        }
        $w = $this->contentWidth() / $n;
        return array_fill(0, $n, $w);
    }

    /**
     * Build a {@see TableRenderContext} from the current theme + style.
     * Bold variant for the header row when `style->headerBold` is true.
     */
    private function tableContext(TableStyle $style): TableRenderContext
    {
        $bodyName = $this->resolveFontName($this->font, $this->bold, $this->italic);
        $headerName = $style->headerBold
            ? $this->resolveFontName($this->font, bold: true, italic: $this->italic)
            : $bodyName;

        return new TableRenderContext(
            bodyFont: $this->ensureFontResource($bodyName),
            bodyMetrics: $this->getMetrics($bodyName),
            headerFont: $this->ensureFontResource($headerName),
            headerMetrics: $this->getMetrics($headerName),
            fontSize: $this->fontSize,
            lineHeight: $this->theme->lineHeight,
            style: $style,
        );
    }

    /**
     * Y-coordinate of the lowest point body content may occupy before
     * a page break is required. This is the page margin plus any
     * reserved footer area.
     */
    private function bottomMargin(): float
    {
        return $this->theme->margin + $this->theme->footerHeight;
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
     * writer and return the font handle. The handle exposes both the
     * resource name (for the Tf operator) and the text encoder (so
     * showText can take UTF-8 directly).
     */
    private function ensureFontResource(string $postScriptName): Font
    {
        if (isset($this->fontResourceCache[$postScriptName])) {
            return $this->fontResourceCache[$postScriptName];
        }
        $standardCase = StandardFont::from($postScriptName);
        $fontHandle = $this->writer->addFont(new Type1Font($standardCase));
        $this->fontResourceCache[$postScriptName] = $fontHandle;
        return $fontHandle;
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, AfmData $metrics, float $size, float $columnWidth): array
    {
        return TextLayout::wrap($text, $metrics, $size, $columnWidth);
    }

    private function measureText(string $text, AfmData $metrics, float $size): float
    {
        return TextLayout::measure($text, $metrics, $size);
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
