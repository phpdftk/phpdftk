<?php

declare(strict_types=1);

/**
 * Find which specific fixtures cause memory spikes during a sweep.
 * Renders N fixtures in sequence, tracks `memory_get_usage()` before
 * + after each one, prints any fixture whose render allocates more
 * than the configured threshold.
 *
 * Pair with diagnose-leak-mode.php (per-call clean) and
 * diagnose-static-caches.php (statics clean) — when those say there
 * isn't a global leak, this one finds which individual fixtures
 * cost the heap by themselves.
 *
 * Usage:
 *   php scripts/diagnose-leak-spikes.php [<fixture-count>] [<threshold-kb>]
 *
 * Defaults: 1000 fixtures from css/, anything spending > 1 MB on a
 * single render gets called out.
 */

require __DIR__ . '/../vendor/autoload.php';

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Pdf\Writer\PdfWriter;

$fixtureCount = (int) ($argv[1] ?? 1000);
$thresholdKb = (int) ($argv[2] ?? 1024);

$root = __DIR__ . '/../vendor-data/wpt';
$subdir = $argv[3] ?? 'css';
$walkRoot = $root . '/' . $subdir;
if (!is_dir($walkRoot)) {
    fwrite(STDERR, "subdir not found: $walkRoot\n");
    exit(2);
}
$fixtures = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
    $walkRoot,
    FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
));
foreach ($it as $entry) {
    if (count($fixtures) >= $fixtureCount) {
        break;
    }
    if (!$entry->isFile()) {
        continue;
    }
    $name = $entry->getFilename();
    if (preg_match('/-ref\.(html?|xht)$/i', $name)) {
        continue;
    }
    if (!preg_match('/\.html?$/i', $name)) {
        continue;
    }
    if (str_contains($entry->getPathname(), '/support/')) {
        continue;
    }
    $fixtures[] = $entry->getPathname();
}

printf("diagnose-leak-spikes: %d fixtures, threshold=%d KB\n\n", count($fixtures), $thresholdKb);

// Warm-up
$renderer = new Renderer((new RendererOptions())->withBaseDir(dirname($fixtures[0])));
$writer = new PdfWriter();
$renderer->renderInto($writer, file_get_contents($fixtures[0]));
unset($renderer, $writer);
gc_collect_cycles();

$startUsed = memory_get_usage(false);
$maxSeenUsed = $startUsed;
$maxSeenAlloc = memory_get_usage(true);
$spikes = [];

printf("%6s %12s %12s %12s  %s\n",
    'idx', 'pre-used(KB)', 'post-used(KB)', 'Δ', 'fixture');
printf("%6s %12s %12s %12s  %s\n",
    str_repeat('-', 6), str_repeat('-', 12), str_repeat('-', 12),
    str_repeat('-', 12), str_repeat('-', 30));

foreach ($fixtures as $i => $path) {
    $pre = memory_get_usage(false);
    try {
        $renderer = new Renderer((new RendererOptions())->withBaseDir(dirname($path)));
        $writer = new PdfWriter();
        $renderer->renderInto($writer, file_get_contents($path));
    } catch (\Throwable $err) {
        unset($renderer, $writer);
        gc_collect_cycles();
        continue;
    }
    $peakDuring = memory_get_peak_usage(false);
    unset($renderer, $writer);
    gc_collect_cycles();
    $post = memory_get_usage(false);
    $alloc = memory_get_usage(true);

    $spikeKb = ($peakDuring - $pre) / 1024;
    $retainedKb = ($post - $pre) / 1024;
    $maxSeenUsed = max($maxSeenUsed, $post);
    $maxSeenAlloc = max($maxSeenAlloc, $alloc);

    if ($spikeKb > $thresholdKb) {
        $rel = substr($path, strlen($root) + 1);
        printf(
            "%6d %12d %12d %+12d  spike=%dKB during render: %s\n",
            $i,
            $pre / 1024,
            $post / 1024,
            $retainedKb,
            (int) $spikeKb,
            $rel,
        );
        $spikes[] = ['idx' => $i, 'spikeKb' => $spikeKb, 'retainedKb' => $retainedKb, 'path' => $rel];
        // Reset peak for next iteration.
        memory_reset_peak_usage();
    }
}

printf("\nFinal: used=%s KB, alloc=%s KB; %d spikes above threshold.\n",
    number_format(memory_get_usage(false) / 1024, 0),
    number_format(memory_get_usage(true) / 1024, 0),
    count($spikes),
);
if ($spikes !== []) {
    usort($spikes, static fn(array $a, array $b): int => $b['spikeKb'] <=> $a['spikeKb']);
    printf("\nTop 10 spikes by transient allocation:\n");
    foreach (array_slice($spikes, 0, 10) as $s) {
        printf("  %6d KB transient, %+6d KB retained — %s\n",
            $s['spikeKb'], $s['retainedKb'], $s['path']);
    }
}
