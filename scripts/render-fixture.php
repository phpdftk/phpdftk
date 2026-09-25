<?php

declare(strict_types=1);

/**
 * Render a single WPT fixture (HTML / XHTML / SVG) to a PNG, using the
 * EXACT same path as the WPT gate (Phpdftk\WptHarness\HarnessRunner) so
 * local debugging matches scored results — including the gate's UA default
 * font, without which every text-bearing fixture renders blank and a test
 * and its reference wrongly compare as identical.
 *
 * Usage:
 *   php scripts/render-fixture.php <fixture.{html,xht,xhtml,htm,svg}> <out.png> [pageIndex]
 *
 * The DOM settler is intentionally NOT run — this mirrors the deterministic
 * settler-off gate (WPT_DISABLE_DOM_SETTLER=1). Fixtures that depend on JS
 * therefore render their pre-JS source, exactly as the gate scores them.
 *
 * `.xht` / `.xhtml` are handed to the same Renderer as `.html`; the HTML
 * parser accepts the XHTML source verbatim (the harness does no special
 * casing), so column/table/positioning reftests written as `.xht` render
 * identically here and can be instrumented via the real class stack.
 */

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\WptHarness\HarnessRunner;
use Phpdftk\WptHarness\Rasteriser;


/**
 * The gate's UA default font, resolved exactly as
 * `HarnessRunner::harnessDefaultFont()` does — including the
 * `WPT_DEFAULT_FONT` override and its `none` opt-out.
 */
function harnessDefaultFont(): ?\Phpdftk\FontParser\FontFaceData
{
    $env = getenv('WPT_DEFAULT_FONT');
    if ($env === 'none') {
        return null;
    }
    $path = ($env !== false && $env !== '')
        ? $env
        : __DIR__ . '/../packages/wpt-harness/resources/fonts/DejaVuSerif.ttf';
    if (!is_file($path)) {
        return null;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    try {
        return match ($ext) {
            'otf' => (new \Phpdftk\FontParser\OpenTypeParser($path))->parse(),
            'woff' => (new \Phpdftk\FontParser\WoffParser($path))->parse(),
            default => (new \Phpdftk\FontParser\TrueTypeParser($path))->parse(),
        };
    } catch (\Throwable) {
        return null;
    }
}

// Locate the Composer autoloader robustly so the script works whether it
// lives in the repo's scripts/ dir or a /tmp copy: try alongside the
// script, then walk up from the current working directory.
$autoload = null;
foreach ([__DIR__ . '/../vendor/autoload.php', getcwd() . '/vendor/autoload.php'] as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}
if ($autoload === null) {
    $dir = getcwd();
    while ($dir !== '' && $dir !== '/' && $dir !== dirname($dir)) {
        if (is_file($dir . '/vendor/autoload.php')) {
            $autoload = $dir . '/vendor/autoload.php';
            break;
        }
        $dir = dirname($dir);
    }
}
if ($autoload === null) {
    fwrite(STDERR, "could not locate vendor/autoload.php; run from the repo root\n");
    exit(2);
}
require $autoload;

if ($argc < 3) {
    fwrite(STDERR, "usage: render-fixture.php <fixture> <out.png> [pageIndex]\n");
    exit(2);
}

$input = $argv[1];
$output = $argv[2];
$pageIndex = isset($argv[3]) ? max(0, (int) $argv[3]) : 0;

$abs = realpath($input);
if ($abs === false || !is_file($abs)) {
    fwrite(STDERR, "fixture not found: $input\n");
    exit(1);
}

$source = file_get_contents($abs);
if ($source === false) {
    fwrite(STDERR, "could not read fixture: $abs\n");
    exit(1);
}

// Sandbox root = the WPT corpus root so refs in `reference/` subdirs can
// resolve `../support/*` siblings (same policy as HarnessRunner). Fall back
// to the fixture's own directory when the file isn't under vendor-data/wpt.
$sandboxRoot = dirname($abs);
if (preg_match('#^(.*/vendor-data/wpt)(/|$)#', $abs, $m) === 1) {
    $sandboxRoot = $m[1];
}

$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

if ($ext === 'svg') {
    // Delegate to the gate's own SVG path rather than re-deriving it:
    // a standalone outermost `<svg>` with no width/height (SVG 2 §8.2 —
    // `auto` → `100%` of the window) needs the page passed as its
    // viewport, and a hand-rolled `draw($svg, 0, 0)` here collapsed
    // every such fixture to blank. Blank-vs-blank then reads as a
    // passing diff for something the gate scores as a failure.
    $pdfBytes = HarnessRunner::renderSvgToPdf($abs);
} else {
    // Mirror HarnessRunner::renderHtmlToPdf option-for-option.
    $options = (new RendererOptions())
        ->withBaseDir(dirname($abs))
        ->withSandboxRoot($sandboxRoot)
        ->withMatchingMediaTypes(['print', 'screen']);
    // The gate installs a UA default font; without it this script renders
    // text-bearing fixtures blank, which silently makes a test and its
    // reference look identical and reports a passing diff for something the
    // gate scores as a failure.
    $defaultFont = harnessDefaultFont();
    if ($defaultFont !== null) {
        $options = $options->withDefaultFont($defaultFont);
    }
    $renderer = new Renderer($options);
    $pdfBytes = $renderer->render($source)->writer->toBytes();
}

$pdfPath = tempnam(sys_get_temp_dir(), 'render_fixture_') . '.pdf';
file_put_contents($pdfPath, $pdfBytes);
// A one-pixel seam is diagnosed by reading the operators each paint
// path emitted, not by squinting at the pixels they produced, so keep
// the intermediate PDF when asked. `WPT_PDF_OUT=/tmp/x.pdf` writes it
// alongside the PNG; inflate its FlateDecode streams to read it.
if (($keep = getenv('WPT_PDF_OUT')) !== false && $keep !== '') {
    @copy($pdfPath, $keep);
}
try {
    $png = (new Rasteriser())->rasterise($pdfPath, $pageIndex);
} finally {
    @unlink($pdfPath);
}

if (!@copy($png, $output)) {
    @unlink($png);
    fwrite(STDERR, "could not write output: $output\n");
    exit(1);
}
@unlink($png);
