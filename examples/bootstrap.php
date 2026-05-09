<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

const EXAMPLES_OUTPUT_DIR = __DIR__ . '/../docs/site/public/samples';

if (!is_dir(EXAMPLES_OUTPUT_DIR)) {
    mkdir(EXAMPLES_OUTPUT_DIR, 0o755, true);
}

function example_output_path(string $relative): string
{
    $full = EXAMPLES_OUTPUT_DIR . '/' . ltrim($relative, '/');
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0o755, true);
    }
    return $full;
}
