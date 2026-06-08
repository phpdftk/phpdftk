<?php

declare(strict_types=1);

/**
 * Bisect the cb-sweep memory leak (issue #28 / #29).
 *
 * Renders the SAME fixture N times in a row, calling
 * `gc_collect_cycles()` between iterations and sampling memory at
 * each step. The shape of the growth curve tells us what KIND of
 * leak we're looking at:
 *
 *   - Constant per-iteration growth → static cache that grows
 *     on every render call (font resolver, name interning,
 *     CSS property registry memo, etc.)
 *
 *   - Initial growth then plateau → one-shot warm-up; not really
 *     a leak in the sweep sense
 *
 *   - No growth → leak is keyed on FIXTURE identity (element
 *     refs, computed-value cache keyed on the element instance,
 *     URL-keyed cache, …); won't show up here but will in the
 *     across-fixture sweep
 *
 * Usage:
 *   php scripts/diagnose-leak-mode.php [<fixture-path>] [<iterations>]
 *
 * Defaults: 100 iterations of a small CSS reftest from the WPT
 * corpus. Each iteration prints memory + delta from the previous,
 * with a 5-step rolling delta to smooth GC noise.
 */

require __DIR__ . '/../vendor/autoload.php';

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Pdf\Writer\PdfWriter;

$fixture = $argv[1] ?? __DIR__ . '/../vendor-data/wpt/css/css-borders/border-radius-001.html';
$iterations = (int) ($argv[2] ?? 100);

if (!is_file($fixture)) {
    fwrite(STDERR, "fixture not found: $fixture\n");
    exit(2);
}

$html = file_get_contents($fixture);
$opts = (new RendererOptions())->withBaseDir(dirname($fixture));

// One warm-up render so subsequent samples don't see the autoloader,
// the property-registry build, etc. as "growth".
$renderer = new Renderer($opts);
$writer = new PdfWriter();
$renderer->renderInto($writer, $html);
unset($renderer, $writer);
gc_collect_cycles();

printf("diagnose-leak-mode\n");
printf("  fixture:    %s\n", $fixture);
printf("  iterations: %d\n", $iterations);
printf("  PHP:        %s\n\n", PHP_VERSION);
// `memory_get_usage(true)` reports the OS-level emalloc chunk total
// (grows when PHP asks for more heap but never shrinks). `(false)`
// reports actual used bytes — what we care about for leak hunting.
// Diverging readings = chunk allocator bookkeeping, not a real leak;
// matching growth = we're actually retaining refs.
printf("%5s  %14s  %14s  %12s  %12s\n",
    'iter', 'used (KB)', 'allocated (KB)', 'Δ used', 'Δ alloc');
printf("%5s  %14s  %14s  %12s  %12s\n",
    str_repeat('-', 5), str_repeat('-', 14), str_repeat('-', 14),
    str_repeat('-', 12), str_repeat('-', 12));

$initial = null;
$samples = [];
for ($i = 0; $i < $iterations; $i++) {
    $renderer = new Renderer($opts);
    $writer = new PdfWriter();
    $renderer->renderInto($writer, $html);
    $bytes = $writer->toBytes();
    unset($renderer, $writer, $bytes);
    gc_collect_cycles();

    $used = memory_get_usage(false);
    $alloc = memory_get_usage(true);
    $initial ??= $used;
    $samples[] = ['used' => $used, 'alloc' => $alloc];

    $deltaUsed = $used - $initial;
    $deltaAlloc = $alloc - ($samples[0]['alloc'] ?? $alloc);

    // Print every iteration up to 10, then every 5th, plus the last.
    if ($i < 10 || $i % 5 === 0 || $i === $iterations - 1) {
        printf(
            "%5d  %14s  %14s  %+12s  %+12s\n",
            $i,
            number_format($used / 1024, 0),
            number_format($alloc / 1024, 0),
            number_format($deltaUsed / 1024, 0),
            number_format($deltaAlloc / 1024, 0),
        );
    }
}

$peakUsed = memory_get_peak_usage(false);
$peakAlloc = memory_get_peak_usage(true);
$final = end($samples);
printf("\n  peak:        used=%s KB  allocated=%s KB\n",
    number_format($peakUsed / 1024, 0),
    number_format($peakAlloc / 1024, 0),
);
printf("  final used:  %s KB\n", number_format($final['used'] / 1024, 0));
printf("  final alloc: %s KB\n", number_format($final['alloc'] / 1024, 0));
printf("  growth (used):  %s KB over %d iterations (%.2f KB/iter)\n",
    number_format(($final['used'] - $initial) / 1024, 0),
    $iterations,
    ($final['used'] - $initial) / 1024 / max($iterations, 1),
);

if (($final['used'] - $initial) > 1024 * 1024) {
    printf("\n  PER-CALL LEAK CONFIRMED — `used` bytes grow even on the same fixture, so something is retaining refs in a static cache or closure.\n");
} elseif (($final['alloc'] - ($samples[0]['alloc'] ?? 0)) > 1024 * 1024) {
    printf("\n  ALLOC grew but USED did not — that's PHP's emalloc chunk allocator expanding without a real ref leak. Not the sweep OOM cause.\n");
} else {
    printf("\n  No per-call growth (Δ < 1 MB used over %d iterations).\n", $iterations);
    printf("  Leak is per-FIXTURE; varies with the corpus.\n");
}
