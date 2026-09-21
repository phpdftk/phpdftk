<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §7.7 — a `viewBox` with a non-zero `min-x` / `min-y` shifts the
 * origin of the local coordinate system.
 *
 * Exactly ONE layer may apply that shift. `SvgRenderer::draw` folds it
 * into the base matrix it concatenates (`e = x + offsetX - minX * sx`),
 * so when a caller supplies that matrix the Translator must not shift
 * again. When no base matrix is supplied (direct-render callers) the
 * Translator still honours the translation itself.
 */
final class ViewBoxOriginTest extends TestCase
{
    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg"'
        . ' width="340" height="140" viewBox="60000 70000 3400 1400">'
        . '<rect x="60100" y="70100" width="100" height="100" fill="blue"/>'
        . '</svg>';

    private function paint(?array $baseMatrix): string
    {
        $doc = (new SvgParser())->parse(self::SVG);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream, baseMatrix: $baseMatrix);
        return implode("\n", $stream->getOperators());
    }

    /**
     * With a caller-supplied base matrix the origin shift is already
     * applied, so the Translator must NOT emit its own translate.
     * Emitting it moved every non-zero-min viewBox clean off the page.
     */
    public function testBaseMatrixCallerDoesNotGetASecondOriginShift(): void
    {
        $ops = $this->paint([0.1, 0.0, 0.0, -0.1, -6000.0, 7140.0]);
        self::assertStringNotContainsString('-60000', $ops);
        self::assertStringNotContainsString('-70000', $ops);
        // The geometry itself still paints, in viewBox user units.
        self::assertStringContainsString('60100 70100 100 100 re', $ops);
    }

    /**
     * Direct-render callers (no base matrix) keep the translate — it is
     * the only thing anchoring content to the viewBox origin for them.
     */
    public function testDirectRenderStillAppliesTheOriginShift(): void
    {
        $ops = $this->paint(null);
        self::assertStringContainsString('1 0 0 1 -60000 -70000 cm', $ops);
        self::assertStringContainsString('60100 70100 100 100 re', $ops);
    }
}
