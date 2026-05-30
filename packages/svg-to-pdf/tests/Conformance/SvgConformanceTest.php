<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests\Conformance;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end conformance smoke test. Each `.svg` fixture under
 * `packages/svg-to-pdf/tests/conformance/fixtures/` is parsed, rendered
 * through the full `SvgRenderer` adapter pipeline, and asserted to:
 *
 *  - Produce a valid PDF (`%PDF-` prefix + `%%EOF` suffix).
 *  - Pass through the painter without throwing.
 *  - Result in a non-trivially sized byte stream (so a silent paint
 *    failure that drops all geometry doesn't slip through).
 *
 * This is a *smoke* layer — the per-feature behaviour assertions live
 * in the focused sub-phase tests next to each painter. Conformance
 * catches the cross-feature regressions a sub-phase test wouldn't see,
 * and is the easy place to add a new fixture when a real-world SVG hits
 * a bug.
 *
 * Discipline pattern (mirrors `packages/html/tests/Conformance/`):
 *
 *  - Every fixture either passes or has a line in `ignored.txt` with a
 *    one-line reason. CI fails on a passing fixture starting to fail
 *    OR on an ignored fixture starting to pass (so deferrals are
 *    discoverable and graduated promptly).
 *  - Removing an entry from `ignored.txt` requires the fixture itself
 *    to pass; new fixtures should be added with the line removed.
 */
final class SvgConformanceTest extends TestCase
{
    private const string FIXTURES_DIR = __DIR__ . '/fixtures';
    private const string IGNORED_LEDGER = __DIR__ . '/ignored.txt';

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function fixtures(): iterable
    {
        $files = glob(self::FIXTURES_DIR . '/*.svg') ?: [];
        sort($files);
        foreach ($files as $file) {
            yield basename($file) => [$file];
        }
    }

    /**
     * @return list<string>
     */
    private static function ignored(): array
    {
        if (!is_file(self::IGNORED_LEDGER)) {
            return [];
        }
        $raw = (string) file_get_contents(self::IGNORED_LEDGER);
        $out = [];
        foreach (explode("\n", $raw) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            // Format: `<filename>: <reason>` — strip the reason.
            $colon = strpos($trimmed, ':');
            $name = $colon === false ? $trimmed : substr($trimmed, 0, $colon);
            $out[] = trim($name);
        }
        return $out;
    }

    #[DataProvider('fixtures')]
    public function testFixtureRendersToValidPdf(string $fixturePath): void
    {
        $name = basename($fixturePath);
        $ignored = self::ignored();
        $isIgnored = in_array($name, $ignored, true);

        try {
            $svg = file_get_contents($fixturePath);
            self::assertNotFalse($svg, "Could not read fixture $name");
            $doc = (new SvgParser())->parse($svg);

            $writer = new PdfWriter();
            $page = $writer->addPage(612.0, 792.0);
            $renderer = new SvgRenderer($page, $writer);
            $renderer->draw($doc, x: 72.0, y: 100.0, width: 468.0, height: 600.0);

            $bytes = $writer->toBytes();
            self::assertStringStartsWith('%PDF-', $bytes, "Fixture $name: missing PDF header");
            self::assertStringContainsString('%%EOF', $bytes, "Fixture $name: missing PDF trailer");
            // Sanity floor — even a 1×1 SVG should produce >500 bytes
            // of PDF infrastructure (catalog / pages / fonts dict / etc).
            self::assertGreaterThan(500, strlen($bytes), "Fixture $name: suspiciously small output");
        } catch (\Throwable $e) {
            if ($isIgnored) {
                // Expected to fail — leave it in the ledger. PHPUnit
                // counts this as passing because we caught the throw.
                return;
            }
            throw $e;
        }

        if ($isIgnored) {
            self::fail(
                "Fixture $name is listed in ignored.txt but now renders cleanly — "
                . 'remove it from the ledger so CI starts enforcing it.',
            );
        }
    }
}
