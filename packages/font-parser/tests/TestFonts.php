<?php

declare(strict_types=1);

namespace Phpdftk\FontParser\Tests;

/**
 * Path resolver for the shared test fonts under tests/fixtures/fonts/ at the
 * repo root. The corpus is shared across phpdftk/font-parser, phpdftk/text,
 * phpdftk/html-to-pdf, phpdftk/svg-to-pdf, and benchmarks so every consumer
 * exercises the same deterministic set of OpenType features.
 *
 * Fonts are SIL OFL 1.1 — see the license files alongside each .otf.
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

    public static function fixturesDir(): string
    {
        return dirname(__DIR__, 3) . '/tests/fixtures/fonts';
    }

    private static function resolve(string $name): string
    {
        $path = self::fixturesDir() . '/' . $name;
        if (!is_file($path)) {
            throw new \RuntimeException(
                "Test font '$name' missing from " . self::fixturesDir(),
            );
        }
        return $path;
    }
}
