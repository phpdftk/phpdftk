<?php

declare(strict_types=1);

/**
 * WPT compliance gallery generator.
 *
 * Walks a `--filter` slice of the WPT corpus and, per test, renders our
 * output (test HTML/SVG -> PDF -> PNG through the real harness pipeline),
 * renders the WPT reference the same way (self-consistency oracle),
 * scores the pair with ImageMagick, downscales thumbnails, and emits a
 * `manifest.json` the Astro gallery page consumes at build time.
 *
 * This is Phases 1 + 4 of docs/plans/wpt-gallery.md: the reusable data
 * layer (manifest + kept PNGs) plus sharding so the full ~22k corpus is
 * tractable in CI.
 *
 * Reference location and `<meta name=fuzzy>` parsing are DELEGATED to
 * HarnessRunner rather than reimplemented here, so gallery verdicts can
 * never drift from the pass-rate scoreboard's.
 *
 * Usage:
 *   php scripts/build-wpt-gallery.php \
 *       [--root=vendor-data/wpt] \
 *       [--out=docs/generated/wpt-gallery] \
 *       [--filter='css/css-flexbox/**'] \   # glob slice of the corpus
 *       [--shard=K/N] \                      # process shard K of N (1-based)
 *       [--limit=N] \                        # cap tests (smoke runs)
 *       [--thumb=300] \                      # thumbnail max width in px
 *       [--only-reftests] \                  # skip tests with no reference
 *       [id ...]                             # explicit ids (overrides filter)
 *
 * Needs Ghostscript (Rasteriser) + ImageMagick (`compare` + `convert`).
 * Emits: <out>/manifest.json, <out>/img/<slug>.{ours,ref}.png (full) and
 * <out>/img/<slug>.{ours,ref}.thumb.png (downscaled).
 */

use Phpdftk\Filesystem\LocalFilesystem;
use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\WptHarness\HarnessRunner;
use Phpdftk\WptHarness\Manifest;
use Phpdftk\WptHarness\Rasteriser;
use Phpdftk\WptHarness\Scorer;

require __DIR__ . '/../vendor/autoload.php';

$opts = [
    'root' => 'vendor-data/wpt',
    'out' => 'docs/generated/wpt-gallery',
    'filter' => null,
    'shard' => null,
    'limit' => null,
    'thumb' => 300,
    'only-reftests' => false,
];
$ids = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--root=')) {
        $opts['root'] = substr($arg, 7);
    } elseif (str_starts_with($arg, '--out=')) {
        $opts['out'] = substr($arg, 6);
    } elseif (str_starts_with($arg, '--filter=')) {
        $opts['filter'] = substr($arg, 9);
    } elseif (str_starts_with($arg, '--shard=')) {
        $opts['shard'] = substr($arg, 8);
    } elseif (str_starts_with($arg, '--limit=')) {
        $opts['limit'] = (int) substr($arg, 8);
    } elseif (str_starts_with($arg, '--thumb=')) {
        $opts['thumb'] = max(48, (int) substr($arg, 8));
    } elseif ($arg === '--only-reftests') {
        $opts['only-reftests'] = true;
    } elseif (!str_starts_with($arg, '--')) {
        $ids[] = $arg;
    }
}

$rasteriser = new Rasteriser();
if (!$rasteriser->isAvailable()) {
    fwrite(STDERR, "build-wpt-gallery: Ghostscript (`gs`) unavailable.\n");
    exit(1);
}
$scorer = new Scorer();
$hasCompare = $scorer->isAvailable();
$hasConvert = imagemagickConvert() !== null;
if (!$hasCompare) {
    fwrite(STDERR, "build-wpt-gallery: ImageMagick `compare` unavailable — verdicts will be 'unknown'.\n");
}
if (!$hasConvert) {
    fwrite(STDERR, "build-wpt-gallery: ImageMagick `convert`/`magick` unavailable — thumbnails fall back to full images.\n");
}

$wptRoot = realpath($opts['root']) ?: $opts['root'];
if (!is_dir($wptRoot)) {
    fwrite(STDERR, "build-wpt-gallery: WPT root not found: {$opts['root']}\n");
    exit(1);
}

$out = $opts['out'];
$imgDir = $out . '/img';
if (!is_dir($imgDir)) {
    @mkdir($imgDir, 0o777, true);
}

// ----- resolve the working set of test ids -----
if ($ids !== []) {
    $testIds = $ids;
} else {
    fwrite(STDERR, "discovering tests" . ($opts['filter'] !== null ? " under filter '{$opts['filter']}'" : '') . " ...\n");
    $testIds = discoverTestIds($wptRoot, $opts['filter']);
    sort($testIds); // stable order so shards are deterministic
    fwrite(STDERR, "discovered " . count($testIds) . " tests\n");
}

// ----- sharding: keep shard K of N (1-based) via modulo on a stable index -----
if ($opts['shard'] !== null) {
    [$k, $n] = array_map('intval', explode('/', $opts['shard'], 2) + [1, 1]);
    if ($n < 1 || $k < 1 || $k > $n) {
        fwrite(STDERR, "build-wpt-gallery: invalid --shard '{$opts['shard']}' (want K/N, 1<=K<=N)\n");
        exit(1);
    }
    $testIds = array_values(array_filter(
        $testIds,
        static fn(int $idx): bool => ($idx % $n) === ($k - 1),
        ARRAY_FILTER_USE_KEY,
    ));
    fwrite(STDERR, "shard {$k}/{$n}: " . count($testIds) . " tests\n");
}

if ($opts['limit'] !== null && $opts['limit'] > 0) {
    $testIds = array_slice($testIds, 0, $opts['limit']);
}

// ----- render + score each test -----
$records = [];
$total = count($testIds);
$i = 0;
foreach ($testIds as $id) {
    $i++;
    $testPath = resolveFixture($wptRoot, $id);
    if ($testPath === null) {
        fwrite(STDERR, "[{$i}/{$total}] skip (not found): {$id}\n");
        continue;
    }
    $rec = renderOne($id, $testPath, $wptRoot, $rasteriser, $scorer, $out, $opts, $hasCompare, $hasConvert);
    if ($opts['only-reftests'] && $rec['reference'] === null) {
        continue;
    }
    if ($rec['ours'] === null && $rec['reference'] === null) {
        fwrite(STDERR, "[{$i}/{$total}] {$id}: nothing rendered\n");
        continue;
    }
    $records[] = $rec;
    fwrite(
        STDERR,
        "[{$i}/{$total}] {$id}: {$rec['verdict']}" .
        ($rec['ae']['reference'] !== null ? sprintf(' (AE %.4f)', $rec['ae']['reference']) : '') .
        "\n",
    );
}

// ----- emit manifest.json -----
$manifest = [
    'schema' => 1,
    'generatedAt' => gmdate('c'),
    'filter' => $opts['filter'],
    'shard' => $opts['shard'],
    'count' => count($records),
    'summary' => summarise($records),
    'tests' => $records,
];
LocalFilesystem::writeFile(
    $out . '/manifest.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);
fwrite(STDERR, "\nwrote {$out}/manifest.json (" . count($records) . " tests)\n");

// =====================================================================
// helpers
// =====================================================================

/**
 * Render one test + its reference, score, thumbnail, and build the
 * manifest record.
 *
 * @param array{root:string,out:string,filter:?string,shard:?string,limit:?int,thumb:int,only-reftests:bool} $opts
 * @return array{testId:string,source:string,assert:string,ours:?string,oursThumb:?string,reference:?string,referenceThumb:?string,referenceSource:?string,engines:array<string,string>,verdict:string,ae:array{reference:?float},flags:list<string>}
 */
function renderOne(
    string $id,
    string $testPath,
    string $wptRoot,
    Rasteriser $ras,
    Scorer $scorer,
    string $out,
    array $opts,
    bool $hasCompare,
    bool $hasConvert,
): array {
    $slug = slugify($id);
    $html = LocalFilesystem::readFile($testPath, 'gallery fixture');
    $assert = extractAssert($html);
    $flags = detectFlags($testPath, $html);

    // Our render.
    $oursFull = "img/{$slug}.ours.png";
    $oursThumbRel = "img/{$slug}.ours.thumb.png";
    $okOurs = renderTo($testPath, $wptRoot, $ras, $out . '/' . $oursFull);
    $oursThumb = null;
    if ($okOurs) {
        $oursThumb = thumbnail($out . '/' . $oursFull, $out . '/' . $oursThumbRel, $opts['thumb'], $hasConvert)
            ? $oursThumbRel : $oursFull;
    }

    // Reference render.
    $refFull = null;
    $refThumb = null;
    $refSourceRel = null;
    $refPath = harnessSemantics($wptRoot)->locateReference($testPath);
    if ($refPath !== null) {
        $refSourceRel = relPath($wptRoot, $refPath);
        $refFull = "img/{$slug}.ref.png";
        $isPng = str_ends_with(strtolower($refPath), '.png');
        $ok = $isPng
            ? copyPng($refPath, $out . '/' . $refFull)
            : renderTo($refPath, $wptRoot, $ras, $out . '/' . $refFull);
        if ($ok) {
            $refThumbRel = "img/{$slug}.ref.thumb.png";
            $refThumb = thumbnail($out . '/' . $refFull, $out . '/' . $refThumbRel, $opts['thumb'], $hasConvert)
                ? $refThumbRel : $refFull;
        } else {
            $refFull = null;
        }
    }

    // Score ours vs reference (self-consistency AE).
    $ae = null;
    $verdict = 'unknown';
    if ($okOurs && $refFull !== null && $hasCompare) {
        $diff = $scorer->diff(
            $out . '/' . $oursFull,
            $out . '/' . $refFull,
            harnessSemantics($wptRoot)->fuzzyFor($testPath, $refPath),
        );
        if ($diff['diffImage'] !== null) {
            @unlink($diff['diffImage']);
        }
        $ae = $diff['score'];
        $verdict = $diff['passed'] ? 'pass' : 'fail';
    } elseif ($okOurs && $refFull === null) {
        $verdict = 'no-ref';
    } elseif (!$okOurs) {
        $verdict = 'render-error';
    }

    return [
        'testId' => $id,
        'source' => relPath($wptRoot, $testPath),
        'assert' => $assert,
        'ours' => $okOurs ? $oursFull : null,
        'oursThumb' => $oursThumb,
        'reference' => $refFull,
        'referenceThumb' => $refThumb,
        'referenceSource' => $refSourceRel,
        'engines' => [], // populated by the cross-browser oracle in a later phase
        'verdict' => $verdict,
        'ae' => ['reference' => $ae],
        'flags' => $flags,
    ];
}

/**
 * Recursively discover WPT test ids under $root, optionally filtered by
 * a glob (same `*`/`**` semantics as the manifest rule files). Skips
 * `-ref` / `-notref` siblings.
 *
 * @return list<string>
 */
function discoverTestIds(string $root, ?string $filter): array
{
    $exts = ['html', 'xht', 'xhtml', 'htm', 'svg'];
    $ids = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($it as $info) {
        assert($info instanceof SplFileInfo);
        if (!$info->isFile()) {
            continue;
        }
        $ext = strtolower($info->getExtension());
        if (!in_array($ext, $exts, true)) {
            continue;
        }
        $stem = $info->getBasename('.' . $info->getExtension());
        if (str_ends_with($stem, '-ref') || str_ends_with($stem, '-notref')) {
            continue;
        }
        $rel = ltrim(str_replace('\\', '/', substr($info->getPathname(), strlen($root))), '/');
        $dot = strrpos($rel, '.');
        $id = $dot !== false ? substr($rel, 0, $dot) : $rel;
        if ($filter !== null && !globMatches($filter, $id)) {
            continue;
        }
        $ids[] = $id;
    }
    return $ids;
}

/** Glob match with `*` (within-segment) and `**` (across-segment) — a
 * standalone port of Manifest::matches so the generator has no private
 * coupling to the harness package. */
function globMatches(string $glob, string $testId): bool
{
    $regex = '';
    $len = strlen($glob);
    for ($i = 0; $i < $len; $i++) {
        $c = $glob[$i];
        if ($c === '*') {
            if ($i + 1 < $len && $glob[$i + 1] === '*') {
                $regex .= '.*';
                $i++;
            } else {
                $regex .= '[^/]*';
            }
        } elseif ($c === '?') {
            $regex .= '[^/]';
        } else {
            $regex .= preg_quote($c, '~');
        }
    }
    return preg_match('~^' . $regex . '$~', $testId) === 1;
}

/** Resolve a test id to an on-disk file, trying the WPT extensions. */
function resolveFixture(string $root, string $id): ?string
{
    foreach (['', '.html', '.htm', '.xht', '.xhtml', '.svg'] as $ext) {
        $p = $root . '/' . $id . $ext;
        if (is_file($p)) {
            return $p;
        }
    }
    return null;
}

/**
 * The harness's own reference-resolution and fuzzy-meta semantics.
 *
 * This script used to carry a second copy of both, keyed on a
 * literal `<link` and a quoted `name="fuzzy"`. Two copies drift: the
 * gallery would show a green verdict for a fixture the scoreboard
 * scored red (or show "no-ref" for one the scoreboard scored at all).
 * Delegating keeps exactly one implementation of WPT's rules.
 *
 * The Manifest is empty on purpose — the gallery renders whatever
 * slice it was asked for and does not apply scope rules.
 */
function harnessSemantics(string $wptRoot): HarnessRunner
{
    static $runner = null;
    static $root = null;
    if ($runner === null || $root !== $wptRoot) {
        $root = $wptRoot;
        $runner = new HarnessRunner(new Manifest(), new Rasteriser(), new Scorer(), $wptRoot);
    }
    return $runner;
}

/** Extract the `<meta name="assert">` text, if any. */
function extractAssert(string $html): string
{
    return preg_match('/name\s*=\s*["\']assert["\']\s+content\s*=\s*["\']([^"\']+)/i', $html, $m)
        ? trim(html_entity_decode($m[1])) : '';
}

/**
 * Classify a test's WPT harness flags (`ahem`, `image`, `script`) so
 * the gallery can filter font/asset/JS-dependent tests. Best-effort
 * heuristics from the test source.
 *
 * @return list<string>
 */
function detectFlags(string $testPath, string $html): array
{
    $flags = [];
    if (stripos($html, 'ahem') !== false) {
        $flags[] = 'ahem';
    }
    if (preg_match('/<img\b|background(-image)?\s*:\s*url\(|<image\b/i', $html) === 1) {
        $flags[] = 'image';
    }
    if (preg_match('/<script\b|reftest-wait/i', $html) === 1) {
        $flags[] = 'script';
    }
    if (str_ends_with(strtolower($testPath), '.svg')) {
        $flags[] = 'svg';
    }
    return array_values(array_unique($flags));
}

/** Render an HTML/SVG file to a PNG at $destPng through the pipeline. */
function renderTo(string $htmlPath, string $wptRoot, Rasteriser $ras, string $destPng): bool
{
    try {
        $ext = strtolower(pathinfo($htmlPath, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $pdf = renderSvg($htmlPath);
        } else {
            $html = LocalFilesystem::readFile($htmlPath, 'gallery fixture');
            $renderer = new Renderer(
                (new RendererOptions())
                    ->withBaseDir(dirname($htmlPath))
                    ->withSandboxRoot($wptRoot)
                    ->withMatchingMediaTypes(['print', 'screen']),
            );
            $pdf = $renderer->render($html)->writer->toBytes();
        }
        $pdfPath = tempnam(sys_get_temp_dir(), 'gal_') . '.pdf';
        LocalFilesystem::writeFile($pdfPath, $pdf);
        $png = $ras->rasterise($pdfPath, 0);
        @unlink($pdfPath);
        copyPng($png, $destPng);
        @unlink($png);
        return true;
    } catch (\Throwable $e) {
        fwrite(STDERR, "  render failed: {$htmlPath}: {$e->getMessage()}\n");
        return false;
    }
}

/** Render an SVG test through the svg-to-pdf stack (mirrors HarnessRunner). */
function renderSvg(string $path): string
{
    if (!class_exists('Phpdftk\\SvgToPdf\\SvgRenderer')
        || !class_exists('Phpdftk\\Svg\\Parser')
        || !class_exists('Phpdftk\\Pdf\\Writer\\PdfWriter')
    ) {
        throw new \RuntimeException('svg-to-pdf renderer stack not installed');
    }
    $svg = LocalFilesystem::readFile($path, 'gallery fixture');
    $writer = new \Phpdftk\Pdf\Writer\PdfWriter();
    $page = $writer->addPage();
    $doc = (new \Phpdftk\Svg\Parser())->parse($svg);
    (new \Phpdftk\SvgToPdf\SvgRenderer($page, $writer))->draw($doc, x: 0, y: 0);
    return $writer->toBytes();
}

/** Copy a PNG through LocalFilesystem so all disk writes are policy-checked. */
function copyPng(string $src, string $dest): bool
{
    try {
        LocalFilesystem::writeFile($dest, LocalFilesystem::readFile($src, 'gallery image'), true);
        return true;
    } catch (\Throwable $e) {
        fwrite(STDERR, "  copy failed: {$src} -> {$dest}: {$e->getMessage()}\n");
        return false;
    }
}

/**
 * Downscale $srcPng to $destThumb at max width $maxW. Returns true on
 * success; false (caller falls back to the full image) when ImageMagick
 * is unavailable or the resize fails.
 */
function thumbnail(string $srcPng, string $destThumb, int $maxW, bool $hasConvert): bool
{
    if (!$hasConvert || !is_file($srcPng)) {
        return false;
    }
    $bin = imagemagickConvert();
    if ($bin === null) {
        return false;
    }
    // `>` only shrinks (never upscales small refs); flatten onto white so
    // transparent PDFs read as they would on screen.
    $cmd = sprintf(
        '%s %s -background white -flatten -resize %dx%d\> %s 2>/dev/null',
        $bin,
        escapeshellarg($srcPng),
        $maxW,
        $maxW * 4,
        escapeshellarg($destThumb),
    );
    exec($cmd, $_, $status);
    return $status === 0 && is_file($destThumb);
}

/** Locate an ImageMagick resize binary (`magick` preferred, then `convert`). */
function imagemagickConvert(): ?string
{
    static $cached = false;
    static $bin = null;
    if ($cached) {
        return $bin;
    }
    $cached = true;
    foreach (['magick', 'convert'] as $candidate) {
        exec(escapeshellcmd($candidate) . ' -version 2>/dev/null', $_, $status);
        if ($status === 0) {
            $bin = $candidate;
            return $bin;
        }
    }
    return null;
}

/** Test-id -> filesystem-safe slug (path separators to `__`). */
function slugify(string $id): string
{
    return str_replace(['/', '\\'], '__', $id);
}

/** Path of $abs relative to $root (POSIX separators). */
function relPath(string $root, string $abs): string
{
    $rootReal = realpath($root) ?: $root;
    $absReal = realpath($abs) ?: $abs;
    if (str_starts_with($absReal, $rootReal)) {
        return ltrim(str_replace('\\', '/', substr($absReal, strlen($rootReal))), '/');
    }
    return str_replace('\\', '/', basename($abs));
}

/**
 * @param list<array{verdict:string,ae:array{reference:?float},flags:list<string>}> $records
 * @return array{pass:int,fail:int,noRef:int,renderError:int,unknown:int}
 */
function summarise(array $records): array
{
    $s = ['pass' => 0, 'fail' => 0, 'noRef' => 0, 'renderError' => 0, 'unknown' => 0];
    foreach ($records as $r) {
        $s[match ($r['verdict']) {
            'pass' => 'pass',
            'fail' => 'fail',
            'no-ref' => 'noRef',
            'render-error' => 'renderError',
            default => 'unknown',
        }]++;
    }
    return $s;
}
