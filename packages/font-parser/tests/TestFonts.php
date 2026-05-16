<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

/**
 * Path resolver for the test fonts bundled under tests/fixtures/.
 *
 * These fonts are committed to ensure deterministic coverage of OpenType
 * features (format-12 cmap, vertical metrics, large CFF charsets) that
 * would otherwise depend on whatever fonts happen to be installed on the
 * developer's machine or CI runner.
 *
 * The entire tests/ tree is excluded from the published Composer artifact
 * via packages/font-parser/.gitattributes.
 */
final class TestFonts
{
    public static function notoSansMongolianOtf(): string
    {
        return self::resolve('NotoSansMongolian-Regular.otf');
    }

    public static function notoSansTifinaghOtf(): string
    {
        return self::resolve('NotoSansTifinagh-Regular.otf');
    }

    private static function resolve(string $name): string
    {
        $path = __DIR__ . '/fixtures/' . $name;
        if (!is_file($path)) {
            throw new \RuntimeException(
                "Test font '$name' missing — run `php scripts/fetch-test-fonts.php` to download it",
            );
        }
        return $path;
    }
}
