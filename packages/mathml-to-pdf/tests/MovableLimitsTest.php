<?php

declare(strict_types=1);

namespace Phpdftk\MathmlToPdf\Tests;

use Phpdftk\Mathml\Parser as MathmlParser;
use Phpdftk\MathmlToPdf\MathmlRenderer;
use Phpdftk\Pdf\Writer\PdfWriter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `<mo movablelimits>` + `<math display>` interaction.
 *
 * Per MathML Core §3.3.6.3, when `<munder>` / `<mover>` /
 * `<munderover>` has a base `<mo>` with `movablelimits="true"`:
 *
 *   - In *inline* style (default, or `<math>` without
 *     `display="block"`), limits render as sub/superscripts
 *     attached at the base's right edge.
 *   - In *display* style (`<math display="block">`), limits stay
 *     centred above/below the base — the existing under/over
 *     behaviour.
 *
 * We compare the Td sequences and Tj order across permutations to
 * confirm the painter routes correctly.
 */
final class MovableLimitsTest extends TestCase
{
    public function testMoverWithMovableLimitsInInlineUsesScriptPositioning(): void
    {
        // Default `<math>` is inline. Mover with movablelimits should
        // render the over as a superscript: base then sup at right
        // edge, not centred above. Td sequence should differ from
        // the centred-above case.
        $inline = $this->render(
            '<mover><mo movablelimits="true">' . "\u{2211}" . '</mo><mi>n</mi></mover>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<mover><mo movablelimits="true">' . "\u{2211}" . '</mo><mi>n</mi></mover>',
            displayBlock: true,
        );
        self::assertNotSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
            'inline + movablelimits should produce different Td sequence '
            . 'than display + movablelimits',
        );
    }

    public function testMoverWithoutMovableLimitsUsesOverPositioning(): void
    {
        // Without movablelimits, both modes use the same centred-above
        // placement.
        $inline = $this->render(
            '<mover><mi>x</mi><mo>^</mo></mover>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<mover><mi>x</mi><mo>^</mo></mover>',
            displayBlock: true,
        );
        self::assertSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
        );
    }

    public function testMunderWithMovableLimitsRoutesToSubscriptInInline(): void
    {
        $inline = $this->render(
            '<munder><mo movablelimits="true">' . "\u{2211}" . '</mo><mi>k</mi></munder>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<munder><mo movablelimits="true">' . "\u{2211}" . '</mo><mi>k</mi></munder>',
            displayBlock: true,
        );
        self::assertNotSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
        );
    }

    public function testMunderoverRoutesBothScriptsInInline(): void
    {
        // Both limits route - top as superscript, bottom as
        // subscript. Output differs from display mode.
        $inline = $this->render(
            '<munderover>'
                . '<mo movablelimits="true">' . "\u{2211}" . '</mo>'
                . '<mi>k</mi><mi>n</mi>'
                . '</munderover>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<munderover>'
                . '<mo movablelimits="true">' . "\u{2211}" . '</mo>'
                . '<mi>k</mi><mi>n</mi>'
                . '</munderover>',
            displayBlock: true,
        );
        self::assertNotSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
        );
    }

    public function testMovableLimitsFalseAlwaysUsesOverUnder(): void
    {
        // Explicit `movablelimits="false"` keeps the limits centred
        // in inline mode - same Td sequence as display mode.
        $inline = $this->render(
            '<mover>'
                . '<mo movablelimits="false">' . "\u{2211}" . '</mo>'
                . '<mi>n</mi>'
                . '</mover>',
            displayBlock: false,
        );
        $displayBlock = $this->render(
            '<mover>'
                . '<mo movablelimits="false">' . "\u{2211}" . '</mo>'
                . '<mi>n</mi>'
                . '</mover>',
            displayBlock: true,
        );
        self::assertSame(
            $this->extractTds($inline),
            $this->extractTds($displayBlock),
        );
    }

    private function render(string $innerXml, bool $displayBlock): string
    {
        $displayAttr = $displayBlock ? ' display="block"' : '';
        $xml = '<math xmlns="http://www.w3.org/1998/Math/MathML"'
            . $displayAttr . '>' . $innerXml . '</math>';
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $renderer = new MathmlRenderer($page, $writer);
        $doc = (new MathmlParser())->parse($xml);
        $renderer->draw($doc, x: 72.0, y: 600.0, width: 200.0, height: 30.0);
        return $writer->toBytes();
    }

    /**
     * @return list<array{float, float}>
     */
    private function extractTds(string $bytes): array
    {
        if (!preg_match_all('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+Td\b/', $bytes, $matches)) {
            return [];
        }
        $out = [];
        foreach ($matches[1] as $i => $dx) {
            $out[] = [(float) $dx, (float) $matches[2][$i]];
        }
        return $out;
    }
}
