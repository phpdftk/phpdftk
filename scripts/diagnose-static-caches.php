<?php

declare(strict_types=1);

/**
 * Localise the cb-sweep memory leak (issue #28 / #29).
 *
 * Renders M *different* fixtures, then walks every loaded
 * `Phpdftk\*` class and inspects its static properties — reporting
 * the count() / strlen() / serialised size of each. Whatever is
 * holding fixture-specific refs across calls will show up here as a
 * static collection that grew once per fixture.
 *
 * Pair with diagnose-leak-mode.php: that one confirms per-call vs
 * per-fixture. This one names the specific class holding the
 * retained refs.
 *
 * Usage:
 *   php scripts/diagnose-static-caches.php [<fixture-count>] [<corpus-root>]
 *
 * Defaults: 200 fixtures from vendor-data/wpt/css/css-borders.
 */

require __DIR__ . '/../vendor/autoload.php';

use Phpdftk\HtmlToPdf\Renderer;
use Phpdftk\HtmlToPdf\RendererOptions;
use Phpdftk\Pdf\Writer\PdfWriter;

$fixtureCount = (int) ($argv[1] ?? 200);
$corpusRoot = $argv[2] ?? __DIR__ . '/../vendor-data/wpt/css';

if (!is_dir($corpusRoot)) {
    fwrite(STDERR, "corpus root not found: $corpusRoot\n");
    exit(2);
}

// Collect fixtures.
$fixtures = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
    $corpusRoot,
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
printf("diagnose-static-caches: walking %d fixtures from %s\n\n",
    count($fixtures), $corpusRoot);

/**
 * Best-effort serialised size of any value. Closures + resources can't
 * be serialised; treat them as zero so the scan doesn't crash.
 */
function valueSize(mixed $v): int
{
    try {
        return strlen(serialize($v));
    } catch (\Throwable) {
        return 0;
    }
}

/**
 * Take a snapshot of every static property on every loaded `Phpdftk\*`
 * class. Returns `class::$prop` => ['count' => int, 'size' => int].
 *
 * @return array<string, array{count: int, size: int}>
 */
function snapshot(): array
{
    $out = [];
    foreach (get_declared_classes() as $class) {
        if (!str_starts_with($class, 'Phpdftk\\')) {
            continue;
        }
        try {
            $rc = new ReflectionClass($class);
        } catch (\Throwable) {
            continue;
        }
        foreach ($rc->getProperties(ReflectionProperty::IS_STATIC) as $prop) {
            $prop->setAccessible(true);
            try {
                if (!$prop->isInitialized()) {
                    continue;
                }
                $val = $prop->getValue();
            } catch (\Throwable) {
                continue;
            }
            $count = is_array($val) ? count($val)
                   : ($val instanceof \Countable ? count($val) : 0);
            $size = valueSize($val);
            $out["$class::\${$prop->getName()}"] = [
                'count' => $count,
                'size' => $size,
            ];
        }
    }
    return $out;
}

// Warm-up render so the autoloader + property registry are loaded
// before we snapshot.
$renderer = new Renderer((new RendererOptions())->withBaseDir(dirname($fixtures[0])));
$writer = new PdfWriter();
$renderer->renderInto($writer, file_get_contents($fixtures[0]));
unset($renderer, $writer);
gc_collect_cycles();

$before = snapshot();
$startUsed = memory_get_usage(false);
$startAlloc = memory_get_usage(true);

$ourErrors = 0;
foreach ($fixtures as $i => $path) {
    try {
        $renderer = new Renderer((new RendererOptions())->withBaseDir(dirname($path)));
        $writer = new PdfWriter();
        $renderer->renderInto($writer, file_get_contents($path));
    } catch (\Throwable) {
        $ourErrors++;
    } finally {
        unset($renderer, $writer);
        gc_collect_cycles();
    }
    if (($i + 1) % 50 === 0) {
        printf("  ... %4d/%-4d  used=%s KB  alloc=%s KB\n",
            $i + 1, count($fixtures),
            number_format(memory_get_usage(false) / 1024, 0),
            number_format(memory_get_usage(true) / 1024, 0));
    }
}

$after = snapshot();
$endUsed = memory_get_usage(false);
$endAlloc = memory_get_usage(true);

// Diff snapshots; sort by `count` growth descending.
$diffs = [];
foreach ($after as $key => $a) {
    $b = $before[$key] ?? ['count' => 0, 'size' => 0];
    $dCount = $a['count'] - $b['count'];
    $dSize = $a['size'] - $b['size'];
    if ($dCount > 0 || $dSize > 1024) {
        $diffs[] = [
            'key' => $key,
            'count' => $a['count'],
            'dCount' => $dCount,
            'size' => $a['size'],
            'dSize' => $dSize,
        ];
    }
}
usort($diffs, static fn(array $a, array $b): int => $b['dCount'] <=> $a['dCount']);

printf("\nMemory:\n");
printf("  used:  %s KB → %s KB (Δ %s KB, %.2f KB/fixture)\n",
    number_format($startUsed / 1024, 0),
    number_format($endUsed / 1024, 0),
    number_format(($endUsed - $startUsed) / 1024, 0),
    ($endUsed - $startUsed) / 1024 / max(count($fixtures), 1),
);
printf("  alloc: %s KB → %s KB (Δ %s KB)\n",
    number_format($startAlloc / 1024, 0),
    number_format($endAlloc / 1024, 0),
    number_format(($endAlloc - $startAlloc) / 1024, 0),
);
printf("Over %d fixtures, %d ours-errors\n\n", count($fixtures), $ourErrors);

if ($diffs === []) {
    echo "No static-property growth detected. The retained refs aren't in `Phpdftk\\*::\$static` form;\n";
    echo "try `weakmap` / `splObjectStorage` instances or DOM holding cycle refs (\$parent <-> \$child).\n";
    exit(0);
}

printf("%-70s %8s %10s %10s\n", 'class::\$static', 'Δcount', 'count', 'size (B)');
echo str_repeat('-', 100) . "\n";
foreach (array_slice($diffs, 0, 30) as $d) {
    printf("%-70s %+8d %10d %10s\n",
        $d['key'],
        $d['dCount'],
        $d['count'],
        number_format($d['size']),
    );
}

if (count($diffs) > 30) {
    printf("\n  (%d more entries below the top 30; raise `--top` to see them)\n", count($diffs) - 30);
}
