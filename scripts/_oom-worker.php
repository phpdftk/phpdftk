<?php

declare(strict_types=1);

/**
 * One-shot subprocess used by scripts/diagnose-oom-fixtures.php.
 * Renders a single WPT fixture through our renderer and prints
 *   PEAK <peak-memory-bytes>
 * on success. On an uncatchable Fatal (memory_limit OOM) PHP
 * itself emits the error to stderr and exits non-zero, which the
 * parent then attributes to the supplied path.
 */

require __DIR__ . '/../vendor/autoload.php';

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Pdf\Writer\PdfWriter;

if (!isset($argv[1])) {
    fwrite(STDERR, "usage: _oom-worker.php <fixture-path>\n");
    exit(2);
}
$path = $argv[1];
if (!is_file($path)) {
    fwrite(STDERR, "fixture not found: $path\n");
    exit(2);
}

try {
    $opts = (new RendererOptions())->withBaseDir(dirname($path));
    $renderer = new Renderer($opts);
    $writer = new PdfWriter();
    $renderer->renderInto($writer, (string) file_get_contents($path));
    $writer->toBytes();
    printf("PEAK %d\n", memory_get_peak_usage(true));
    exit(0);
} catch (\Throwable $e) {
    // Catchable failures are NOT what we're hunting for — record
    // them but don't conflate with an OOM. Print PEAK so the
    // parent still gets a memory reading.
    printf("PEAK %d\n", memory_get_peak_usage(true));
    fwrite(STDERR, "throwable: " . get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(0);
}
