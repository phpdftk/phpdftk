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

sort($scripts);

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
