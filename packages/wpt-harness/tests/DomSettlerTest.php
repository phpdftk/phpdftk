<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\DomSettler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the DOM settler.
 *
 * The end-to-end behaviour (actually launching Playwright +
 * Chromium and dumping a settled HTML) is not exercised here -
 * it's a network of subprocesses with its own bootstrap. These
 * tests cover the PHP-side wiring: detection of the reftest-wait
 * trigger, the missing-script fallback, and the cache key shape.
 */
final class DomSettlerTest extends TestCase
{
    public function testNeedsSettlingDetectsReftestWaitClass(): void
    {
        $settler = $this->settler();

        self::assertTrue($settler->needsSettling(
            '<!DOCTYPE html><html class="reftest-wait"></html>',
        ));
        self::assertTrue($settler->needsSettling(
            '<!DOCTYPE html><html class="other reftest-wait"></html>',
        ));
        self::assertTrue($settler->needsSettling(
            "<!DOCTYPE html><html class='reftest-wait'></html>",
        ));
        // Whitespace between the leading "class=" and the value.
        self::assertTrue($settler->needsSettling(
            '<!DOCTYPE html><html  class = "reftest-wait" ></html>',
        ));
    }

    public function testNeedsSettlingRejectsUnrelatedDocuments(): void
    {
        $settler = $this->settler();

        self::assertFalse($settler->needsSettling(
            '<!DOCTYPE html><html></html>',
        ));
        self::assertFalse($settler->needsSettling(
            '<!DOCTYPE html><html class="not-reftest"></html>',
        ));
        // The string "reftest-wait" appears in text, not in the
        // <html> class attribute.
        self::assertFalse($settler->needsSettling(
            '<!DOCTYPE html><html><body>reftest-wait keyword</body></html>',
        ));
    }

    public function testMaybeSettleSkipsFixturesWithoutReftestWait(): void
    {
        $settler = $this->settler();

        $result = $settler->maybeSettle(
            '/tmp/no-such-file.html',
            '<!DOCTYPE html><html></html>',
        );

        self::assertNull(
            $result,
            'no reftest-wait class -> no settling, return null',
        );
    }

    public function testMaybeSettleReturnsNullWhenScriptMissing(): void
    {
        // Point at a non-existent script - the settler should
        // gracefully return null rather than throwing.
        $cacheDir = sys_get_temp_dir() . '/phpdftk-settler-test-' . uniqid();
        $settler = new DomSettler(
            scriptPath: '/var/empty/no-such-script.mjs',
            cacheDir: $cacheDir,
            corpusRoot: '/var/empty',
        );

        $result = $settler->maybeSettle(
            '/var/empty/test.html',
            '<!DOCTYPE html><html class="reftest-wait"></html>',
        );

        self::assertNull($result);
        // No cache directory should have been created either.
        self::assertDirectoryDoesNotExist($cacheDir);
    }

    public function testCachedSettledHtmlIsReturnedDirectly(): void
    {
        $cacheDir = sys_get_temp_dir() . '/phpdftk-settler-cache-' . uniqid();
        mkdir($cacheDir, recursive: true);
        try {
            $fixture = '<!DOCTYPE html><html class="reftest-wait"></html>';
            $cacheKey = hash('sha256', $fixture);
            $cached = '<!DOCTYPE html><html class=""><body>cached</body></html>';
            file_put_contents(
                $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.html',
                $cached,
            );

            $settler = new DomSettler(
                scriptPath: '/var/empty/no-such-script.mjs',
                cacheDir: $cacheDir,
                corpusRoot: '/var/empty',
            );

            $result = $settler->maybeSettle(
                '/var/empty/test.html',
                $fixture,
            );

            self::assertSame(
                $cached,
                $result,
                'cache hit returns settled HTML without invoking script',
            );
        } finally {
            $this->rmrf($cacheDir);
        }
    }

    private function settler(): DomSettler
    {
        return new DomSettler(
            scriptPath: '/var/empty/no-such-script.mjs',
            cacheDir: sys_get_temp_dir() . '/phpdftk-settler-test',
            corpusRoot: '/var/empty',
        );
    }

    private function rmrf(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($child)) {
                $this->rmrf($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($path);
    }
}
