<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Sheet\Origin;
use Phpdftk\Css\Sheet\Stylesheet;
use Phpdftk\Html\Dom\Document;
use Phpdftk\Html\Parser as HtmlParser;
use Phpdftk\HtmlToPdf\Box\BoxGenerator;
use Phpdftk\HtmlToPdf\Layout\BlockLayout;
use Phpdftk\HtmlToPdf\Layout\LayoutContext;
use Phpdftk\HtmlToPdf\Painter\Painter;
use Phpdftk\Pdf\Writer\PdfWriter;

/**
 * Top-level façade for `phpdftk/html-to-pdf`. Wires parse → cascade →
 * box generation → layout → paint into one call. Holds no state between
 * invocations — every `render()` produces a fresh `PdfWriter`.
 *
 * Usage:
 *
 *     $result = (new Renderer())->render($html, $css);
 *     $result->writer->save('out.pdf');
 *
 * Or render into an existing writer (the path `Pdf::addHtml` will use):
 *
 *     $warnings = (new Renderer())->renderInto($writer, $html, $css);
 *
 * Phase-1 simplifications: text is only painted when `RendererOptions`
 * carries a `defaultFont`; without one the renderer still produces a
 * structurally-valid PDF with background + border content. `@font-face`
 * resolution and font-family matching land in 1M. Multi-page paginated
 * output lands in 1I (paged media); for now the renderer fits the
 * document onto a single page sized by `RendererOptions`.
 */
final class Renderer
{
    private readonly HtmlParser $htmlParser;
    private readonly CssParser $cssParser;
    private readonly Cascade $cascade;
    private readonly BoxGenerator $boxGenerator;
    private readonly BlockLayout $layout;
    private ?\Phpdftk\Filesystem\ResourceLoader $cachedResourceLoader = null;

    public function __construct(
        public readonly RendererOptions $options = new RendererOptions(),
    ) {
        $this->htmlParser = new HtmlParser();
        $this->cssParser = new CssParser();
        // Wire the page viewport into the cascade so `@media`
        // feature queries (`(min-width: ...)`, `orientation`, etc.)
        // evaluate against the rendered page dimensions in CSS px.
        $cssPxPerPt = 96.0 / 72.0;
        $this->cascade = (new Cascade(PropertyRegistry::default()))
            ->withViewport(
                $this->options->pageWidth * $cssPxPerPt,
                $this->options->pageHeight * $cssPxPerPt,
            );
        $this->boxGenerator = new BoxGenerator($this->cascade, $this->options->baseDir);
        $this->layout = new BlockLayout($this->cascade);
    }

    /**
     * Render `$html` (with optional author CSS) into a fresh `PdfWriter`.
     * Returns a {@see RenderResult} carrying both the writer and any
     * diagnostics that came up.
     */
    public function render(string $html, ?string $css = null): RenderResult
    {
        $writer = new PdfWriter();
        $warnings = $this->renderInto($writer, $html, $css);
        return new RenderResult($writer, $warnings);
    }

    /**
     * Render into an existing `PdfWriter`. Returns the diagnostics
     * emitted; the writer mutation is the visible side effect.
     *
     * @return list<Warning>
     */
    public function renderInto(PdfWriter $writer, string $html, ?string $css = null): array
    {
        $warnings = [];

        $document = $this->htmlParser->parseDocument($html);
        $this->applyDocumentMetadata($document, $writer);
        $sheets = $this->collectStylesheets($css, $document);
        // @font-face parsing: walk every sheet for `@font-face` rules,
        // decode their `data:font/*` sources, and merge the parsed
        // OpenTypeData into a copy of the configured fontMap before the
        // FontResolver gets built. Authored `@font-face` wins over any
        // entry in `RendererOptions::fontMap` that shares its family.
        $fontMap = $this->options->fontMap;
        $faceWarnings = [];
        foreach ($this->loadFontFaces($sheets, $faceWarnings) as $name => $data) {
            $fontMap[strtolower($name)] = $data;
        }
        $warnings = array_merge($warnings, $faceWarnings);
        // CSS Paged Media 3 §6.1: `@page { size: ... }` overrides the
        // renderer's default page dimensions when set. Read it before
        // building the layout context so block layout sees the right
        // containing-block width / height for `%` resolution and the
        // pagination math works against the actual page slot.
        $pageSize = $this->resolvePageSize($sheets);
        $pageWidth = $pageSize['width'];
        $pageHeight = $pageSize['height'];
        // CSS Paged Media 3 §6.2: `@page { margin: ... }` declares the
        // page margins. Phase-1 uses the margin only for positioning the
        // running headers/footers — the body still gets its layout origin
        // from `body { margin }` so existing fixtures stay stable.
        $pageMargins = $this->resolvePageMargins($sheets);
        $root = $this->boxGenerator->generate($document, $sheets);
        // CSS GCPM 3 §5 — capture the named-string store populated
        // during box generation. Page-margin painting reads it to
        // resolve `content: string(name)` references.
        $namedStrings = $this->boxGenerator->getNamedStrings();
        $runningElements = $this->boxGenerator->getRunningElements();
        if ($root === null) {
            $warnings[] = new Warning(
                WarningCode::UnsupportedDisplayType,
                'Document has no <html> root element',
                WarningSeverity::Error,
            );
            $this->maybeThrow($warnings);
            return $warnings;
        }

        $fontResolver = new \Phpdftk\HtmlToPdf\Layout\FontResolver(
            $fontMap,
            $this->options->defaultFont,
            $this->options->faceMap,
        );
        $layoutCtx = new LayoutContext(
            containingBlockWidth: $pageWidth,
            containingBlockHeight: $pageHeight,
            originX: 0.0,
            originY: 0.0,
            lengthContext: new LengthContext(),
            defaultFont: $this->options->defaultFont,
            fontResolver: $fontResolver,
        );
        $this->layout->layout($root, $layoutCtx);

        // If the document contains non-whitespace text but no font was
        // wired in, warn — the text won't render. Lenient mode still
        // produces a valid PDF (background + border content only).
        if ($this->options->defaultFont === null && $this->documentHasText($document)) {
            $warnings[] = new Warning(
                WarningCode::MissingFont,
                'No default font configured — text content will not render. '
                . 'Pass a font via RendererOptions::withDefaultFont().',
                WarningSeverity::Warning,
            );
        }

        // `<img>` without a paintable `data:image/png|jpeg` URL or `alt`
        // fallback won't appear in the output — emit a warning per failing
        // image so callers can surface the missing-resource state.
        $unpaintableImgs = $this->countUnpaintableImages($document);
        if ($unpaintableImgs > 0) {
            $warnings[] = new Warning(
                WarningCode::MissingResource,
                sprintf(
                    '%d <img> element%s without an embeddable data: URL — '
                    . 'remote / file:// image fetching lands with Phase 1L\'s '
                    . 'resource loader. Add `alt="..."` so the fallback flows.',
                    $unpaintableImgs,
                    $unpaintableImgs === 1 ? '' : 's',
                ),
                WarningSeverity::Warning,
            );
        }

        $totalHeight = max($pageHeight, $root->geometry->outerHeight());
        $pageCount = (int) max(1, ceil($totalHeight / $pageHeight));

        // CSS Paged Media 3 §3.4: when a block declares `page: foo`,
        // the page containing its first fragment is tagged "foo" and
        // picks up `@page foo` overrides (background / margins /
        // margin-boxes). Walk the laid-out box tree once to build
        // `pageIndex → name`; later, per-page resolvers overlay the
        // named rules on top of the defaults.
        $pageNames = $this->resolvePageNames($root, $pageHeight, $pageCount);

        // Build an `id → layoutY` map so `<a href="#anchor">` links can
        // resolve to PDF named destinations. Walk the post-layout box tree
        // once.
        $anchorMap = $this->collectAnchors($root);

        // Collect heading boxes ahead of pagination so we can emit a PDF
        // outline once page refs are known.
        $headings = $this->collectHeadings($root);

        // Pre-add all pages up-front so we have a stable PdfReference for
        // every page before any annotation is emitted — a link on page 1
        // may target an anchor on page 3.
        /** @var list<\Phpdftk\Pdf\Writer\Page> $pages */
        $pages = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pages[] = $writer->addPage($pageWidth, $pageHeight);
        }

        // Register the outline now that page refs exist. Build a flat list
        // of headings — nesting under their level is a Phase-2 follow-up.
        $this->emitOutline($headings, $pages, $pageHeight, $writer);

        // CSS Paged Media 3 §3 + Generated Content for Paged Media 3 §2:
        // collect `@page { @<position> { content: "..." } }` blocks once
        // up-front. Phase-1 subset supports static `content: <string>` in
        // the 6 corner / centre margin boxes (top-left / top-center /
        // top-right / bottom-left / bottom-center / bottom-right). The
        // other 10 positions (corner + side rails) and `counter(page)` /
        // `element()` substitution land in follow-ups.
        $pageMarginBoxes = $this->collectPageMarginBoxes($sheets);

        for ($i = 0; $i < $pageCount; $i++) {
            $page = $pages[$i];
            $stream = $writer->addContentStream($page);

            $codepoints = $this->collectCodepoints($html);
            $registeredFont = null;
            /** @var array<string, \Phpdftk\Pdf\Core\Font\RegisteredFont> $registeredMap */
            $registeredMap = [];
            if ($this->options->defaultFont !== null) {
                $registeredFont = $writer->addOpenTypeFont(
                    $this->options->defaultFont,
                    $codepoints,
                    $page,
                );
                $registeredMap[$this->options->defaultFont->postScriptName] = $registeredFont;
            }
            // Register every alternate font in the map so per-fragment
            // font-family switching has a `Tf` resource to reference.
            foreach ($fontMap as $alt) {
                if ($alt === $this->options->defaultFont) {
                    continue;
                }
                if (isset($registeredMap[$alt->postScriptName])) {
                    continue;
                }
                $registeredMap[$alt->postScriptName] = $writer->addOpenTypeFont(
                    $alt,
                    $codepoints,
                    $page,
                );
            }
            // Register every weight/style face the resolver might pick.
            // Same key as defaultFont/fontMap so the painter looks up the
            // right RegisteredFont by postScriptName.
            foreach ($this->options->faceMap as $faces) {
                foreach ($faces as $face) {
                    if (isset($registeredMap[$face->data->postScriptName])) {
                        continue;
                    }
                    $registeredMap[$face->data->postScriptName] = $writer->addOpenTypeFont(
                        $face->data,
                        $codepoints,
                        $page,
                    );
                }
            }

            // Clip every page's drawing to its own MediaBox so the
            // "paint the whole tree, let the viewport drop what's
            // off-page" pagination strategy doesn't leak content past
            // the page boundaries in viewers that don't crop automatically.
            $stream->rectangle(0, 0, $pageWidth, $pageHeight);
            $stream->clip();
            $stream->endPath();
            // CSS Paged Media 3 §3.1: `@page { background-color }`
            // fills the entire page sheet before any content paints.
            // Sits inside the clip so the colour stays on this page
            // even when later content draws over it. When this page
            // is tagged with a name (via a `page: foo` block on it),
            // overlay `@page foo` onto the default rule.
            $pageBgForThis = $this->resolvePageBackground($sheets, $pageNames[$i] ?? null);
            if ($pageBgForThis !== null) {
                $stream->saveGraphicsState();
                $stream->setFillColorRGB(
                    $pageBgForThis->r,
                    $pageBgForThis->g,
                    $pageBgForThis->b,
                );
                $stream->rectangle(0, 0, $pageWidth, $pageHeight);
                $stream->fill();
                $stream->restoreGraphicsState();
            }

            // Page i wants layout-Y rows [i*pageHeight .. (i+1)*pageHeight)
            // to appear at the top of the PDF page. The painter computes
            // PDF Y as `pageHeightConstant - layoutY`; setting the constant
            // to `(i+1)*pageHeight` makes layoutY=i*pageHeight land at
            // PDF Y = pageHeight (top of MediaBox), and layoutY=(i+1)*pageHeight
            // land at PDF Y = 0 (bottom).
            $painter = new Painter(
                ($i + 1) * $pageHeight,
                $registeredFont,
                $page,
                pageRangeStart: $i * $pageHeight,
                pageRangeEnd: ($i + 1) * $pageHeight,
                writer: $writer,
                baseDir: $this->options->baseDir,
                registeredFonts: $registeredMap,
                pageWidth: $pageWidth,
                resourceLoader: $this->options->resourceLoader,
            );
            $painter->paint($root, $stream);
            // Per-page link annotations — emit one /Link per `<a href>` rect
            // the painter collected on this page, clipping to MediaBox so
            // multi-page paint passes don't leak annotations onto unrelated
            // pages.
            $this->emitLinkAnnotations(
                $painter->collectedLinks,
                $writer,
                $page,
                $pageHeight,
                $anchorMap,
                $pages,
            );
            // Paint `@page` margin boxes (running headers / footers) once
            // per page, after the main content stream so they sit on top.
            // Uses the writer's default-font GID map so text shapes against
            // the same font subset as the rest of the document. Per-page
            // selector resolution happens here so `@page :first` only
            // applies to page 0 and `@page :left`/`:right` alternate.
            if ($pageMarginBoxes !== [] && $registeredFont !== null && $this->options->defaultFont !== null) {
                $resolved = $this->resolvePageMarginBoxes($pageMarginBoxes, $i, $pageNames[$i] ?? null);
                if ($resolved !== []) {
                    $this->paintPageMarginBoxes(
                        $stream,
                        $resolved,
                        $pageWidth,
                        $pageHeight,
                        $this->options->defaultFont,
                        $registeredFont,
                        pageIndex: $i,
                        pageCount: $pageCount,
                        fontResolver: $fontResolver,
                        registeredMap: $registeredMap,
                        marginTop: $pageMargins['top'],
                        marginRight: $pageMargins['right'],
                        marginBottom: $pageMargins['bottom'],
                        marginLeft: $pageMargins['left'],
                        namedStrings: $namedStrings,
                        runningElements: $runningElements,
                    );
                }
            }
        }

        return $warnings;
    }

    /**
     * Collect every `<h1>`–`<h6>` box in document order, capturing the
     * heading level, the rendered text content, and the box's top-edge Y
     * in layout space. Headings without text content are skipped.
     *
     * @return list<array{level: int, text: string, layoutY: float}>
     */
    private function collectHeadings(\Phpdftk\HtmlToPdf\Box\Box $root): array
    {
        $out = [];
        // Pre-order DFS in document order via a stack with reverse-pushed
        // children (so the first child is processed before its siblings).
        // Only match on `BlockBox` so TextBox / InlineBox children (which
        // share their parent's element ref) don't get double-counted.
        $stack = [$root];
        while ($stack !== []) {
            $node = array_shift($stack);
            $element = $node->element;
            if ($node instanceof \Phpdftk\HtmlToPdf\Box\BlockBox
                && $element !== null
                && preg_match('/^h([1-6])$/', strtolower($element->localName), $m) === 1
            ) {
                $text = trim($this->collectTextContent($node));
                if ($text !== '') {
                    $out[] = [
                        'level' => (int) $m[1],
                        'text' => $text,
                        'layoutY' => $node->geometry->y,
                    ];
                }
            }
            $children = $node->children;
            foreach (array_reverse($children) as $c) {
                array_unshift($stack, $c);
            }
        }
        return $out;
    }

    /** Recursively collect TextBox content under a box. */
    private function collectTextContent(\Phpdftk\HtmlToPdf\Box\Box $box): string
    {
        $out = '';
        if ($box instanceof \Phpdftk\HtmlToPdf\Box\TextBox) {
            return $box->text;
        }
        foreach ($box->children as $c) {
            $out .= $this->collectTextContent($c);
        }
        return $out;
    }

    /**
     * Register a PDF outline (bookmarks tree) from the collected headings,
     * nesting `<hN>` under the most recent heading of lower N (so `<h2>`s
     * appear under their preceding `<h1>`, etc.). Headings that open at a
     * deeper level than any prior sibling create an implicit parent chain
     * at the outline root — matching what browsers do for "reader mode"
     * outlines.
     *
     * @param list<array{level: int, text: string, layoutY: float}> $headings
     * @param list<\Phpdftk\Pdf\Writer\Page> $pages
     */
    private function emitOutline(array $headings, array $pages, float $pageHeight, PdfWriter $writer): void
    {
        if ($headings === [] || $pages === []) {
            return;
        }
        $outline = new \Phpdftk\Pdf\Core\Document\Outline();
        $writer->register($outline);
        $outlineRef = new \Phpdftk\Pdf\Core\PdfReference($outline->objectNumber);

        /**
         * Each entry: ['item' => OutlineItem, 'level' => int, 'children' =>
         * list<int> indices into $entries].
         *
         * @var list<array{item: \Phpdftk\Pdf\Core\Document\OutlineItem, level: int, parent: ?int, children: list<int>}> $entries
         */
        $entries = [];
        /** @var list<int> $stack stack of $entries indices being descended into */
        $stack = [];
        foreach ($headings as $h) {
            $pageIdx = max(0, min(count($pages) - 1, (int) floor($h['layoutY'] / $pageHeight)));
            $localY = $h['layoutY'] - $pageIdx * $pageHeight;
            $top = max(0.0, min($pageHeight, $pageHeight - $localY));
            $pageRef = new \Phpdftk\Pdf\Core\PdfReference($pages[$pageIdx]->corePage()->objectNumber);
            $item = new \Phpdftk\Pdf\Core\Document\OutlineItem($h['text']);
            $item->dest = \Phpdftk\Pdf\Core\Document\Destination::xyz($pageRef, null, $top);
            $writer->register($item);
            // Pop stack while top.level >= this.level so we ascend to the
            // appropriate parent.
            while ($stack !== [] && $entries[$stack[array_key_last($stack)]]['level'] >= $h['level']) {
                array_pop($stack);
            }
            $parentIdx = $stack === [] ? null : $stack[array_key_last($stack)];
            $idx = count($entries);
            $entries[] = [
                'item' => $item,
                'level' => $h['level'],
                'parent' => $parentIdx,
                'children' => [],
            ];
            if ($parentIdx !== null) {
                $entries[$parentIdx]['children'][] = $idx;
            }
            $stack[] = $idx;
        }

        // Wire references now that every item has an object number.
        $rootChildren = [];
        foreach ($entries as $idx => $entry) {
            $item = $entry['item'];
            $parentIdx = $entry['parent'];
            $item->parent = $parentIdx === null
                ? $outlineRef
                : new \Phpdftk\Pdf\Core\PdfReference($entries[$parentIdx]['item']->objectNumber);
            $children = $entry['children'];
            if ($children !== []) {
                $first = $entries[$children[0]]['item'];
                $last = $entries[$children[array_key_last($children)]]['item'];
                $item->first = new \Phpdftk\Pdf\Core\PdfReference($first->objectNumber);
                $item->last = new \Phpdftk\Pdf\Core\PdfReference($last->objectNumber);
                $item->count = count($children); // direct children only — collapsed by default
            }
            if ($parentIdx === null) {
                $rootChildren[] = $idx;
            }
        }

        // Sibling prev/next chains per parent.
        $linkSiblings = static function (array $sibIdxs) use ($entries): void {
            $n = count($sibIdxs);
            for ($i = 0; $i < $n; $i++) {
                $item = $entries[$sibIdxs[$i]]['item'];
                if ($i > 0) {
                    $item->prev = new \Phpdftk\Pdf\Core\PdfReference(
                        $entries[$sibIdxs[$i - 1]]['item']->objectNumber,
                    );
                }
                if ($i < $n - 1) {
                    $item->next = new \Phpdftk\Pdf\Core\PdfReference(
                        $entries[$sibIdxs[$i + 1]]['item']->objectNumber,
                    );
                }
            }
        };
        $linkSiblings($rootChildren);
        foreach ($entries as $entry) {
            if ($entry['children'] !== []) {
                $linkSiblings($entry['children']);
            }
        }

        if ($rootChildren !== []) {
            $outline->first = new \Phpdftk\Pdf\Core\PdfReference(
                $entries[$rootChildren[0]]['item']->objectNumber,
            );
            $outline->last = new \Phpdftk\Pdf\Core\PdfReference(
                $entries[$rootChildren[array_key_last($rootChildren)]]['item']->objectNumber,
            );
        }
        $outline->count = count($rootChildren);
        $catalog = $writer->getCatalog();
        $catalog->outlines = $outlineRef;
        // Open the outline pane by default so users see the bookmark tree
        // when the PDF opens. Authors can post-hoc override the page mode
        // via `PdfWriter`'s catalog accessor.
        if ($catalog->pageMode === null) {
            $catalog->pageMode = new \Phpdftk\Pdf\Core\PdfName('UseOutlines');
        }
    }

    /**
     * Walk the laid-out box tree and record `id → layoutY` for every box
     * whose originating element has an `id` attribute. Also captures the
     * legacy HTML4 `<a name="...">` form. The Y is the top edge of the
     * box's content area in layout-space (top-down) — that's what we want
     * to scroll-to for a `#anchor` jump.
     *
     * @return array<string, float>
     */
    private function collectAnchors(\Phpdftk\HtmlToPdf\Box\Box $root): array
    {
        $map = [];
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            $element = $node->element;
            if ($element !== null) {
                $id = $element->getAttribute('id');
                if ($id !== null && $id !== '' && !isset($map[$id])) {
                    $map[$id] = $node->geometry->y;
                }
                if (strtolower($element->localName) === 'a') {
                    $name = $element->getAttribute('name');
                    if ($name !== null && $name !== '' && !isset($map[$name])) {
                        $map[$name] = $node->geometry->y;
                    }
                }
            }
            foreach ($node->children as $c) {
                $stack[] = $c;
            }
        }
        return $map;
    }

    /**
     * Parse the HTML into a DOM for inspection / manipulation by callers.
     * Useful for hand-tweaking output before re-rendering.
     */
    public function parse(string $html): Document
    {
        return $this->htmlParser->parseDocument($html);
    }

    public function parseStylesheet(string $css): Stylesheet
    {
        return $this->cssParser->parseStylesheet($css);
    }

    /**
     * Build the cascade-ordered list: UA, then the caller-supplied author
     * CSS (when non-empty), then every embedded `<style>` element's content
     * in document order. Document `<style>` rules win over the explicit
     * `$authorCss` (later source wins per CSS Cascade 5 §6.3) so authors
     * can use `$authorCss` to inject defaults the document can override.
     *
     * @return list<Stylesheet>
     */
    private function collectStylesheets(?string $authorCss, Document $document): array
    {
        $uaSheet = $this->cssParser->parseStylesheet(
            $this->options->effectiveUserAgentStylesheet(),
            Origin::UserAgent,
        );
        $sheets = [$this->expandImports($uaSheet)];
        if ($authorCss !== null && $authorCss !== '') {
            $sheets[] = $this->expandImports(
                $this->cssParser->parseStylesheet($authorCss, Origin::Author),
            );
        }
        foreach ($this->extractAuthorCss($document) as $css) {
            $sheets[] = $this->expandImports(
                $this->cssParser->parseStylesheet($css, Origin::Author),
            );
        }
        return $sheets;
    }

    /**
     * Resolve every top-of-sheet `@import url(...) [media]` at-rule by
     * fetching the target CSS, parsing it, recursing for nested imports,
     * and splicing the imported rules in at the `@import` position so
     * cascade source-order is preserved. Per CSS Cascade 5 §6.3 the
     * imported sheet's rules behave as if pasted at the import point,
     * with later rules in the importing sheet still winning ties.
     *
     * Recursion depth-capped at 16 per `docs/plans/html-and-svg.md`
     * Security defaults to prevent `a.css → b.css → a.css` loops blowing
     * the stack. Unloadable imports drop silently — the renderer keeps
     * going with the remaining rules.
     */
    private function expandImports(\Phpdftk\Css\Sheet\Stylesheet $sheet, int $depth = 0): \Phpdftk\Css\Sheet\Stylesheet
    {
        if ($depth >= 16) {
            return $sheet;
        }
        $newRules = [];
        $importsAllowed = true;
        foreach ($sheet->rules as $rule) {
            if ($rule instanceof \Phpdftk\Css\Sheet\AtRule
                && strtolower($rule->name) === 'import'
                && $importsAllowed
            ) {
                $imported = $this->loadImport($rule, $sheet->origin);
                if ($imported !== null) {
                    $expanded = $this->expandImports($imported, $depth + 1);
                    foreach ($expanded->rules as $r) {
                        $newRules[] = $r;
                    }
                }
                continue;
            }
            // CSS Syntax 3: `@import` must precede every other rule
            // (except `@charset` and other `@import`s). Once we hit a
            // non-import at top level, the import window closes.
            if ($rule instanceof \Phpdftk\Css\Sheet\StyleRule) {
                $importsAllowed = false;
            }
            $newRules[] = $rule;
        }
        return new \Phpdftk\Css\Sheet\Stylesheet($newRules, $sheet->origin);
    }

    /**
     * Load a single `@import` at-rule's target CSS and return the
     * parsed Stylesheet, or null when the URL fails to resolve, the
     * fetch fails, or a media-query filter rejects the sheet.
     *
     * The prelude is hand-extracted with a small grammar:
     *   - `url("…")` / `url('…')` / `url(…)` (quoted or bare)
     *   - bare `"…"` / `'…'`
     *   - optional trailing media-query list
     */
    private function loadImport(
        \Phpdftk\Css\Sheet\AtRule $rule,
        \Phpdftk\Css\Sheet\Origin $origin,
    ): ?\Phpdftk\Css\Sheet\Stylesheet {
        $prelude = trim($rule->prelude);
        $href = null;
        $remainder = '';
        if (preg_match('~^url\(\s*("([^"]*)"|\'([^\']*)\'|([^)\s]*))\s*\)\s*(.*)$~i', $prelude, $m) === 1) {
            $href = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : $m[4]);
            $remainder = $m[5];
        } elseif (preg_match('~^"([^"]*)"\s*(.*)$~', $prelude, $m) === 1) {
            $href = $m[1];
            $remainder = $m[2];
        } elseif (preg_match("~^'([^']*)'\\s*(.*)\$~", $prelude, $m) === 1) {
            $href = $m[1];
            $remainder = $m[2];
        }
        if ($href === null || $href === '') {
            return null;
        }
        // Optional trailing media query. Phase-1 matcher accepts
        // `print` / `all` / lists containing either.
        $remainder = trim($remainder);
        if ($remainder !== '' && !$this->mediaPreludeMatches($remainder)) {
            return null;
        }
        // Resolve the URL (data:text/css… or relative under baseDir).
        $css = $this->fetchImportSource($href);
        if ($css === null) {
            return null;
        }
        return $this->cssParser->parseStylesheet($css, $origin);
    }

    /**
     * Resolve an `@import` href to its raw CSS bytes via the unified
     * `Phpdftk\Filesystem\ResourceLoader`. `data:` URLs must declare a
     * `text/css` MIME for `@import`; the loader's MIME allowlist
     * enforces that. Filesystem paths use the same realpath-escape +
     * stream-wrapper gates as every other resource.
     */
    private function fetchImportSource(string $href): ?string
    {
        // http(s) routes through phpdftk/resource-loader when one
        // is attached; otherwise drops with a missing-resource
        // warning consistent with @font-face http behaviour.
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $sink = [];
            return $this->fetchHttpResource($href, $sink, '@import');
        }
        return $this->resourceLoader()->load($href, allowedMimes: ['text/css']);
    }

    /**
     * Cached `ResourceLoader` bound to the renderer's `baseDir`.
     * Created lazily so renderers without `baseDir` still construct
     * cleanly — the loader handles a null base by rejecting filesystem
     * paths while still decoding data: URLs.
     */
    private function resourceLoader(): \Phpdftk\Filesystem\ResourceLoader
    {
        return $this->cachedResourceLoader
            ??= new \Phpdftk\Filesystem\ResourceLoader($this->options->baseDir);
    }

    /**
     * Emit `/Link` annotations for the painter's collected link rects on
     * this page. Each rect is clipped to the page's MediaBox (`[0, 0,
     * pageWidth, pageHeight]`); rects entirely outside the page are
     * dropped — this handles the multi-page paint pass cleanly because
     * the painter walks the full box tree per page with a shifted Y
     * constant.
     *
     * @param list<array{href: string, llx: float, lly: float, urx: float, ury: float}> $links
     */
    /**
     * @param list<array{href: string, llx: float, lly: float, urx: float, ury: float, title: ?string}> $links
     * @param array<string, float> $anchorMap
     * @param list<\Phpdftk\Pdf\Writer\Page> $allPages
     */
    private function emitLinkAnnotations(
        array $links,
        PdfWriter $writer,
        \Phpdftk\Pdf\Writer\Page $page,
        float $pageHeight,
        array $anchorMap,
        array $allPages,
    ): void {
        if ($links === []) {
            return;
        }
        $corePage = $page->corePage();
        foreach ($links as $link) {
            // Drop rects entirely above or below the page box.
            if ($link['ury'] <= 0.0 || $link['lly'] >= $pageHeight) {
                continue;
            }
            $llx = $link['llx'];
            $urx = $link['urx'];
            $lly = max(0.0, $link['lly']);
            $ury = min($pageHeight, $link['ury']);
            if ($ury - $lly <= 0.0 || $urx - $llx <= 0.0) {
                continue;
            }
            $rect = new \Phpdftk\Pdf\Core\PdfArray([
                new \Phpdftk\Pdf\Core\PdfNumber($llx),
                new \Phpdftk\Pdf\Core\PdfNumber($lly),
                new \Phpdftk\Pdf\Core\PdfNumber($urx),
                new \Phpdftk\Pdf\Core\PdfNumber($ury),
            ]);
            $annotation = new \Phpdftk\Pdf\Core\Annotation\LinkAnnotation($rect);
            // Suppress the default 1-unit black border that PDF readers
            // overlay on `/Link` annotations — browser print output never
            // shows a frame around links and our text already carries
            // the styling (`color`, `text-decoration: underline`).
            $annotation->border = new \Phpdftk\Pdf\Core\PdfArray([
                new \Phpdftk\Pdf\Core\PdfNumber(0),
                new \Phpdftk\Pdf\Core\PdfNumber(0),
                new \Phpdftk\Pdf\Core\PdfNumber(0),
            ]);
            if (($link['title'] ?? null) !== null && $link['title'] !== '') {
                $annotation->contents = new \Phpdftk\Pdf\Core\PdfString($link['title']);
            }

            $dest = $this->resolveFragmentDestination($link['href'], $anchorMap, $allPages, $pageHeight);
            if ($dest !== null) {
                $annotation->dest = $dest;
            } else {
                $actionDict = new \Phpdftk\Pdf\Core\PdfDictionary();
                $actionDict->set('Type', new \Phpdftk\Pdf\Core\PdfName('Action'));
                $actionDict->set('S', new \Phpdftk\Pdf\Core\PdfName('URI'));
                $actionDict->set('URI', new \Phpdftk\Pdf\Core\PdfString($link['href']));
                $annotation->a = $actionDict;
            }

            $writer->register($annotation);
            $corePage->annots[] = new \Phpdftk\Pdf\Core\PdfReference($annotation->objectNumber);
        }
    }

    /**
     * If `$href` is a fragment of the form `#anchor` and the anchor is in
     * the document, return the matching {@see Destination::xyz} pointing
     * at the right page + Y. Otherwise return null and the caller will
     * fall back to a URI action.
     *
     * @param array<string, float> $anchorMap
     * @param list<\Phpdftk\Pdf\Writer\Page> $allPages
     */
    private function resolveFragmentDestination(
        string $href,
        array $anchorMap,
        array $allPages,
        float $pageHeight,
    ): ?\Phpdftk\Pdf\Core\Document\Destination {
        if (!str_starts_with($href, '#')) {
            return null;
        }
        $anchor = substr($href, 1);
        if (!isset($anchorMap[$anchor])) {
            return null;
        }
        $layoutY = $anchorMap[$anchor];
        $pageIdx = (int) floor($layoutY / $pageHeight);
        if ($pageIdx < 0 || $pageIdx >= count($allPages)) {
            return null;
        }
        $localY = $layoutY - $pageIdx * $pageHeight;
        $top = $pageHeight - $localY;
        $pageRef = new \Phpdftk\Pdf\Core\PdfReference($allPages[$pageIdx]->corePage()->objectNumber);
        return \Phpdftk\Pdf\Core\Document\Destination::xyz($pageRef, null, $top);
    }

    /**
     * Map the document's `<title>` element + key `<meta>` tags onto the
     * PDF's `/Info` dictionary. Supported author conventions:
     *  - `<title>` → /Title
     *  - `<meta name="author">` → /Author
     *  - `<meta name="description">` → /Subject
     *  - `<meta name="keywords">` → /Keywords
     *
     * Skips entries that are missing or empty so the renderer doesn't
     * stomp on an `/Info` already populated by the caller via
     * `PdfWriter::setInfo`.
     */
    private function applyDocumentMetadata(Document $document, PdfWriter $writer): void
    {
        $title = $this->findTextOfFirstElement($document, 'title');
        $author = $this->findMetaContent($document, 'author');
        $description = $this->findMetaContent($document, 'description');
        $keywords = $this->findMetaContent($document, 'keywords');
        $info = new \Phpdftk\Pdf\Core\Document\Info();
        if ($title !== null) {
            $info->title = new \Phpdftk\Pdf\Core\PdfString($title);
        }
        if ($author !== null) {
            $info->author = new \Phpdftk\Pdf\Core\PdfString($author);
        }
        if ($description !== null) {
            $info->subject = new \Phpdftk\Pdf\Core\PdfString($description);
        }
        if ($keywords !== null) {
            $info->keywords = new \Phpdftk\Pdf\Core\PdfString($keywords);
        }
        // Always identify the renderer in the standard /Creator +
        // /Producer entries so downstream tooling (verapdf, qpdf, etc.)
        // can trace the pipeline a PDF came from — including docs that
        // don't carry <title> / <meta> tags themselves.
        $info->creator = new \Phpdftk\Pdf\Core\PdfString('phpdftk/html-to-pdf');
        $info->producer = new \Phpdftk\Pdf\Core\PdfString('phpdftk');
        // ISO 32000-2 §7.9.4 PDF date format:
        // `(D:YYYYMMDDHHmmSSOHH'mm')` with O ∈ {Z, +, -}.
        $info->creationDate = new \Phpdftk\Pdf\Core\PdfString($this->formatPdfDate(new \DateTimeImmutable()));
        $writer->setInfo($info);
    }

    private function formatPdfDate(\DateTimeImmutable $dt): string
    {
        $offset = $dt->getOffset();
        if ($offset === 0) {
            $tz = 'Z';
        } else {
            $sign = $offset >= 0 ? '+' : '-';
            $absOffset = abs($offset);
            $hours = intdiv($absOffset, 3600);
            $minutes = intdiv($absOffset % 3600, 60);
            $tz = sprintf("%s%02d'%02d'", $sign, $hours, $minutes);
        }
        return 'D:' . $dt->format('YmdHis') . $tz;
    }

    private function findTextOfFirstElement(Document $document, string $localName): ?string
    {
        $stack = [$document->documentElement];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node === null) {
                continue;
            }
            if (strtolower($node->localName) === $localName) {
                $text = '';
                for ($t = $node->firstChild; $t !== null; $t = $t->nextSibling) {
                    if ($t instanceof \Phpdftk\Html\Dom\Text) {
                        $text .= $t->data;
                    }
                }
                $text = trim($text);
                return $text === '' ? null : $text;
            }
            for ($child = $node->firstChild; $child !== null; $child = $child->nextSibling) {
                if ($child instanceof \Phpdftk\Html\Dom\Element) {
                    $stack[] = $child;
                }
            }
        }
        return null;
    }

    private function findMetaContent(Document $document, string $nameAttr): ?string
    {
        $stack = [$document->documentElement];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node === null) {
                continue;
            }
            if (strtolower($node->localName) === 'meta') {
                $name = $node->getAttribute('name');
                if ($name !== null && strtolower($name) === $nameAttr) {
                    $content = $node->getAttribute('content');
                    if ($content !== null && trim($content) !== '') {
                        return trim($content);
                    }
                }
            }
            for ($child = $node->firstChild; $child !== null; $child = $child->nextSibling) {
                if ($child instanceof \Phpdftk\Html\Dom\Element) {
                    $stack[] = $child;
                }
            }
        }
        return null;
    }

    /**
     * Walk the document for both `<style>…</style>` element contents
     * AND `<link rel="stylesheet" href="…">` external sheets, yielding
     * each loaded CSS chunk in document order so the cascade's
     * later-source-wins rule operates on the right ordering. External
     * `<link>` sheets resolve via:
     *   - `data:text/css[;base64],…` payloads (decoded)
     *   - relative-or-absolute paths under `RendererOptions::baseDir`
     *     (with `realpath` escape rejection + stream-wrapper rejection
     *     mirroring `@font-face` / `<img src>` resolution)
     *
     * Unloadable `<link>` hrefs silently skip (no Warning yet — the
     * resource loader gate in 1L proper will surface them).
     *
     * @return list<string>
     */
    private function extractAuthorCss(Document $document): array
    {
        $out = [];
        $stack = [$document->documentElement];
        while ($stack !== []) {
            $node = array_shift($stack);
            if ($node === null) {
                continue;
            }
            // Depth-first in document order: push children in reverse so the
            // first child is processed next.
            $children = [];
            for ($child = $node->firstChild; $child !== null; $child = $child->nextSibling) {
                if ($child instanceof \Phpdftk\Html\Dom\Element) {
                    $children[] = $child;
                }
            }
            foreach (array_reverse($children) as $c) {
                array_unshift($stack, $c);
            }
            $local = strtolower($node->localName);
            if ($local === 'style') {
                $text = '';
                for ($t = $node->firstChild; $t !== null; $t = $t->nextSibling) {
                    if ($t instanceof \Phpdftk\Html\Dom\Text) {
                        $text .= $t->data;
                    }
                }
                if (trim($text) !== '') {
                    $out[] = $text;
                }
            } elseif ($local === 'link') {
                $css = $this->loadLinkedStylesheet($node);
                if ($css !== null) {
                    $out[] = $css;
                }
            }
        }
        return $out;
    }

    /**
     * Collect every codepoint in the HTML so the font registration can
     * subset to just the used glyphs. Done by stripping tags and walking
     * UTF-8 codepoints — fast enough for Phase 1; a proper text-node
     * walk over the DOM lands in 1N-bis.
     *
     * @return list<int>
     */
    private function collectCodepoints(string $html): array
    {
        $stripped = strip_tags($html);
        $seen = [];
        // Always include the characters that may be needed for counter-style
        // list markers (decimal / alpha / roman) and basic punctuation —
        // even if the document body doesn't use them — so `<ol>` markers
        // can shape against the registered font subset.
        foreach (range(ord('0'), ord('9')) as $cp) {
            $seen[$cp] = true;
        }
        foreach (range(ord('a'), ord('z')) as $cp) {
            $seen[$cp] = true;
        }
        foreach (range(ord('A'), ord('Z')) as $cp) {
            $seen[$cp] = true;
        }
        foreach ([ord('.'), ord(','), ord(':'), ord(';'), ord(' ')] as $cp) {
            $seen[$cp] = true;
        }
        // U+2026 HORIZONTAL ELLIPSIS — emitted by `text-overflow: ellipsis`
        // and useful punctuation in body text.
        $seen[0x2026] = true;
        // U+200B ZERO-WIDTH SPACE — emitted by the `<wbr>` lowering as
        // a soft-break opportunity that fonts may or may not support;
        // request the glyph so the subset captures it when present.
        $seen[0x200B] = true;
        $i = 0;
        $bytes = strlen($stripped);
        while ($i < $bytes) {
            $b = ord($stripped[$i]);
            if ($b < 0x80) {
                $seen[$b] = true;
                $i++;
            } elseif ($b < 0xC0) {
                $i++;
            } elseif ($b < 0xE0) {
                $cp = (($b & 0x1F) << 6) | (ord($stripped[$i + 1] ?? "\x00") & 0x3F);
                $seen[$cp] = true;
                $i += 2;
            } elseif ($b < 0xF0) {
                $cp = (($b & 0x0F) << 12)
                    | ((ord($stripped[$i + 1] ?? "\x00") & 0x3F) << 6)
                    | (ord($stripped[$i + 2] ?? "\x00") & 0x3F);
                $seen[$cp] = true;
                $i += 3;
            } else {
                $cp = (($b & 0x07) << 18)
                    | ((ord($stripped[$i + 1] ?? "\x00") & 0x3F) << 12)
                    | ((ord($stripped[$i + 2] ?? "\x00") & 0x3F) << 6)
                    | (ord($stripped[$i + 3] ?? "\x00") & 0x3F);
                $seen[$cp] = true;
                $i += 4;
            }
        }
        return array_keys($seen);
    }

    /**
     * Count `<img>` elements (post-parse, ignoring scripted dynamism since
     * we don't run JS). Drives the MissingResource warning emitted when
     * image painting is unsupported in the current phase.
     */
    private function countUnpaintableImages(Document $document): int
    {
        $count = 0;
        $stack = [$document->documentElement];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node === null) {
                continue;
            }
            if (strtolower($node->localName) === 'img') {
                $src = $node->getAttribute('src');
                $alt = $node->getAttribute('alt');
                if (!$this->isPaintableImageSrc($src) && ($alt === null || $alt === '')) {
                    $count++;
                }
            }
            for ($c = $node->firstChild; $c !== null; $c = $c->nextSibling) {
                if ($c instanceof \Phpdftk\Html\Dom\Element) {
                    $stack[] = $c;
                }
            }
        }
        return $count;
    }

    /**
     * Mirror the painter's "can this `<img src>` be drawn?" decision so the
     * MissingResource warning doesn't false-positive on local-file paths
     * the painter actually handles. Accepts `data:image/{png,jpeg}` URLs
     * unconditionally; `http(s)://` when the options carry an HTTP
     * ResourceLoader (4F.5); for filesystem paths, requires `baseDir`
     * and that `realpath()` resolves under it.
     */
    private function isPaintableImageSrc(?string $src): bool
    {
        if ($src === null || $src === '') {
            return false;
        }
        if (preg_match('~^data:image/(png|jpeg|jpg);~', $src) === 1) {
            return true;
        }
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $this->options->resourceLoader !== null;
        }
        return $this->resourceLoader()->resolveLocalPath($src) !== null;
    }

    /**
     * Walk the parsed DOM looking for any non-whitespace text content.
     * Used to decide whether a missing default-font is worth warning about.
     */
    private function documentHasText(Document $document): bool
    {
        $stack = [$document->documentElement];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node === null) {
                continue;
            }
            for ($child = $node->firstChild; $child !== null; $child = $child->nextSibling) {
                if ($child instanceof \Phpdftk\Html\Dom\Text) {
                    if (trim($child->data) !== '') {
                        return true;
                    }
                    continue;
                }
                if ($child instanceof \Phpdftk\Html\Dom\Element) {
                    // Skip head, script, style — they don't render.
                    $local = strtolower($child->localName);
                    if (in_array($local, ['head', 'script', 'style', 'title', 'meta', 'link', 'base'], true)) {
                        continue;
                    }
                    $stack[] = $child;
                }
            }
        }
        return false;
    }

    /**
     * Walk every supplied stylesheet for `@font-face` rules, decode each
     * rule's `src: url(...)` into raw font bytes (via `data:` URLs or
     * `file://` paths resolved against `RendererOptions::baseDir`), parse
     * the bytes with `OpenTypeParser`, and yield `family-name => OpenTypeData`.
     *
     * Phase-1 scope: OTF/CFF only (matches what `OpenTypeParser` accepts),
     * `data:` and resolved-local sources only — remote `http(s)://` fetch
     * lands in Phase 2 behind the same `ResourceLoader` gate. Per-face
     * failures emit a Warning and the face is dropped; the renderer keeps
     * going with the rest of the document. Multi-value `src` lists are
     * walked left-to-right; the first source that parses wins. The CSS
     * `format(...)` hint is accepted but never trusted — magic-number
     * detection on the decoded bytes is the actual gate.
     *
     * @param list<Stylesheet> $sheets
     * @param list<Warning> $warnings
     * @return iterable<string, \Phpdftk\FontParser\OpenTypeData>
     */
    private function loadFontFaces(array $sheets, array &$warnings): iterable
    {
        foreach ($sheets as $sheet) {
            foreach ($sheet->rules as $rule) {
                if (!$rule instanceof \Phpdftk\Css\Sheet\AtRule) {
                    continue;
                }
                if (strtolower($rule->name) !== 'font-face') {
                    continue;
                }
                if ($rule->block === null) {
                    continue;
                }
                $family = null;
                /** @var list<\Phpdftk\Css\Value\Value> $srcCandidates */
                $srcCandidates = [];
                foreach ($rule->block->contents as $item) {
                    if (!$item instanceof \Phpdftk\Css\Sheet\Declaration) {
                        continue;
                    }
                    if ($item->property === 'font-family') {
                        $family = $this->fontFamilyName($item->value);
                    } elseif ($item->property === 'src') {
                        $srcCandidates = $this->splitSrcList($item->value);
                    }
                }
                if ($family === null || $family === '' || $srcCandidates === []) {
                    $warnings[] = new Warning(
                        WarningCode::UnsupportedCssValue,
                        '@font-face rule missing `font-family` or `src` — face dropped.',
                        WarningSeverity::Warning,
                    );
                    continue;
                }
                $data = null;
                foreach ($srcCandidates as $candidate) {
                    // Honour the CSS Fonts 4 §4.3 `format()` hint when one
                    // is supplied. An unsupported hint skips the source
                    // without touching the fetch path — useful for authors
                    // shipping `url(font.woff2) format("woff2"),
                    // url(font.otf) format("opentype")` fallback chains.
                    if ($candidate['format'] !== null
                        && !in_array($candidate['format'], self::SUPPORTED_FONT_FORMATS, true)
                    ) {
                        continue;
                    }
                    $bytes = $this->fetchFontSource($candidate['url'], $warnings);
                    if ($bytes === null) {
                        continue;
                    }
                    // WOFF 1.0 wraps OTF/TTF in a zlib-compressed
                    // container; transparently unwrap so the downstream
                    // OpenTypeParser sees the original SFNT.
                    if (\Phpdftk\FontParser\WoffParser::isWoff($bytes)) {
                        try {
                            $bytes = \Phpdftk\FontParser\WoffParser::decompressBytes($bytes);
                        } catch (\Throwable $e) {
                            $warnings[] = new Warning(
                                WarningCode::UnsupportedCssValue,
                                sprintf(
                                    '@font-face `%s` WOFF source failed to decompress: %s',
                                    $family,
                                    $e->getMessage(),
                                ),
                                WarningSeverity::Warning,
                            );
                            continue;
                        }
                    }
                    try {
                        $data = \Phpdftk\FontParser\OpenTypeParser::fromBytes($bytes)->parse();
                        break;
                    } catch (\Throwable $e) {
                        $warnings[] = new Warning(
                            WarningCode::UnsupportedCssValue,
                            sprintf(
                                '@font-face `%s` source failed to parse: %s',
                                $family,
                                $e->getMessage(),
                            ),
                            WarningSeverity::Warning,
                        );
                    }
                }
                if ($data === null) {
                    $warnings[] = new Warning(
                        WarningCode::MissingResource,
                        sprintf(
                            '@font-face `%s` has no loadable source — face dropped.',
                            $family,
                        ),
                        WarningSeverity::Warning,
                    );
                    continue;
                }
                yield $family => $data;
            }
        }
    }

    /**
     * Extract the family name from a `font-family` value inside an
     * `@font-face` block. Accepts a `StringValue` (`"My Font"`) or a
     * `Keyword` or a space-separated `ValueList` of keywords (the
     * unquoted-multi-word form `font-family: My Font`).
     */
    private function fontFamilyName(\Phpdftk\Css\Value\Value $value): ?string
    {
        if ($value instanceof \Phpdftk\Css\Value\StringValue) {
            $name = trim($value->value);
            return $name === '' ? null : $name;
        }
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            $name = trim($value->name);
            return $name === '' ? null : $name;
        }
        if ($value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Space
        ) {
            $parts = [];
            foreach ($value->values as $v) {
                if ($v instanceof \Phpdftk\Css\Value\Keyword) {
                    $parts[] = $v->name;
                } elseif ($v instanceof \Phpdftk\Css\Value\StringValue) {
                    $parts[] = $v->value;
                }
            }
            $name = trim(implode(' ', $parts));
            return $name === '' ? null : $name;
        }
        return null;
    }

    /**
     * Split a `src:` value into its candidate sources (comma-separated by
     * the CSS Fonts 4 grammar). Each element is a `{url, format}` tuple:
     * `url` is the bare `Url` or `url()`/`local()` `CssFunction`; `format`
     * is the lower-cased identifier from the optional trailing
     * `format(<keyword|string>)` sibling, or null when no hint is given.
     * CSS Fonts 4 §4.3: the format hint is advisory, not load-blocking,
     * but it lets the resolver skip sources it can't decode without
     * attempting the parse.
     *
     * @return list<array{url: \Phpdftk\Css\Value\Value, format: ?string}>
     */
    private function splitSrcList(\Phpdftk\Css\Value\Value $value): array
    {
        $candidates = $value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Comma
                ? $value->values
                : [$value];
        $out = [];
        foreach ($candidates as $c) {
            if ($c instanceof \Phpdftk\Css\Value\ValueList
                && $c->separator === \Phpdftk\Css\Value\ListSeparator::Space
                && $c->values !== []
            ) {
                $out[] = [
                    'url' => $c->values[0],
                    'format' => $this->extractFormatHint($c->values),
                ];
                continue;
            }
            $out[] = ['url' => $c, 'format' => null];
        }
        return $out;
    }

    /**
     * Find a `format(...)` `CssFunction` in the space-list and return its
     * first argument as a lower-cased string. Tolerates the function's
     * argument being either a `Keyword` or a `StringValue` (both spec
     * variants). Returns null when no `format()` sibling is present.
     *
     * @param list<\Phpdftk\Css\Value\Value> $siblings
     */
    private function extractFormatHint(array $siblings): ?string
    {
        foreach ($siblings as $s) {
            if (!$s instanceof \Phpdftk\Css\Value\CssFunction
                || strtolower($s->name) !== 'format'
                || $s->arguments === []
            ) {
                continue;
            }
            $first = $s->arguments[0];
            if ($first instanceof \Phpdftk\Css\Value\StringValue) {
                return strtolower($first->value);
            }
            if ($first instanceof \Phpdftk\Css\Value\Keyword) {
                return strtolower($first->name);
            }
        }
        return null;
    }

    /**
     * The set of `format(...)` hints we can decode at Phase 1. OTF/CFF
     * goes through `OpenTypeParser`; everything else (WOFF/WOFF2/EOT/
     * SVG/TTC) requires decompression or extra parsers that haven't
     * landed yet. Hints outside this set make the resolver skip the
     * source without attempting a fetch.
     */
    private const SUPPORTED_FONT_FORMATS = [
        'opentype',
        'opentype-variations',
        'woff',
    ];

    /**
     * Load the CSS text for a `<link rel="stylesheet" href="…">`
     * element, or null when the link doesn't apply (`rel` not
     * stylesheet, href missing, fetch fails, media query unmatched).
     * Supports `data:text/css[;base64],…` payloads and relative paths
     * resolved under `RendererOptions::baseDir` (with realpath escape
     * rejection — same posture as `<img src>` / `@font-face`).
     *
     * Honours `<link media="…">`: the same Phase-1 prelude matcher used
     * for `@media` rule cascade. Drops the sheet when `media` is
     * present and doesn't include `print` / `all`.
     */
    private function loadLinkedStylesheet(\Phpdftk\Html\Dom\Element $link): ?string
    {
        $relAttr = $link->getAttribute('rel');
        if ($relAttr === null) {
            return null;
        }
        // `rel` is a space-separated token list; pick stylesheet
        // anywhere in it. Case-insensitive per HTML 5.
        $rels = preg_split('/\s+/', strtolower(trim($relAttr))) ?: [];
        if (!in_array('stylesheet', $rels, true)) {
            return null;
        }
        $href = $link->getAttribute('href');
        if ($href === null || $href === '') {
            return null;
        }
        // CSS Media Queries 5: a `<link media="…">` filters the sheet
        // per the same media-type matcher we use for `@media` rules.
        $media = $link->getAttribute('media');
        if ($media !== null && $media !== '' && !$this->mediaPreludeMatches($media)) {
            return null;
        }
        // `data:` URLs must declare `text/css`; filesystem paths take
        // any extension. The ResourceLoader's allowlist enforces the
        // MIME check for the former.
        if (str_starts_with($href, 'data:')) {
            return $this->resourceLoader()->load($href, allowedMimes: ['text/css']);
        }
        return $this->resourceLoader()->load($href);
    }

    /**
     * Phase-1 media-type matcher mirrored from `Cascade::mediaPreludeMatches`.
     * The cascade can't be re-used because its method is private; we
     * duplicate the small predicate here. Both should stay in sync —
     * any change to the cascade matcher should reflect here too.
     */
    private function mediaPreludeMatches(string $prelude): bool
    {
        $lower = strtolower(trim($prelude));
        if ($lower === '' || $lower === 'all') {
            return true;
        }
        foreach (explode(',', $lower) as $part) {
            $tokens = preg_split('/\s+/', trim($part)) ?: [];
            foreach ($tokens as $tok) {
                if ($tok === 'print' || $tok === 'all') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Resolve a single `src` candidate to raw font bytes via the unified
     * `ResourceLoader`. Returns null when the URL can't be unwrapped
     * (not a `url(...)` or `StringValue`) or when the loader can't
     * fetch it under the current security gates. Per-source diagnostic
     * Warnings only emit for the local-file branch when the resolved
     * path read fails — data: URLs that the loader can't decode fall
     * through silently so the caller's downstream parse-attempt can
     * surface the error.
     *
     * @param list<Warning> $warnings
     */
    private function fetchFontSource(\Phpdftk\Css\Value\Value $candidate, array &$warnings): ?string
    {
        $url = null;
        if ($candidate instanceof \Phpdftk\Css\Value\Url) {
            $url = $candidate->url;
        } elseif ($candidate instanceof \Phpdftk\Css\Value\CssFunction
            && strtolower($candidate->name) === 'url'
            && isset($candidate->arguments[0])
        ) {
            $first = $candidate->arguments[0];
            if ($first instanceof \Phpdftk\Css\Value\Url) {
                $url = $first->url;
            } elseif ($first instanceof \Phpdftk\Css\Value\StringValue) {
                $url = $first->value;
            }
        }
        if ($url === null || $url === '') {
            return null;
        }
        // Fonts are binary; `data:` URLs must be base64 for binary to
        // round-trip. The ResourceLoader accepts urlencoded payloads
        // too but they're unsafe for fonts — reject explicitly.
        if (str_starts_with($url, 'data:') && stripos($url, ';base64,') === false) {
            return null;
        }
        // http(s):// — route through the optional
        // phpdftk/resource-loader. When no loader configured the
        // url is rejected with a MissingResource warning (same as
        // local-file unfound) instead of silently dropping.
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $bytes = $this->fetchHttpResource($url, $warnings, '@font-face src');
            return $bytes;
        }
        $bytes = $this->resourceLoader()->load($url);
        if ($bytes === null && !str_starts_with($url, 'data:')) {
            // Local-file branch: emit a per-source warning so authors
            // see what went wrong with a missing fixture. data: URL
            // failures fall through silently — the downstream parse
            // attempt surfaces them.
            if ($this->options->baseDir !== null) {
                $warnings[] = new Warning(
                    WarningCode::MissingResource,
                    sprintf('@font-face src `%s` could not be read.', $url),
                    WarningSeverity::Warning,
                );
            }
        }
        return $bytes;
    }

    /**
     * Resolve an `http(s)://` URL through the optional
     * `phpdftk/resource-loader` attached via
     * `RendererOptions::withResourceLoader`. Without a loader the
     * URL is rejected and a per-source warning emitted so authors
     * see the missing configuration instead of silently dropping
     * their `@font-face` / `@import` / `<img>` / etc.
     *
     * @param list<Warning> $warnings
     */
    private function fetchHttpResource(string $url, array &$warnings, string $contextLabel): ?string
    {
        $loader = $this->options->resourceLoader;
        if ($loader === null) {
            $warnings[] = new Warning(
                WarningCode::MissingResource,
                sprintf(
                    '%s `%s` requires a ResourceLoader (via RendererOptions::withResourceLoader) — http(s) hrefs drop otherwise.',
                    $contextLabel,
                    $url,
                ),
                WarningSeverity::Warning,
            );
            return null;
        }
        try {
            $result = $loader->fetch($url);
            return $result->bytes;
        } catch (\Phpdftk\ResourceLoader\Exception\SsrfBlockedException $e) {
            $warnings[] = new Warning(
                WarningCode::MissingResource,
                sprintf('%s `%s` blocked by SSRF policy: %s', $contextLabel, $url, $e->getMessage()),
                WarningSeverity::Warning,
            );
            return null;
        } catch (\Phpdftk\ResourceLoader\Exception\FetchFailedException $e) {
            $warnings[] = new Warning(
                WarningCode::MissingResource,
                sprintf('%s `%s` fetch failed: %s', $contextLabel, $url, $e->getMessage()),
                WarningSeverity::Warning,
            );
            return null;
        }
    }

    /**
     * Walk every supplied stylesheet looking for `@page` at-rules; for
     * each, extract its nested margin-box at-rules (e.g. `@top-center`)
     * and pull the `content` declaration out alongside any styling
     * declarations (`font-size`, `color`, `text-align`). The `content`
     * value is parsed into a sequence of parts (literal strings +
     * `counter(page)` / `counter(pages)` directives) so the per-page
     * paint pass can substitute the right page number at emission time.
     *
     * CSS Paged Media 3 §3.3 page selectors are partially supported at
     * Phase 1: `:first` (matches page index 0), `:left` (even-numbered
     * 0-indexed pages — index 1, 3, 5...), `:right` (odd-numbered
     * 0-indexed pages — index 0, 2, 4...). Other selectors (`:blank`,
     * `:nth(...)`, named pages) ignored. Multiple `@page` rules with
     * different selectors stack — `resolvePageMarginBoxes` overlays
     * them per-page at paint time.
     *
     * @param list<\Phpdftk\Css\Sheet\Stylesheet> $sheets
     * @return array<string, array<string, array{
     *     parts: list<array{kind: string, value: string}>,
     *     fontSize: float,
     *     color: \Phpdftk\Css\Value\Color,
     *     textAlign: ?string,
     *     fontFamily: ?\Phpdftk\Css\Value\Value,
     *     fontWeight: int,
     *     fontStyle: string,
     * }>> selector → position → spec
     */
    private function collectPageMarginBoxes(array $sheets): array
    {
        $supported = [
            'top-left-corner', 'top-left', 'top-center', 'top-right', 'top-right-corner',
            'bottom-left-corner', 'bottom-left', 'bottom-center', 'bottom-right', 'bottom-right-corner',
        ];
        $out = [];
        foreach ($sheets as $sheet) {
            foreach ($sheet->rules as $rule) {
                if (!$rule instanceof \Phpdftk\Css\Sheet\AtRule
                    || strtolower($rule->name) !== 'page'
                    || $rule->block === null
                ) {
                    continue;
                }
                $selector = $this->normalisePageSelector($rule->prelude);
                if ($selector === null) {
                    continue;
                }
                if (!isset($out[$selector])) {
                    $out[$selector] = [];
                }
                // CSS Paged Media 3 §3 + Generated Content 3 §2.1: the
                // `@page` rule's own typography declarations cascade
                // INTO its nested margin boxes. Read them first so each
                // box's spec starts at those defaults instead of the
                // hard-coded 10pt black; the margin box's own
                // declarations still win per source-order.
                $pageDefaults = [
                    'fontSize' => 10.0,
                    'color' => new \Phpdftk\Css\Value\Color(0.0, 0.0, 0.0, 1.0),
                    'textAlign' => null,
                    'fontFamily' => null,
                    'fontWeight' => 400,
                    'fontStyle' => 'normal',
                ];
                foreach ($rule->block->contents as $pageDecl) {
                    if (!$pageDecl instanceof \Phpdftk\Css\Sheet\Declaration) {
                        continue;
                    }
                    switch ($pageDecl->property) {
                        case 'font-size':
                            if ($pageDecl->value instanceof \Phpdftk\Css\Value\Length) {
                                $pageDefaults['fontSize'] = max(1.0, $pageDecl->value->value);
                            }
                            break;
                        case 'color':
                            if ($pageDecl->value instanceof \Phpdftk\Css\Value\Color) {
                                $pageDefaults['color'] = $pageDecl->value;
                            }
                            break;
                        case 'font-family':
                            $pageDefaults['fontFamily'] = $pageDecl->value;
                            break;
                        case 'font-weight':
                            $pageDefaults['fontWeight'] = $this->parseFontWeight($pageDecl->value);
                            break;
                        case 'font-style':
                            $pageDefaults['fontStyle'] = $this->parseFontStyle($pageDecl->value);
                            break;
                    }
                }
                foreach ($rule->block->contents as $item) {
                    if (!$item instanceof \Phpdftk\Css\Sheet\AtRule
                        || $item->block === null
                    ) {
                        continue;
                    }
                    $pos = strtolower($item->name);
                    if (!in_array($pos, $supported, true)) {
                        continue;
                    }
                    $parts = null;
                    $fontSize = $pageDefaults['fontSize'];
                    $color = $pageDefaults['color'];
                    $textAlign = $pageDefaults['textAlign'];
                    $fontFamily = $pageDefaults['fontFamily'];
                    $fontWeight = $pageDefaults['fontWeight'];
                    $fontStyle = $pageDefaults['fontStyle'];
                    foreach ($item->block->contents as $decl) {
                        if (!$decl instanceof \Phpdftk\Css\Sheet\Declaration) {
                            continue;
                        }
                        switch ($decl->property) {
                            case 'content':
                                $parts = $this->parseContentValue($decl->value);
                                break;
                            case 'font-size':
                                if ($decl->value instanceof \Phpdftk\Css\Value\Length) {
                                    $fontSize = max(1.0, $decl->value->value);
                                }
                                break;
                            case 'color':
                                if ($decl->value instanceof \Phpdftk\Css\Value\Color) {
                                    $color = $decl->value;
                                }
                                break;
                            case 'text-align':
                                if ($decl->value instanceof \Phpdftk\Css\Value\Keyword) {
                                    $kw = strtolower($decl->value->name);
                                    if (in_array($kw, ['left', 'right', 'center', 'start', 'end'], true)) {
                                        $textAlign = $kw === 'start' ? 'left'
                                            : ($kw === 'end' ? 'right' : $kw);
                                    }
                                }
                                break;
                            case 'font-family':
                                $fontFamily = $decl->value;
                                break;
                            case 'font-weight':
                                $fontWeight = $this->parseFontWeight($decl->value);
                                break;
                            case 'font-style':
                                $fontStyle = $this->parseFontStyle($decl->value);
                                break;
                        }
                    }
                    if ($parts !== null && $parts !== []) {
                        $out[$selector][$pos] = [
                            'parts' => $parts,
                            'fontSize' => $fontSize,
                            'color' => $color,
                            'textAlign' => $textAlign,
                            'fontFamily' => $fontFamily,
                            'fontWeight' => $fontWeight,
                            'fontStyle' => $fontStyle,
                        ];
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Reduce a `@page <prelude>` selector text to one of the supported
     * keys: `''` (unscoped / default), `:first`, `:left`, `:right`.
     * Returns null for unsupported selectors (`:blank`, named pages, etc.)
     * so the caller drops the rule entirely rather than mis-applying it.
     */
    /**
     * Resolve the effective page width / height from CSS Paged Media 3
     * §6.1 `@page { size: ... }` declarations. Falls back to the
     * `RendererOptions` defaults when no `size` is declared or the
     * declared value isn't recognised. Multiple `@page` rules merge —
     * the last `size` declaration wins per source-order.
     *
     * Supported forms:
     *   - `auto` — use defaults
     *   - `<length>{1,2}` — width [height]; one length sets a square
     *   - `<page-size>` — A3/A4/A5/B4/B5/JIS-B4/JIS-B5/letter/legal/ledger
     *   - `<page-size> <orientation>` or `<orientation> <page-size>`
     *   - `<orientation>` alone (rotates the default size)
     *
     * @param list<\Phpdftk\Css\Sheet\Stylesheet> $sheets
     * @return array{width: float, height: float}
     */
    private function resolvePageSize(array $sheets): array
    {
        $width = $this->options->pageWidth;
        $height = $this->options->pageHeight;
        foreach ($sheets as $sheet) {
            foreach ($sheet->rules as $rule) {
                if (!$rule instanceof \Phpdftk\Css\Sheet\AtRule
                    || strtolower($rule->name) !== 'page'
                    || $rule->block === null
                ) {
                    continue;
                }
                foreach ($rule->block->contents as $decl) {
                    if (!$decl instanceof \Phpdftk\Css\Sheet\Declaration
                        || $decl->property !== 'size'
                    ) {
                        continue;
                    }
                    $resolved = $this->parsePageSize($decl->value);
                    if ($resolved !== null) {
                        [$width, $height] = $resolved;
                    }
                }
            }
        }
        return ['width' => $width, 'height' => $height];
    }

    /**
     * Resolve the effective page background color from every `@page`
     * rule's `background-color` (and the `background` shorthand's color
     * component) declarations. Returns null when no @page rule sets a
     * page-level background.
     *
     * Phase-1 simplification: colour only. `background-image`,
     * `background-repeat`, etc. lands later alongside the body-level
     * background-image painter once a shared image-paint path is in
     * place.
     *
     * `$pageName` (optional) selects an `@page <name>` overlay on top
     * of the default unnamed rule. CSS Paged Media 3 §3.4: when the
     * page being painted is tagged with a name, the named rule wins
     * for any property it sets.
     *
     * @param list<\Phpdftk\Css\Sheet\Stylesheet> $sheets
     */
    private function resolvePageBackground(array $sheets, ?string $pageName = null): ?\Phpdftk\Css\Value\Color
    {
        $expander = new \Phpdftk\Css\Cascade\ShorthandExpander();
        $color = null;
        foreach ($sheets as $sheet) {
            foreach ($sheet->rules as $rule) {
                if (!$rule instanceof \Phpdftk\Css\Sheet\AtRule
                    || strtolower($rule->name) !== 'page'
                    || $rule->block === null
                ) {
                    continue;
                }
                $sel = $this->normalisePageSelector($rule->prelude);
                if (!$this->pageSelectorAppliesTo($sel, $pageName)) {
                    continue;
                }
                foreach ($rule->block->contents as $decl) {
                    if (!$decl instanceof \Phpdftk\Css\Sheet\Declaration) {
                        continue;
                    }
                    if ($decl->property === 'background-color'
                        && $decl->value instanceof \Phpdftk\Css\Value\Color
                    ) {
                        $color = $decl->value;
                    } elseif ($decl->property === 'background') {
                        $expanded = $expander->expand('background', $decl->value);
                        $bg = $expanded['background-color'] ?? null;
                        if ($bg instanceof \Phpdftk\Css\Value\Color) {
                            $color = $bg;
                        }
                    }
                }
            }
        }
        return $color;
    }

    /**
     * `true` when an `@page` rule with the given normalised selector
     * applies to a page tagged `$pageName`. Default (no selector)
     * always applies; named selectors apply only when their name
     * matches the page tag.
     */
    private function pageSelectorAppliesTo(?string $selector, ?string $pageName): bool
    {
        if ($selector === null) {
            return false;
        }
        if ($selector === '' || $selector === ':first' || $selector === ':left' || $selector === ':right') {
            // Phase-1: ignore parity / first overlays here. The
            // resolvePageMarginBoxes pipeline still honours them
            // separately via its own selector overlay.
            return $selector === '';
        }
        if (str_starts_with($selector, 'name:')) {
            return $pageName !== null && substr($selector, 5) === $pageName;
        }
        return false;
    }

    /**
     * Walk the laid-out box tree once, building a per-page-index map of
     * the named page type that applies. A block with `page: foo`
     * tags the page containing its top edge as "foo" (CSS Paged Media
     * 3 §3.4 — the first fragment determines the page type).
     *
     * @return array<int, string>
     */
    private function resolvePageNames(\Phpdftk\HtmlToPdf\Box\Box $root, float $pageHeight, int $pageCount): array
    {
        $map = [];
        if ($pageHeight <= 0.0) {
            return $map;
        }
        $stack = [$root];
        while ($stack !== []) {
            $node = array_pop($stack);
            $value = $node->style->get('page');
            if ($value instanceof \Phpdftk\Css\Value\Keyword
                && strtolower($value->name) !== 'auto'
            ) {
                $pageIndex = (int) floor($node->geometry->y / $pageHeight);
                if ($pageIndex >= 0 && $pageIndex < $pageCount && !isset($map[$pageIndex])) {
                    $map[$pageIndex] = strtolower($value->name);
                }
            }
            // Push children in reverse order so document-order walk
            // processes the first child first.
            for ($i = count($node->children) - 1; $i >= 0; $i--) {
                $stack[] = $node->children[$i];
            }
        }
        return $map;
    }

    /**
     * Resolve effective page margins (in PDF points) from every `@page`
     * rule's margin declarations. Honours the `margin` shorthand (1-4
     * components per CSS Box 3) and the per-side longhands
     * (`margin-top` / -right / -bottom / -left); later declarations win
     * per source order. Defaults to 36pt all sides — the same fixed
     * margin the painter used before CSS-driven control landed.
     *
     * @param list<\Phpdftk\Css\Sheet\Stylesheet> $sheets
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    private function resolvePageMargins(array $sheets): array
    {
        $expander = new \Phpdftk\Css\Cascade\ShorthandExpander();
        $margins = ['top' => 36.0, 'right' => 36.0, 'bottom' => 36.0, 'left' => 36.0];
        foreach ($sheets as $sheet) {
            foreach ($sheet->rules as $rule) {
                if (!$rule instanceof \Phpdftk\Css\Sheet\AtRule
                    || strtolower($rule->name) !== 'page'
                    || $rule->block === null
                ) {
                    continue;
                }
                foreach ($rule->block->contents as $decl) {
                    if (!$decl instanceof \Phpdftk\Css\Sheet\Declaration) {
                        continue;
                    }
                    $prop = $decl->property;
                    if ($prop === 'margin') {
                        $expanded = $expander->expand('margin', $decl->value);
                        foreach (['top', 'right', 'bottom', 'left'] as $side) {
                            $sideValue = $expanded['margin-' . $side] ?? null;
                            if ($sideValue instanceof \Phpdftk\Css\Value\Length) {
                                $margins[$side] = $sideValue->value;
                            }
                        }
                    } elseif (in_array($prop, ['margin-top', 'margin-right', 'margin-bottom', 'margin-left'], true)) {
                        if ($decl->value instanceof \Phpdftk\Css\Value\Length) {
                            $margins[substr($prop, 7)] = $decl->value->value;
                        }
                    }
                }
            }
        }
        return $margins;
    }

    /**
     * Parse a single `@page { size }` value into `[width, height]` in
     * PDF points, or null when the value can't be resolved.
     *
     * @return array{0: float, 1: float}|null
     */
    private function parsePageSize(\Phpdftk\Css\Value\Value $value): ?array
    {
        // Standard ISO + US page sizes in PDF points (1 inch = 72 pt).
        // Matches the CSS Paged Media 3 §6.1 named-size table.
        $named = [
            'a3' => [842.0, 1191.0],
            'a4' => [595.0, 842.0],
            'a5' => [420.0, 595.0],
            'b4' => [729.0, 1032.0],
            'b5' => [516.0, 729.0],
            'jis-b4' => [729.0, 1032.0],
            'jis-b5' => [516.0, 729.0],
            'letter' => [612.0, 792.0],
            'legal' => [612.0, 1008.0],
            'ledger' => [792.0, 1224.0],
        ];
        $items = $value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Space
                ? $value->values
                : [$value];
        // Single `auto` keyword → use defaults.
        if (count($items) === 1
            && $items[0] instanceof \Phpdftk\Css\Value\Keyword
            && strtolower($items[0]->name) === 'auto'
        ) {
            return null;
        }
        // Single length → square. Two lengths → width + height.
        $lengths = array_values(array_filter(
            $items,
            static fn($v) => $v instanceof \Phpdftk\Css\Value\Length,
        ));
        if (count($lengths) === 1) {
            return [$lengths[0]->value, $lengths[0]->value];
        }
        if (count($lengths) === 2) {
            return [$lengths[0]->value, $lengths[1]->value];
        }
        // Otherwise scan keywords: `<page-size>` + optional orientation.
        $size = null;
        $orientation = null;
        foreach ($items as $item) {
            if (!$item instanceof \Phpdftk\Css\Value\Keyword) {
                continue;
            }
            $kw = strtolower($item->name);
            if (isset($named[$kw])) {
                $size = $named[$kw];
            } elseif ($kw === 'landscape' || $kw === 'portrait') {
                $orientation = $kw;
            }
        }
        if ($size === null) {
            // Orientation alone: rotate the default size.
            if ($orientation !== null) {
                $defaultPortrait = [$this->options->pageWidth, $this->options->pageHeight];
                if ($defaultPortrait[0] > $defaultPortrait[1]) {
                    [$defaultPortrait[0], $defaultPortrait[1]] = [$defaultPortrait[1], $defaultPortrait[0]];
                }
                return $orientation === 'landscape'
                    ? [$defaultPortrait[1], $defaultPortrait[0]]
                    : $defaultPortrait;
            }
            return null;
        }
        if ($orientation === 'landscape' && $size[0] < $size[1]) {
            return [$size[1], $size[0]];
        }
        if ($orientation === 'portrait' && $size[0] > $size[1]) {
            return [$size[1], $size[0]];
        }
        return $size;
    }

    /**
     * Parse a CSS `font-weight` value to the CSS Fonts 4 1–1000 range.
     * Keywords map per spec: `normal` → 400, `bold` / `bolder` → 700,
     * `lighter` → 100. Anything unrecognised falls back to 400.
     */
    private function parseFontWeight(\Phpdftk\Css\Value\Value $value): int
    {
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            return match (strtolower($value->name)) {
                'bold', 'bolder' => 700,
                'lighter' => 100,
                default => 400,
            };
        }
        if ($value instanceof \Phpdftk\Css\Value\Integer
            || $value instanceof \Phpdftk\Css\Value\Number
        ) {
            return max(1, min(1000, (int) $value->value));
        }
        return 400;
    }

    /**
     * Parse a CSS `font-style` value to one of `normal`, `italic`, or
     * `oblique`. Unknown values fall back to `normal`.
     */
    private function parseFontStyle(\Phpdftk\Css\Value\Value $value): string
    {
        if ($value instanceof \Phpdftk\Css\Value\Keyword) {
            $lc = strtolower($value->name);
            if (in_array($lc, ['italic', 'oblique'], true)) {
                return $lc;
            }
        }
        return 'normal';
    }

    private function normalisePageSelector(string $prelude): ?string
    {
        $lc = strtolower(trim($prelude));
        if ($lc === '') {
            return '';
        }
        if (in_array($lc, [':first', ':left', ':right'], true)) {
            return $lc;
        }
        // CSS Paged Media 3 §3.4: `@page <ident>` names a page type.
        // The prelude is an identifier (possibly followed by a
        // pseudo-class — Phase 1 ignores combined `<ident>:first`
        // forms and just keys on the bare name).
        if (preg_match('/^([a-z_][a-z0-9_-]*)$/', $lc, $m) === 1) {
            return 'name:' . $m[1];
        }
        return null;
    }

    /**
     * Build the per-position margin-box map for a specific page index by
     * overlaying selector-scoped rules in CSS Paged Media 3 §3.3
     * specificity order: default (no selector) is the base, then
     * `:left` / `:right` (one applies per page), then `:first` (only
     * page 0). Position-keyed overlay so a `:first { @top-center }`
     * override preserves the default `@bottom-center` rule.
     *
     * @param array<string, array<string, array{
     *     parts: list<array{kind: string, value: string}>,
     *     fontSize: float,
     *     color: \Phpdftk\Css\Value\Color,
     *     textAlign: ?string,
     *     fontFamily: ?\Phpdftk\Css\Value\Value,
     *     fontWeight: int,
     *     fontStyle: string,
     * }>> $marginBoxes
     * @return array<string, array{
     *     parts: list<array{kind: string, value: string}>,
     *     fontSize: float,
     *     color: \Phpdftk\Css\Value\Color,
     *     textAlign: ?string,
     *     fontFamily: ?\Phpdftk\Css\Value\Value,
     *     fontWeight: int,
     *     fontStyle: string,
     * }>
     */
    private function resolvePageMarginBoxes(array $marginBoxes, int $pageIndex, ?string $pageName = null): array
    {
        $resolved = $marginBoxes[''] ?? [];
        // Even-numbered (0-indexed) pages are right-facing per the CSS
        // Paged Media 3 default ("the first page of a document begins on
        // a right page"). Odd-indexed are left-facing.
        $sideSelector = $pageIndex % 2 === 0 ? ':right' : ':left';
        if (isset($marginBoxes[$sideSelector])) {
            $resolved = array_merge($resolved, $marginBoxes[$sideSelector]);
        }
        if ($pageIndex === 0 && isset($marginBoxes[':first'])) {
            $resolved = array_merge($resolved, $marginBoxes[':first']);
        }
        // CSS Paged Media 3 §3.4: named selectors overlay on top of
        // the parity/first selectors when the page is tagged with a
        // matching name.
        if ($pageName !== null && isset($marginBoxes['name:' . $pageName])) {
            $resolved = array_merge($resolved, $marginBoxes['name:' . $pageName]);
        }
        return $resolved;
    }

    /**
     * Parse a CSS `content` value (StringValue, counter() CssFunction,
     * or a space-separated ValueList mixing both) into a list of parts
     * the paint pass can resolve per page. Returns an empty list when
     * the value contains nothing renderable.
     *
     * `counter(name [, style])` arguments come back parsed by the CSS
     * value parser as a comma-separated `ValueList` inside the function
     * call. We honour the second positional `<counter-style>` keyword
     * argument (`decimal`, `lower-roman`, `upper-alpha`, etc. per CSS
     * Counter Styles 3 §6) by stashing the style with the counter part
     * so the paint pass can format the numeric value through it.
     *
     * @return list<array{kind: string, value: string, style?: string}>
     */
    private function parseContentValue(\Phpdftk\Css\Value\Value $value): array
    {
        $parts = [];
        $items = $value instanceof \Phpdftk\Css\Value\ValueList
            && $value->separator === \Phpdftk\Css\Value\ListSeparator::Space
                ? $value->values
                : [$value];
        foreach ($items as $item) {
            if ($item instanceof \Phpdftk\Css\Value\StringValue) {
                $parts[] = ['kind' => 'literal', 'value' => $item->value];
            } elseif ($item instanceof \Phpdftk\Css\Value\StringFunction) {
                // GCPM 3 §5.2 — resolved at paint time against the
                // box generator's named-string store.
                $parts[] = [
                    'kind' => 'namedstring',
                    'value' => '',
                    'name' => $item->name,
                    'target' => $item->target,
                ];
            } elseif ($item instanceof \Phpdftk\Css\Value\ElementFunction) {
                // GCPM 3 §4.2 — resolved at paint time against the
                // box generator's running-element store. The store
                // currently captures element textContent; full
                // fragment rendering is a future deliverable.
                $parts[] = [
                    'kind' => 'runningelement',
                    'value' => '',
                    'name' => $item->name,
                    'target' => $item->target,
                ];
            } elseif ($item instanceof \Phpdftk\Css\Value\CssFunction
                && strtolower($item->name) === 'counter'
                && $item->arguments !== []
            ) {
                $args = $this->splitCounterArgs($item->arguments);
                $first = $args[0] ?? null;
                $name = $first instanceof \Phpdftk\Css\Value\Keyword
                    ? strtolower($first->name)
                    : null;
                if ($name !== 'page' && $name !== 'pages') {
                    continue;
                }
                $style = 'decimal';
                $second = $args[1] ?? null;
                if ($second instanceof \Phpdftk\Css\Value\Keyword) {
                    $style = strtolower($second->name);
                }
                $parts[] = [
                    'kind' => $name === 'pages' ? 'totalpages' : 'pagenumber',
                    'value' => '',
                    'style' => $style,
                ];
            }
        }
        return $parts;
    }

    /**
     * `counter(page, lower-roman)` parses into a CssFunction whose single
     * `arguments[0]` is a comma-separated `ValueList` of the actual
     * positional arguments. Split it back into a flat list so the caller
     * can index by position. Tolerant of the single-arg case (no comma).
     *
     * @param list<\Phpdftk\Css\Value\Value> $arguments
     * @return list<\Phpdftk\Css\Value\Value>
     */
    private function splitCounterArgs(array $arguments): array
    {
        if (count($arguments) !== 1) {
            return $arguments;
        }
        $head = $arguments[0];
        if ($head instanceof \Phpdftk\Css\Value\ValueList
            && $head->separator === \Phpdftk\Css\Value\ListSeparator::Comma
        ) {
            return $head->values;
        }
        return $arguments;
    }

    /**
     * Paint the collected `@page` margin boxes on the current page.
     * Phase-1 positioning: a fixed 36pt (0.5") page margin band; text
     * baseline sits halfway through the margin. Each position picks an
     * anchor point and a horizontal alignment:
     *   - top-left / bottom-left → left-aligned at the margin
     *   - top-center / bottom-center → centred on the page width
     *   - top-right / bottom-right → right-aligned at the margin
     * Uses the document's default font at 10pt. Author-driven sizing /
     * styling lands when we cascade margin-box rules into a proper
     * mini-layout (follow-up).
     *
     * @param array<string, array{
     *     parts: list<array{kind: string, value: string, style?: string, name?: string, target?: string}>,
     *     fontSize: float,
     *     color: \Phpdftk\Css\Value\Color,
     *     textAlign: ?string,
     *     fontFamily: ?\Phpdftk\Css\Value\Value,
     *     fontWeight: int,
     *     fontStyle: string,
     * }> $boxes
     * @param array<string, \Phpdftk\Pdf\Core\Font\RegisteredFont> $registeredMap
     * @param array<string, string> $namedStrings  GCPM 3 §5 named-string
     *     store accumulated during box generation; resolves
     *     `content: string(name)` parts in page margin boxes.
     * @param array<string, string> $runningElements  GCPM 3 §4 running-
     *     element store accumulated during box generation;
     *     resolves `content: element(name)` parts.
     */
    private function paintPageMarginBoxes(
        \Phpdftk\Pdf\Core\Content\ContentStream $stream,
        array $boxes,
        float $pageWidth,
        float $pageHeight,
        \Phpdftk\FontParser\OpenTypeData $font,
        \Phpdftk\Pdf\Core\Font\RegisteredFont $registered,
        int $pageIndex,
        int $pageCount,
        ?\Phpdftk\HtmlToPdf\Layout\FontResolver $fontResolver = null,
        array $registeredMap = [],
        float $marginTop = 36.0,
        float $marginRight = 36.0,
        float $marginBottom = 36.0,
        float $marginLeft = 36.0,
        array $namedStrings = [],
        array $runningElements = [],
    ): void {
        $shaper = new \Phpdftk\Text\Shaper();
        foreach ($boxes as $position => $spec) {
            // Resolve the per-page-variable parts. `counter(page)` becomes
            // the 1-based page number, `counter(pages)` the total, both
            // formatted through the optional `<counter-style>` argument
            // (`decimal` / `lower-roman` / `upper-alpha` / ...).
            $text = '';
            foreach ($spec['parts'] as $part) {
                if ($part['kind'] === 'pagenumber') {
                    $text .= \Phpdftk\HtmlToPdf\Layout\CounterFormat::format(
                        $pageIndex + 1,
                        $part['style'] ?? 'decimal',
                    );
                } elseif ($part['kind'] === 'totalpages') {
                    $text .= \Phpdftk\HtmlToPdf\Layout\CounterFormat::format(
                        $pageCount,
                        $part['style'] ?? 'decimal',
                    );
                } elseif ($part['kind'] === 'namedstring') {
                    $text .= $namedStrings[$part['name']] ?? '';
                } elseif ($part['kind'] === 'runningelement') {
                    $text .= $runningElements[$part['name']] ?? '';
                } else {
                    $text .= $part['value'];
                }
            }
            if ($text === '') {
                continue;
            }
            // Resolve a per-position `font-family` (+ weight + style)
            // through the same FontResolver the body uses. When a real
            // bold/italic face matches, the painter skips the synthetic
            // fake-bold / fake-italic fallbacks; otherwise the
            // FontMatch's match flags drive whether those fire.
            $faceFont = $font;
            $faceRegistered = $registered;
            $needsFakeBold = $spec['fontWeight'] >= 600;
            $needsFakeItalic = $spec['fontStyle'] !== 'normal';
            if ($spec['fontFamily'] !== null && $fontResolver !== null) {
                $match = $fontResolver->resolveMatch(
                    $spec['fontFamily'],
                    $spec['fontWeight'],
                    $spec['fontStyle'],
                );
                if ($match !== null
                    && isset($registeredMap[$match->face->data->postScriptName])
                ) {
                    $faceFont = $match->face->data;
                    $faceRegistered = $registeredMap[$match->face->data->postScriptName];
                    if ($match->matchesWeight) {
                        $needsFakeBold = false;
                    }
                    if ($match->matchesStyle) {
                        $needsFakeItalic = false;
                    }
                }
            }
            $shapingCtx = new \Phpdftk\Text\ShapingContext($faceFont, $spec['fontSize']);
            $shaped = $shaper->shapeRun($text, $shapingCtx);
            if ($shaped->glyphs === []) {
                continue;
            }
            $width = $shaped->totalAdvance;
            // Y bands: top boxes sit centred in the top margin
            // (pageHeight - marginTop / 2); bottom boxes centred in the
            // bottom margin (marginBottom / 2).
            $yPdf = match (true) {
                str_starts_with($position, 'top-') => $pageHeight - $marginTop / 2,
                default => $marginBottom / 2,
            };
            // Corner boxes sit in their respective margin corner area;
            // default alignment centres the text inside that area.
            // Author `text-align` still overrides.
            $isCorner = str_ends_with($position, '-corner');
            $alignment = $spec['textAlign'] ?? match (true) {
                $isCorner => 'center',
                str_ends_with($position, '-left') => 'left',
                str_ends_with($position, '-right') => 'right',
                default => 'center',
            };
            $xPdf = match (true) {
                $isCorner && str_contains($position, '-left-') => max(0.0, ($marginLeft - $width) / 2),
                $isCorner && str_contains($position, '-right-')
                    => $pageWidth - $marginRight + max(0.0, ($marginRight - $width) / 2),
                $alignment === 'left' => $marginLeft,
                $alignment === 'right' => $pageWidth - $marginRight - $width,
                default => ($pageWidth - $width) / 2,
            };
            $stream->saveGraphicsState();
            $stream->setFillColorRGB($spec['color']->r, $spec['color']->g, $spec['color']->b);
            $stream->setFont($faceRegistered, $spec['fontSize']);
            $stream->beginText();
            // Fake-italic via a 12° skew in the Tm `c` slot when no real
            // italic face matched — same trick used in the body painter.
            $skew = $needsFakeItalic ? 0.213 : 0.0;
            $stream->setTextMatrix(1, 0, $skew, 1, $xPdf, $yPdf);
            if ($needsFakeBold) {
                $stream->setStrokeColorRGB(
                    $spec['color']->r,
                    $spec['color']->g,
                    $spec['color']->b,
                );
                $stream->setLineWidth($spec['fontSize'] * 0.04);
                $stream->setTextRenderingMode(2);
            } else {
                $stream->setTextRenderingMode(0);
            }
            $gidMap = $faceRegistered instanceof \Phpdftk\Pdf\Writer\Font
                ? $faceRegistered->getOldToNewGidMap()
                : [];
            $hexParts = [];
            foreach ($shaped->glyphs as $glyph) {
                $newGid = $gidMap[$glyph->glyphId] ?? $glyph->glyphId;
                $hexParts[] = sprintf('%04X', $newGid);
            }
            $stream->showTextHex(implode('', $hexParts));
            $stream->endText();
            $stream->restoreGraphicsState();
        }
    }

    /** @param list<Warning> $warnings */
    private function maybeThrow(array $warnings): void
    {
        if (!$this->options->strict) {
            return;
        }
        foreach ($warnings as $w) {
            if ($w->severity === WarningSeverity::Error) {
                throw new StrictModeException($w);
            }
        }
    }
}
