<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 3N opacity: `opacity`, `fill-opacity`, `stroke-opacity` lower to a
 * `gs` op referencing an `ExtGState` resource registered on the host
 * `Page`. Without a Page reference the painter falls back to fully
 * opaque rendering, so the parser stays usable standalone.
 */
final class OpacityTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    /**
     * Paint with a Page so ExtGState registration is exercised. The
     * resulting writer round-trip also doubles as a smoke test that
     * the emitted gs name resolves at serialise time.
     *
     * @return array{ops: string, bytes: string}
     */
    private function paintWithPage(string $svg): array
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse($svg);
        $this->translator->paint($doc, $stream, $page);
        return [
            'ops' => implode("\n", $stream->getOperators()),
            'bytes' => $writer->toBytes(),
        ];
    }

    public function testOpacityWithoutPageReferenceIsSilentlyIgnored(): void
    {
        // Confirms the no-Page fallback. The shape paints fully
        // opaque — no `gs` op in the stream — but the call still
        // succeeds.
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" opacity="0.5"/></svg>',
        );
        $this->translator->paint($doc, $stream); // no Page argument
        $ops = implode("\n", $stream->getOperators());
        self::assertStringNotContainsString(' gs', $ops);
    }

    public function testElementOpacityEmitsGsAndRegistersExtGState(): void
    {
        $result = $this->paintWithPage(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" opacity="0.5"/></svg>',
        );
        self::assertStringContainsString(' gs', $result['ops']);
        // The PDF body should carry the ExtGState dict — `/ca 0.5` (fill)
        // and `/CA 0.5` (stroke) live in the resource at serialise time.
        self::assertStringContainsString('/ExtGState', $result['bytes']);
        self::assertStringContainsString('/ca 0.5', $result['bytes']);
        self::assertStringContainsString('/CA 0.5', $result['bytes']);
    }

    public function testFullOpacityDoesNotEmitGs(): void
    {
        // `opacity="1"` should round-trip the same as omitting opacity —
        // no `gs` op, no `ExtGState` resource.
        $result = $this->paintWithPage(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" opacity="1"/></svg>',
        );
        self::assertStringNotContainsString(' gs', $result['ops']);
    }

    public function testFillOpacityAndStrokeOpacityCombineWithElementOpacity(): void
    {
        // Effective alpha is per-channel × element opacity. fill-opacity
        // 0.5 × opacity 0.5 = 0.25 for the fill channel; stroke channel
        // is just element opacity 0.5.
        $result = $this->paintWithPage(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" fill="red" stroke="blue" '
            . 'fill-opacity="0.5" opacity="0.5"/></svg>',
        );
        self::assertStringContainsString('/ca 0.25', $result['bytes']);
        self::assertStringContainsString('/CA 0.5', $result['bytes']);
    }

    public function testOpacityWrapsInQQ(): void
    {
        // Opacity is a graphics-state op like `cm`; same scope-leak
        // protection applies — wrap in q/Q so the next shape paints
        // fully opaque.
        $result = $this->paintWithPage(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" opacity="0.5"/>'
            . '<rect width="10" height="10"/>'
            . '</svg>',
        );
        $lines = explode("\n", $result['ops']);
        $qCount = count(array_filter($lines, static fn(string $l): bool => $l === 'q'));
        $bigQCount = count(array_filter($lines, static fn(string $l): bool => $l === 'Q'));
        self::assertSame(1, $qCount);
        self::assertSame(1, $bigQCount);
    }

    public function testIdenticalOpacityReusesSingleExtGState(): void
    {
        // `ensureOpacityState` keys by alpha so two shapes at the
        // same opacity share one resource dict.
        $result = $this->paintWithPage(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" opacity="0.5"/>'
            . '<rect x="20" y="20" width="10" height="10" opacity="0.5"/>'
            . '</svg>',
        );
        // Two gs invocations but exactly one ExtGState dict in
        // resources — the `/ca 0.5` literal should appear once.
        self::assertSame(2, substr_count($result['ops'], ' gs'));
        self::assertSame(1, substr_count($result['bytes'], '/ca 0.5'));
    }
}
