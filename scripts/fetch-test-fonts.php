<?php

declare(strict_types=1);

/**
 * Download checked-in test fonts from their canonical upstream sources.
 *
 * Test fonts live in packages/font-parser/tests/fixtures/ and are excluded
 * from the Composer dist archive via .gitattributes. This script lets us
 * refresh them deterministically: pinned versions, pinned SHA-256s.
 */

$root = dirname(__DIR__);
$fixturesDir = $root . '/packages/font-parser/tests/fixtures';

$fonts = [
    [
        'name' => 'NotoSansMongolian-Regular.otf',
        'url' => 'https://github.com/notofonts/mongolian/releases/download/NotoSansMongolian-v3.002/NotoSansMongolian-v3.002.zip',
        'pathInZip' => 'NotoSansMongolian/unhinted/otf/NotoSansMongolian-Regular.otf',
        'licensePathInZip' => 'OFL.txt',
        'licenseFile' => 'NotoSansMongolian-OFL.txt',
        'sha256' => 'b902038425c40d24d8e4ba3843fe7a40b254381196b7356c22207eb88aa9121a',
    ],
    [
        'name' => 'NotoSansTifinagh-Regular.otf',
        'url' => 'https://github.com/notofonts/tifinagh/releases/download/NotoSansTifinagh-v2.006/NotoSansTifinagh-v2.006.zip',
        'pathInZip' => 'NotoSansTifinagh/unhinted/slim-otf/NotoSansTifinagh-Regular.otf',
        'licensePathInZip' => 'OFL.txt',
        'licenseFile' => 'NotoSansTifinagh-OFL.txt',
        'sha256' => '8396ced3cf679a82b5ac2f18639b8a5676b8cd25cfaa833adea8dacbd5a97052',
    ],
];

if (!is_dir($fixturesDir)) {
    mkdir($fixturesDir, 0755, true);
}

$failures = [];

foreach ($fonts as $font) {
    $outFile = $fixturesDir . '/' . $font['name'];
    echo "→ {$font['name']}\n";

    if (is_file($outFile) && hash_file('sha256', $outFile) === $font['sha256']) {
        echo "  already up to date (sha256 verified)\n";
        continue;
    }

    $tmpZip = tempnam(sys_get_temp_dir(), 'phpdftk_font_') . '.zip';
    $extractDir = tempnam(sys_get_temp_dir(), 'phpdftk_font_extract_');
    @unlink($extractDir);
    mkdir($extractDir);

    try {
        echo "  fetching {$font['url']}\n";
        $bytes = @file_get_contents($font['url']);
        if ($bytes === false) {
            $failures[] = $font['name'] . ': download failed';
            continue;
        }
        file_put_contents($tmpZip, $bytes);

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            $failures[] = $font['name'] . ': zip open failed';
            continue;
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $sourcePath = $extractDir . '/' . $font['pathInZip'];
        if (!is_file($sourcePath)) {
            $failures[] = $font['name'] . ": '{$font['pathInZip']}' not found inside zip";
            continue;
        }

        $sha = hash_file('sha256', $sourcePath);
        if ($sha !== $font['sha256']) {
            $failures[] = $font['name'] . ": sha256 mismatch (got $sha, expected {$font['sha256']})";
            continue;
        }

        copy($sourcePath, $outFile);
        echo "  installed → $outFile\n";

        if (isset($font['licensePathInZip'])) {
            $licSource = $extractDir . '/' . $font['licensePathInZip'];
            $licOut = $fixturesDir . '/' . $font['licenseFile'];
            if (is_file($licSource)) {
                copy($licSource, $licOut);
                echo "  license  → $licOut\n";
            }
        }
    } finally {
        @unlink($tmpZip);
        // Best-effort cleanup of the temp extract dir
        if (is_dir($extractDir)) {
            $rii = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($rii as $f) {
                $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
            }
            rmdir($extractDir);
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "\nFailures:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - $f\n");
    }
    exit(1);
}

echo "\nAll fonts up to date.\n";
