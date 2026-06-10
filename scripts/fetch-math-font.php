<?php

declare(strict_types=1);

/**
 * Download Latin Modern Math (the production math font the MathML
 * painter uses when font-derived MathConstants are wanted at draw
 * time).
 *
 * The font is OFL/GUST-licensed and freely redistributable but
 * isn't checked into the repo to keep the working tree small. This
 * script fetches it on demand and lands it at:
 *
 *   packages/mathml-to-pdf/tests/fixtures/fonts/latinmodern-math.otf
 *
 * That path is git-ignored so accidental commits don't sneak in.
 *
 * Tests under packages/mathml-to-pdf/tests/ that need a real math
 * font skip themselves when the file is absent, so this script is
 * optional for everyday development. Run it once to enable the
 * production-font tests + benchmarks:
 *
 *   composer fetch:math-font
 *   # or
 *   php scripts/fetch-math-font.php
 *
 * URL override: set MATH_FONT_URL to fetch a different mirror.
 *
 * Checksum policy:
 *   - If a hash is recorded below, mismatched files are rejected.
 *   - Otherwise the script accepts the download and prints the
 *     computed hash so a future revision can pin it.
 */

$root = dirname(__DIR__);
$fixturesDir = $root . '/packages/mathml-to-pdf/tests/fixtures/fonts';
$outFile = $fixturesDir . '/latinmodern-math.otf';

$url = $_SERVER['MATH_FONT_URL']
    ?? getenv('MATH_FONT_URL')
    ?: 'https://mirror.ctan.org/fonts/lm-math/opentype/latinmodern-math.otf';

// Pin this when a known-good build is settled on. Leave empty to
// accept any download and surface the computed hash for review.
$expectedSha256 = '';

if (!is_dir($fixturesDir)) {
    mkdir($fixturesDir, 0755, true);
}

if (is_file($outFile)) {
    $sha = hash_file('sha256', $outFile);
    if ($expectedSha256 === '' || $sha === $expectedSha256) {
        echo "Latin Modern Math already present at $outFile (sha256=$sha)\n";
        exit(0);
    }
    fwrite(STDERR, "Existing file mismatches expected hash; re-downloading.\n");
    unlink($outFile);
}

echo "Fetching Latin Modern Math from $url\n";
$bytes = @file_get_contents($url);
if ($bytes === false) {
    fwrite(STDERR, "Download failed: $url\n");
    fwrite(STDERR, "Set MATH_FONT_URL to a known-good mirror and retry.\n");
    exit(1);
}

// Sanity-check the magic bytes - OpenType CFF starts with 'OTTO'.
$magic = substr($bytes, 0, 4);
if ($magic !== 'OTTO' && $magic !== "\x00\x01\x00\x00") {
    fwrite(STDERR, sprintf(
        "Downloaded file doesn't look like an OpenType font (magic=0x%s).\n",
        bin2hex($magic),
    ));
    exit(1);
}

file_put_contents($outFile, $bytes);
$sha = hash_file('sha256', $outFile);
$size = filesize($outFile);
echo "Saved $outFile (" . number_format((int) $size) . " bytes)\n";
echo "SHA-256: $sha\n";

if ($expectedSha256 !== '' && $sha !== $expectedSha256) {
    fwrite(STDERR, "Hash mismatch:\n  got      $sha\n  expected $expectedSha256\n");
    unlink($outFile);
    exit(1);
}
if ($expectedSha256 === '') {
    echo "No expected hash pinned in this script; review the download "
        . "and update \$expectedSha256 in scripts/fetch-math-font.php "
        . "to lock it in.\n";
}
