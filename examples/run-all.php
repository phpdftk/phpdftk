<?php

declare(strict_types=1);

$root = __DIR__;
$scripts = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $name = $file->getFilename();
    if (in_array($name, ['bootstrap.php', 'run-all.php'], true)) {
        continue;
    }
    $scripts[] = $file->getPathname();
}

// Order by top-level directory so producer examples run before consumers:
// writer outputs are consumed by reader/toolkit examples.
$order = ['writer' => 0, 'core' => 1, 'toolkit' => 2, 'reader' => 3];
usort($scripts, function (string $a, string $b) use ($root, $order): int {
    $relA = substr($a, strlen($root) + 1);
    $relB = substr($b, strlen($root) + 1);
    $dirA = explode('/', $relA)[0];
    $dirB = explode('/', $relB)[0];
    $rankA = $order[$dirA] ?? PHP_INT_MAX;
    $rankB = $order[$dirB] ?? PHP_INT_MAX;
    return $rankA <=> $rankB ?: strcmp($relA, $relB);
});

$failed = [];
foreach ($scripts as $script) {
    $relative = substr($script, strlen($root) + 1);
    echo "→ {$relative}\n";
    $exitCode = 0;
    passthru(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($script), $exitCode);
    if ($exitCode !== 0) {
        $failed[] = "{$relative} (exit {$exitCode})";
    }
}

if ($failed !== []) {
    fwrite(STDERR, "\nFailed:\n");
    foreach ($failed as $msg) {
        fwrite(STDERR, "  - {$msg}\n");
    }
    exit(1);
}

echo "\nAll examples ran successfully.\n";
