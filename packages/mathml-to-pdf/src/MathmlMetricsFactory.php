<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf;

use Phpdftk\FontParser\MathConstantsParser;
use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\FontParser\WoffParser;

/**
 * Builds {@see MathmlMetrics} from an OpenType MATH-table font.
 *
 * Reads either a raw OTF (`.otf` / `.ttf`) or a WOFF1 wrapper
 * (`.woff`), parses the MATH sub-table, and surfaces the
 * MathConstants through the adapter.
 *
 * Throws when:
 *   - the file isn't a parseable OpenType CFF font,
 *   - the font has no MATH table,
 *   - the MathConstants sub-table is missing or truncated.
 *
 * Callers that want a graceful fallback (e.g. "use math metrics if
 * the font is available, otherwise standard-font defaults") should
 * try/catch around the factory and pass null to MathmlRenderer when
 * the font isn't usable.
 */
final class MathmlMetricsFactory
{
    /**
     * Load + parse a math font and return a populated
     * MathmlMetrics. The painter's unitsPerEm scaling tracks the
     * font's reported value so values come out in em correctly.
     */
    public static function fromMathFont(string $path): MathmlMetrics
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'woff') {
            $fontBytes = WoffParser::decompress($path);
            $data = OpenTypeParser::fromBytes($fontBytes)->parse();
        } else {
            // OTF / TTF flat-file - parse directly.
            $data = (new OpenTypeParser($path))->parse();
        }

        if ($data->mathTable === null) {
            throw new \RuntimeException(
                "Font at $path has no MATH table - cannot build math metrics from it",
            );
        }
        if (!$data->mathTable->hasMathConstants()) {
            throw new \RuntimeException(
                "Font at $path has a MATH table without MathConstants",
            );
        }
        $constants = (new MathConstantsParser())
            ->parse($data->mathTable->mathConstantsBytes);

        return new MathmlMetrics(
            constants: $constants,
            unitsPerEm: $data->unitsPerEm,
        );
    }
}
