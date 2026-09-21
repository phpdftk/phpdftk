<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness;

/**
 * End-to-end WPT test runner — walks the corpus under `$wptRoot`,
 * classifies each test via {@see Manifest}, renders the in-scope
 * tests through `phpdftk/html-to-pdf` (or `phpdftk/svg-to-pdf` for
 * SVG tests), rasterises via {@see Rasteriser}, diffs via
 * {@see Scorer}, and emits a per-test {@see TestResult} ledger.
 *
 * Phase 4A.1 implements the walker + classification path. The
 * actual rendering / rasterisation / scoring lands in 4A.2 + 4A.3;
 * until those ship, in-scope tests are marked `Skipped` with a
 * reason explaining the missing substrate so the dashboard can
 * communicate progress accurately.
 *
 * Test discovery: recursive glob for HTML / XHT / SVG files under
 * `$wptRoot`. Files matching `*-ref.*` are treated as reference
 * renderings (skipped — they're the expected output for some
 * other test, not a test themselves).
 */
final class HarnessRunner
{
    /** @var list<string> File extensions recognised as test files. */
    private const TEST_EXTENSIONS = ['html', 'xht', 'xhtml', 'htm', 'svg'];

    /**
     * How much of a fixture to scan for `<link rel=match>` and
     * `<meta name=fuzzy>` declarations. WPT parses the whole
     * document; a bounded read keeps a pathological fixture from
     * stalling the harness. Verified against the full corpus: no
     * fixture declares either past this offset.
     */
    private const MARKUP_SCAN_BYTES = 64 * 1024;

    public function __construct(
        private readonly Manifest $manifest,
        private readonly Rasteriser $rasteriser,
        private readonly Scorer $scorer,
        private readonly string $wptRoot,
        /**
         * Optional DOM settler. When present, fixtures carrying
         * `class="reftest-wait"` are shelled through Playwright to
         * settle their JavaScript before the PHP renderer sees them.
         * Null skips settling entirely (the legacy behaviour) and
         * lets tests with unrun setup-JS reflect their pre-settled
         * state, matching how the harness behaved before this hook
         * was added.
         */
        private readonly ?DomSettler $domSettler = null,
    ) {}

    /**
     * Bundled UA default font for text emission. Browsers render unstyled
     * text in their default serif (Times), so the harness ships a real
     * serif (DejaVu Serif) as the default `font-family` fallback — without
     * it, text has zero advance/height and text-dependent reftests pass
     * BLANK (test and reference both empty), silently hiding real
     * text-layout gaps. Test AND reference render with the same font, so
     * the comparison stays self-consistent; refs that hard-code Times pixel
     * metrics use Ahem (loaded from the corpus) and are unaffected.
     *
     * Override the file via the `WPT_DEFAULT_FONT` env var (a .ttf/.otf/
     * .woff path); set it to `none` to run font-less (the legacy mode).
     * `false` = not yet resolved; `null` = resolved to "no font".
     */
    private \Phpdftk\FontParser\FontFaceData|null|false $harnessDefaultFont = false;

    private function harnessDefaultFont(): ?\Phpdftk\FontParser\FontFaceData
    {
        if ($this->harnessDefaultFont !== false) {
            return $this->harnessDefaultFont;
        }
        $env = getenv('WPT_DEFAULT_FONT');
        if ($env === 'none') {
            return $this->harnessDefaultFont = null;
        }
        $path = ($env !== false && $env !== '')
            ? $env
            : __DIR__ . '/../resources/fonts/DejaVuSerif.ttf';
        if (!is_file($path)) {
            return $this->harnessDefaultFont = null;
        }
        // Dispatch by container: .otf (CFF) / .woff / .woff2 have dedicated
        // parsers; .ttf (TrueType glyf) is the default.
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        try {
            return $this->harnessDefaultFont = match ($ext) {
                'otf' => (new \Phpdftk\FontParser\OpenTypeParser($path))->parse(),
                'woff' => (new \Phpdftk\FontParser\WoffParser($path))->parse(),
                default => (new \Phpdftk\FontParser\TrueTypeParser($path))->parse(),
            };
        } catch (\Throwable) {
            return $this->harnessDefaultFont = null;
        }
    }

    /**
     * Run the harness corpus. Returns one {@see TestResult} per
     * test that was either rendered or classified.
     *
     * `$filter` accepts the same glob syntax as the manifest rule
     * files (`*` matches within a segment, `**` matches across) —
     * tests whose ID doesn't match are excluded from the run.
     *
     * @return list<TestResult>
     */
    public function run(?string $filter = null): array
    {
        if (!is_dir($this->wptRoot)) {
            return [];
        }

        $results = [];
        foreach ($this->discoverTests($this->wptRoot) as $absolutePath) {
            $testId = $this->testIdFromPath($absolutePath);
            if ($testId === null) {
                continue;
            }
            if ($filter !== null && !Manifest::matches($filter, $testId)) {
                continue;
            }
            $results[] = $this->runOne($testId);
        }
        return $results;
    }

    /**
     * Classify a single test ID without scanning the filesystem.
     * Useful for `composer wpt classify <id>` and for tests of
     * this class.
     */
    public function runOne(string $testId): TestResult
    {
        $verdict = $this->manifest->classify($testId);
        if ($verdict !== null) {
            // Out-of-scope or pending-substrate — no render needed.
            return new TestResult(
                testId: $testId,
                status: $verdict['status'],
                diffScore: 0.0,
                reason: $verdict['reason'],
                diffArtefactPath: null,
                renderMicros: 0.0,
            );
        }

        // In-scope: render the test through phpdftk, rasterise via
        // Ghostscript, visually-diff against the WPT reference.
        return $this->runRendered($testId);
    }

    /**
     * Debug aid — write the visual-diff artefacts (the rendered PNG, the
     * reference PNG, and the ImageMagick diff PNG) for every in-scope test
     * matching `$filter`, up to `$limit`, into `$outDir`. Reuses the exact
     * production render → rasterise → diff pipeline (same `Renderer`
     * options, same `Rasteriser`, same `Scorer`) so the artefacts match
     * what `run` scores — no divergence from a hand-rolled renderer.
     *
     * @return list<array{testId: string, score: float, passed: bool, rendered: ?string, reference: ?string, diff: ?string, reason: ?string}>
     */
    public function renderDiffArtefacts(?string $filter, string $outDir, int $limit = 20): array
    {
        if (!is_dir($outDir) && !@mkdir($outDir, 0o777, true) && !is_dir($outDir)) {
            throw new \RuntimeException("cannot create artefact dir: $outDir");
        }
        $rows = [];
        foreach ($this->discoverTests($this->wptRoot) as $absolutePath) {
            if (count($rows) >= $limit) {
                break;
            }
            $testId = $this->testIdFromPath($absolutePath);
            if ($testId === null) {
                continue;
            }
            if ($filter !== null && !Manifest::matches($filter, $testId)) {
                continue;
            }
            if ($this->manifest->classify($testId) !== null) {
                // Out-of-scope / pending-substrate — nothing to render.
                continue;
            }
            $rows[] = $this->renderOneArtefact($testId, $outDir);
        }

        return $rows;
    }

    /**
     * @return array{testId: string, score: float, passed: bool, rendered: ?string, reference: ?string, diff: ?string, reason: ?string}
     */
    private function renderOneArtefact(string $testId, string $outDir): array
    {
        $row = [
            'testId' => $testId,
            'score' => 1.0,
            'passed' => false,
            'rendered' => null,
            'reference' => null,
            'diff' => null,
            'reason' => null,
        ];
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $testId) ?? $testId;
        $base = rtrim($outDir, '/') . '/' . $safe;

        $rootAbs = realpath($this->wptRoot);
        $testPath = $rootAbs !== false ? $this->resolveTestFile($rootAbs, $testId) : null;
        if ($testPath === null) {
            $row['reason'] = 'test file not found';

            return $row;
        }
        $refPath = $this->locateReference($testPath);
        if ($refPath === null) {
            $row['reason'] = 'no -ref sibling / rel=match reference';

            return $row;
        }
        try {
            $renderedPng = $this->renderToPng($testPath);
        } catch (\Throwable $e) {
            $row['reason'] = 'render failed: ' . $e->getMessage();

            return $row;
        }
        try {
            $refPng = str_ends_with(strtolower($refPath), '.png')
                ? $refPath
                : $this->renderToPng($refPath);
        } catch (\Throwable $e) {
            @unlink($renderedPng);
            $row['reason'] = 'reference render failed: ' . $e->getMessage();

            return $row;
        }
        $diff = $this->scorer->diff($renderedPng, $refPng, $this->fuzzyFor($testPath, $refPath));

        @copy($renderedPng, $base . '-rendered.png');
        @copy($refPng, $base . '-ref.png');
        if ($diff['diffImage'] !== null) {
            @copy($diff['diffImage'], $base . '-diff.png');
            @unlink($diff['diffImage']);
        }
        @unlink($renderedPng);
        if ($refPng !== $refPath) {
            @unlink($refPng);
        }

        $row['score'] = $diff['score'];
        $row['passed'] = $diff['passed'];
        $row['rendered'] = is_file($base . '-rendered.png') ? $base . '-rendered.png' : null;
        $row['reference'] = is_file($base . '-ref.png') ? $base . '-ref.png' : null;
        $row['diff'] = is_file($base . '-diff.png') ? $base . '-diff.png' : null;

        return $row;
    }

    /**
     * Render an in-scope test, rasterise the resulting PDF, and
     * visually-diff against its WPT reference. Tests without a
     * `*-ref.{png,html,xht,svg}` sibling are reported as Skipped
     * — the harness can't know what "pass" means without one.
     */
    private function runRendered(string $testId): TestResult
    {
        $rootAbs = realpath($this->wptRoot);
        if ($rootAbs === false) {
            return new TestResult(
                testId: $testId,
                status: TestStatus::HarnessError,
                diffScore: 0.0,
                reason: "wpt root not accessible: $this->wptRoot",
                diffArtefactPath: null,
                renderMicros: 0.0,
            );
        }
        $testPath = $this->resolveTestFile($rootAbs, $testId);
        if ($testPath === null) {
            return new TestResult(
                testId: $testId,
                status: TestStatus::HarnessError,
                diffScore: 0.0,
                reason: 'test file not found for ID',
                diffArtefactPath: null,
                renderMicros: 0.0,
            );
        }
        $refPath = $this->locateReference($testPath);
        if ($refPath === null) {
            return new TestResult(
                testId: $testId,
                status: TestStatus::Skipped,
                diffScore: 0.0,
                reason: 'no -ref.{png,html,xht,svg} sibling found',
                diffArtefactPath: null,
                renderMicros: 0.0,
            );
        }

        $start = hrtime(true);
        try {
            $renderedPng = $this->renderToPng($testPath);
        } catch (\Throwable $e) {
            return new TestResult(
                testId: $testId,
                status: TestStatus::Fail,
                diffScore: 1.0,
                reason: 'render failed: ' . $e->getMessage(),
                diffArtefactPath: null,
                renderMicros: (hrtime(true) - $start) / 1000.0,
            );
        }
        $renderMicros = (hrtime(true) - $start) / 1000.0;

        try {
            $refPng = str_ends_with(strtolower($refPath), '.png')
                ? $refPath
                : $this->renderToPng($refPath);
        } catch (\Throwable $e) {
            @unlink($renderedPng);
            return new TestResult(
                testId: $testId,
                status: TestStatus::HarnessError,
                diffScore: 1.0,
                reason: 'reference render failed: ' . $e->getMessage(),
                diffArtefactPath: null,
                renderMicros: $renderMicros,
            );
        }

        $diff = $this->scorer->diff($renderedPng, $refPng, $this->fuzzyFor($testPath, $refPath));
        @unlink($renderedPng);
        if ($refPng !== $refPath) {
            @unlink($refPng);
        }

        return new TestResult(
            testId: $testId,
            status: $diff['passed'] ? TestStatus::Pass : TestStatus::Fail,
            diffScore: $diff['score'],
            reason: $diff['reason'],
            diffArtefactPath: $diff['diffImage'] ?? null,
            renderMicros: $renderMicros,
            bothRendersSolid: $diff['bothSolid'],
        );
    }

    /**
     * Resolve the `<meta name="fuzzy">` tolerance that applies when
     * this test is compared against `$referencePath`.
     *
     * WPT selects fuzzy nodes exactly the way it selects reference
     * links — `.//{http://www.w3.org/1999/xhtml}meta[@name='fuzzy']` —
     * so a namespace prefix and unquoted attribute values are both
     * normal, and 237 corpus fixtures do write `name=fuzzy` unquoted.
     * A matcher that demands `name="fuzzy"` in quotes, and demands it
     * before `content`, drops every one of those fixtures back onto
     * the harness default threshold without saying so.
     *
     * A declaration may be keyed to one specific reference
     * (`content="ref.html:0-5;0-100"` — WPT's `parse_ref_keyed_meta`,
     * splitting on the last colon). A keyed declaration applies only
     * to the reference it names; an unkeyed one is the default for
     * every reference.
     *
     * @internal Exposed for {@see \Phpdftk\WptHarness\Tests\FuzzyMetaResolutionTest}.
     */
    public function fuzzyFor(string $testPath, string $referencePath): ?FuzzyTolerance
    {
        $head = @file_get_contents($testPath, false, null, 0, self::MARKUP_SCAN_BYTES);
        if ($head === false || $head === '') {
            return null;
        }
        $unkeyed = null;
        foreach (self::elementsNamed($head, 'meta') as $tag) {
            if (self::attributeValue($tag, 'name') !== 'fuzzy') {
                continue;
            }
            $content = self::attributeValue($tag, 'content');
            if ($content === null) {
                continue;
            }
            $colon = strrpos($content, ':');
            if ($colon !== false) {
                $keyedRef = $this->resolveHref($testPath, substr($content, 0, $colon));
                if ($keyedRef !== null) {
                    if ($keyedRef === $referencePath) {
                        $keyed = FuzzyTolerance::parse(substr($content, $colon + 1));
                        if ($keyed !== null) {
                            return $keyed;
                        }
                    }
                    // Keyed at a different reference — not ours.
                    continue;
                }
            }
            $unkeyed ??= FuzzyTolerance::parse($content);
        }
        return $unkeyed;
    }

    private function resolveTestFile(string $rootAbs, string $testId): ?string
    {
        foreach (self::TEST_EXTENSIONS as $ext) {
            $candidate = $rootAbs . '/' . $testId . '.' . $ext;
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Locate the reference rendering for a WPT reftest.
     *
     * WPT's own manifest generator (`tools/manifest/sourcefile.py`)
     * defines the reference set as:
     *
     *     match_links = self.root.findall(
     *         ".//{http://www.w3.org/1999/xhtml}link[@rel='match']")
     *
     * Three things follow, and this method honours all three:
     *
     *  1. The lookup is by **XHTML namespace**, not by literal tag
     *     spelling. SVG and XML fixtures declare their reference as
     *     `<html:link rel="match" href="…"/>` with `xmlns:html` bound
     *     to the XHTML namespace — still an XHTML `link`, still a
     *     reference. A matcher keyed on the literal string `<link`
     *     misses every one of them (266 fixtures in the current
     *     corpus, 112 of them under `css/css-masking/`), which drops
     *     them out of scope entirely instead of scoring them.
     *  2. The declared link is what makes a file a reftest, so when a
     *     test declares one it **outranks** the legacy `<stem>-ref.*`
     *     filename sibling. 35 fixtures in the corpus carry both and
     *     disagree (e.g. `block-in-inline-remove-006.xht` declares
     *     `…-nosplit-ref.xht` while a `…-ref.xht` sibling also
     *     exists); WPT scores the declared one.
     *  3. `rel="mismatch"` is the negative relation and is *not*
     *     selected by `[@rel='match']`. The harness does not yet
     *     implement "must not match" semantics, so mismatch-only
     *     fixtures stay Skipped (a follow-up).
     *
     * The `<stem>-ref.*` sibling remains the fallback: it is a
     * CSS-WG-era convention that predates `rel=match`, and 53 corpus
     * fixtures still rely on it alone. PNG wins among siblings since
     * it short-circuits a re-render.
     *
     * @internal Exposed for {@see \Phpdftk\WptHarness\Tests\ReferenceResolutionTest}.
     */
    public function locateReference(string $testPath): ?string
    {
        $declared = $this->locateLinkRelMatchReference($testPath);
        if ($declared !== null) {
            return $declared;
        }
        $info = pathinfo($testPath);
        $dir = $info['dirname'] ?? '.';
        $stem = $info['filename'] ?? '';
        $candidates = [
            $dir . '/' . $stem . '-ref.png',
            $dir . '/' . $stem . '-ref.html',
            $dir . '/' . $stem . '-ref.xht',
            $dir . '/' . $stem . '-ref.svg',
        ];
        foreach ($candidates as $cand) {
            $real = realpath($cand);
            if ($real !== false && is_file($real)) {
                return $real;
            }
        }
        return null;
    }

    /**
     * Resolve the first `<link rel="match" href="…">` in a test file
     * to an on-disk path. Tags are scanned in document order and the
     * first one whose href resolves to an existing file wins — an
     * unresolvable declaration falls through to the caller's
     * filename-sibling fallback rather than poisoning the lookup.
     *
     * Accepts any namespace prefix on the element name (`html:link`,
     * `x:link`, …) and unquoted attribute values (`rel=match`), both
     * of which occur in the corpus.
     *
     * Read is bounded to {@see self::MARKUP_SCAN_BYTES} so a
     * malformed test can't stall the harness. Verified against the
     * full corpus: zero fixtures declare their reference beyond that
     * offset.
     */
    private function locateLinkRelMatchReference(string $testPath): ?string
    {
        $head = @file_get_contents($testPath, false, null, 0, self::MARKUP_SCAN_BYTES);
        if ($head === false || $head === '') {
            return null;
        }
        foreach (self::elementsNamed($head, 'link') as $tag) {
            if (self::attributeValue($tag, 'rel') !== 'match') {
                continue;
            }
            $href = self::attributeValue($tag, 'href');
            if ($href === null) {
                continue;
            }
            $resolved = $this->resolveHref($testPath, $href);
            if ($resolved !== null) {
                return $resolved;
            }
        }
        return null;
    }

    /**
     * Resolve a reftest href to an absolute on-disk path. Root-relative
     * hrefs resolve against the corpus root (wptserve's document root);
     * everything else against the referring file's directory.
     *
     * Fragments and query strings are stripped: they are meaningful to
     * the browser but never part of the filename on disk.
     */
    private function resolveHref(string $fromPath, string $href): ?string
    {
        // WPT strips ASCII whitespace from the href before joining
        // (`item.attrib["href"].strip(space_chars)`).
        $href = trim($href, " \t\n\r\f\x0B");
        $href = (string) preg_replace('~[#?].*$~s', '', $href);
        if ($href === '') {
            return null;
        }
        $resolved = str_starts_with($href, '/')
            ? rtrim($this->wptRoot, '/') . $href
            : dirname($fromPath) . '/' . $href;
        $real = realpath($resolved);
        return ($real !== false && is_file($real)) ? $real : null;
    }

    /**
     * Yield every start tag named `$name`, allowing an optional XML
     * namespace prefix (`html:link`). `[^>]` bounds each match to a
     * single tag so attributes can never be read across a tag
     * boundary.
     *
     * @return list<string>
     */
    private static function elementsNamed(string $markup, string $name): array
    {
        $pattern = '~<(?:[A-Za-z_][\w.\-]*:)?' . preg_quote($name, '~') . '\b[^>]*>~i';
        if (preg_match_all($pattern, $markup, $matches) < 1) {
            return [];
        }
        /** @var list<string> $tags */
        $tags = $matches[0];
        return $tags;
    }

    /**
     * Read one attribute out of a start tag. Handles double-quoted,
     * single-quoted and unquoted values. The lookbehind stops
     * `data-href` from being read as `href`.
     */
    private static function attributeValue(string $tag, string $name): ?string
    {
        $pattern = '~(?<![\w:.\-])' . preg_quote($name, '~')
            . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))~i';
        if (preg_match($pattern, $tag, $m, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }
        return $m[1] ?? $m[2] ?? $m[3] ?? null;
    }

    /**
     * Render a single test file through the phpdftk pipeline and
     * rasterise the first page of the resulting PDF.
     */
    private function renderToPng(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $pdfBytes = $ext === 'svg'
            ? $this->renderSvgToPdf($path)
            : $this->renderHtmlToPdf($path);
        $pdfPath = tempnam(sys_get_temp_dir(), 'wpt_pdf_') . '.pdf';
        file_put_contents($pdfPath, $pdfBytes);
        try {
            return $this->rasteriser->rasterise($pdfPath, 0);
        } finally {
            @unlink($pdfPath);
        }
    }

    private function renderHtmlToPdf(string $path): string
    {
        if (!class_exists('Phpdftk\\HtmlToPdf\\Renderer')) {
            throw new \RuntimeException('phpdftk/html-to-pdf not installed');
        }
        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException("could not read test file: $path");
        }
        // Settle reftest-wait fixtures through Playwright so the
        // PHP renderer sees the post-JS DOM (inline-style shifts
        // from getBoundingClientRect, fonts marked loaded via
        // FontFace API, etc.). When no settler is configured, the
        // settler isn't available on this host, or settling fails,
        // fall back to the pre-JS source - the harness still
        // produces a result, just one that reflects an unrun test.
        if ($this->domSettler !== null) {
            $settled = $this->domSettler->maybeSettle($path, $html);
            if ($settled !== null) {
                $html = $settled;
            }
        }
        // Sandbox to the WPT corpus root so refs in `reference/`
        // subdirs can resolve `../support/img.png` siblings of the
        // test directory. baseDir alone is too tight — the default
        // ResourceLoader sandbox is the same as baseDir, which
        // rejects any `..` walk.
        $options = (new \Phpdftk\HtmlToPdf\RendererOptions())
            ->withBaseDir(dirname($path))
            ->withSandboxRoot($this->wptRoot)
            // WPT test corpus is browser-targeted, so the vast
            // majority of `@media` rules gate on `screen` rather
            // than `print`. Match both so author CSS applies the
            // way the test fixtures (and their references) expect.
            ->withMatchingMediaTypes(['print', 'screen']);
        $defaultFont = $this->harnessDefaultFont();
        if ($defaultFont !== null) {
            $options = $options->withDefaultFont($defaultFont);
        }
        $result = (new \Phpdftk\HtmlToPdf\Renderer($options))->render($html);
        return $result->writer->toBytes();
    }

    private function renderSvgToPdf(string $path): string
    {
        if (!class_exists('Phpdftk\\SvgToPdf\\SvgRenderer')
            || !class_exists('Phpdftk\\Svg\\Parser')
            || !class_exists('Phpdftk\\Pdf\\Writer\\PdfWriter')
        ) {
            throw new \RuntimeException('svg-to-pdf renderer stack not installed');
        }
        $svgSource = file_get_contents($path);
        if ($svgSource === false) {
            throw new \RuntimeException("could not read test file: $path");
        }
        $writer = new \Phpdftk\Pdf\Writer\PdfWriter();
        $pageWidth = 612.0;
        $pageHeight = 792.0;
        $page = $writer->addPage($pageWidth, $pageHeight);
        $svgDoc = (new \Phpdftk\Svg\Parser())->parse($svgSource);
        $renderer = new \Phpdftk\SvgToPdf\SvgRenderer($page, $writer);
        // SVG 2 §8.2 — `width` / `height` on the outermost `<svg>` are
        // `auto`, which resolves to `100%`. For a STANDALONE document the
        // viewport is the window, which here is the page: pass it as the
        // destination so a sizeless root fills the page instead of
        // collapsing to the renderer's finite-size fallback. Without this
        // a `<svg>` with no width/height/viewBox renders blank, and its
        // reftest passes only because the test side is equally blank.
        // Only a root that supplies NO intrinsic size takes the page as
        // its viewport; one declaring `width="200" height="200"` (or a
        // `viewBox`, which gives a ratio) keeps its natural size, exactly
        // as a browser shows a standalone SVG.
        [$viewportWidth, $viewportHeight] = self::svgRootViewport($svgDoc, $pageWidth, $pageHeight);
        // `draw()` anchors the destination rect by its BOTTOM-left in
        // PDF user space, while a standalone document's viewport
        // starts at the TOP-left of the canvas. They only coincide
        // when the viewport is as tall as the page, so a shorter
        // viewport has to be lifted by the difference or it renders
        // flush with the bottom margin instead of the top.
        $renderer->draw(
            $svgDoc,
            x: 0,
            y: $pageHeight - $viewportHeight,
            width: $viewportWidth,
            height: $viewportHeight,
        );
        return $writer->toBytes();
    }

    /**
     * SVG 2 §8.2 — the viewport a STANDALONE outermost `<svg>` paints
     * into, in CSS px.
     *
     * The `width` / `height` attributes are the viewport; a `viewBox`
     * is a coordinate system mapped INTO that viewport, never the
     * viewport itself. `<svg viewBox="0 0 3 3" width="200"
     * height="200">` is a 200×200 box in which one user unit is 66⅔
     * px — passing no destination would instead render the whole
     * document 3pt wide.
     *
     * When only one axis is fixed, a `viewBox` supplies the ratio for
     * the other. When neither axis is fixed, `width` / `height` are
     * `auto` → `100%` of the window, which for a standalone render is
     * the page. A `viewBox`-only root keeps its viewBox extent as its
     * natural size.
     *
     * @return array{0: float, 1: float}
     */
    private static function svgRootViewport(
        \Phpdftk\Svg\SvgDocument $svg,
        float $pageWidth,
        float $pageHeight,
    ): array {
        $viewBox = $svg->viewBox();
        $w = self::parseSvgRootLength($svg->widthAttribute());
        $h = self::parseSvgRootLength($svg->heightAttribute());
        $ratio = $viewBox !== null && $viewBox[2] > 0.0 && $viewBox[3] > 0.0
            ? $viewBox[2] / $viewBox[3]
            : null;
        if ($w !== null && $h === null && $ratio !== null) {
            $h = $w / $ratio;
        } elseif ($h !== null && $w === null && $ratio !== null) {
            $w = $h * $ratio;
        }
        if ($w !== null && $h !== null) {
            // Mirror `SvgRenderer::resolveSourceRect`'s near-integral
            // snap (crbug.com/1392140) when there is no viewBox, so the
            // destination it derives from the same attributes and the
            // one passed here stay identical and the scale stays 1.
            return $viewBox === null
                ? [(float) round($w), (float) round($h)]
                : [$w, $h];
        }
        if ($viewBox !== null) {
            return [$viewBox[2], $viewBox[3]];
        }
        return [$pageWidth, $pageHeight];
    }

    /**
     * A root `width` / `height` attribute as a fixed CSS-px length, or
     * null when it is absent, a percentage, or otherwise not a
     * parseable absolute length (all of which are `auto`-like and need
     * a viewport to resolve).
     */
    private static function parseSvgRootLength(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || str_contains($trimmed, '%')) {
            return null;
        }
        if (preg_match(
            '/^([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)\s*(px|pt|pc|cm|mm|q|in)?$/i',
            $trimmed,
            $m,
        ) !== 1) {
            return null;
        }
        $value = (float) $m[1] * match (strtolower($m[2] ?? '')) {
            'cm' => 96.0 / 2.54,
            'mm' => 96.0 / 25.4,
            'q' => 96.0 / 101.6,
            'in' => 96.0,
            'pt' => 96.0 / 72.0,
            'pc' => 16.0,
            default => 1.0,
        };
        return $value > 0.0 ? $value : null;
    }

    public function manifest(): Manifest
    {
        return $this->manifest;
    }

    public function rasteriser(): Rasteriser
    {
        return $this->rasteriser;
    }

    public function scorer(): Scorer
    {
        return $this->scorer;
    }

    public function wptRoot(): string
    {
        return $this->wptRoot;
    }

    /**
     * Recursively yield absolute paths to test files under the
     * given root. References (`*-ref.*`) are skipped — they're
     * targets for the diff scorer, not tests.
     *
     * @return iterable<string>
     */
    private function discoverTests(string $root): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $info) {
            assert($info instanceof \SplFileInfo);
            if (!$info->isFile()) {
                continue;
            }
            $extension = strtolower($info->getExtension());
            if (!in_array($extension, self::TEST_EXTENSIONS, true)) {
                continue;
            }
            $stem = $info->getBasename('.' . $info->getExtension());
            // `*-ref.html`, `*-ref.xht`, `*-notref.html` are
            // expected-rendering / negative-match siblings, not
            // tests themselves.
            if (str_ends_with($stem, '-ref') || str_ends_with($stem, '-notref')) {
                continue;
            }
            yield $info->getPathname();
        }
    }

    /**
     * Convert an absolute test-file path into a stable test ID —
     * the relative path under `$wptRoot`, minus the file
     * extension. POSIX-style separators so manifest globs match
     * cross-platform.
     */
    private function testIdFromPath(string $absolutePath): ?string
    {
        $rootAbs = realpath($this->wptRoot);
        $pathAbs = realpath($absolutePath);
        if ($rootAbs === false || $pathAbs === false) {
            return null;
        }
        if (!str_starts_with($pathAbs, $rootAbs)) {
            return null;
        }
        $relative = substr($pathAbs, strlen($rootAbs));
        $relative = ltrim($relative, '/\\');
        // Normalise to POSIX separators.
        $relative = str_replace('\\', '/', $relative);
        // Strip the extension.
        $dotPos = strrpos($relative, '.');
        if ($dotPos !== false) {
            $relative = substr($relative, 0, $dotPos);
        }
        return $relative;
    }
}
