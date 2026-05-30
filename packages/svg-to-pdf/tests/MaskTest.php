<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 3R+8 — `<mask>` painter. `mask="url(#id)"` lookups create a
 * transparency Form XObject containing the mask's painted children,
 * wrap it in a `/SMask` `ExtGState`, attach to the page, and emit
 * `gs <maskName>` in the q/Q wrap around the masked element.
 */
final class MaskTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    /**
     * @return array{ops: string, bytes: string}
     */
    private function paint(string $svg): array
    {
        $writer = new PdfWriter(compressStreams: false);
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse($svg);
        $this->translator->paint($doc, $stream, $page, $writer);
        return [
            'ops' => implode("\n", $stream->getOperators()),
            'bytes' => $writer->toBytes(),
        ];
    }

    public function testMaskedElementEmitsGsAndRegistersFormXObject(): void
    {
        $result = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs>'
            . '<mask id="m" maskContentUnits="userSpaceOnUse">'
            . '<rect x="10" y="10" width="80" height="80" fill="white"/>'
            . '</mask>'
            . '</defs>'
            . '<rect width="100" height="100" fill="red" mask="url(#m)"/>'
            . '</svg>',
        );
        // gs op invoked in the wrap around the masked rect.
        self::assertMatchesRegularExpression('!/GS_mask_\d+ gs!', $result['ops']);
        // PDF body should carry the Form XObject with a transparency
        // group + DeviceGray colour space.
        self::assertStringContainsString('/Subtype /Form', $result['bytes']);
        self::assertStringContainsString('/S /Transparency', $result['bytes']);
        self::assertStringContainsString('/CS /DeviceGray', $result['bytes']);
        // SoftMask dict referencing the Form XObject via /G.
        self::assertStringContainsString('/Type /Mask', $result['bytes']);
        self::assertStringContainsString('/S /Luminosity', $result['bytes']);
        // BC = [0] so outside the mask region the backdrop is black
        // (hidden) — matches SVG 2 §14.5's "outside the mask region
        // the alpha is 0".
        self::assertStringContainsString('/BC [ 0 ]', $result['bytes']);
    }

    public function testMaskBBoxMatchesMaskedElementBounds(): void
    {
        // The form's /BBox should enclose the masked rect (0, 0) ..
        // (100, 50) so the soft mask covers it.
        $result = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><mask id="m" maskContentUnits="userSpaceOnUse">'
            . '<rect width="100" height="50" fill="white"/>'
            . '</mask></defs>'
            . '<rect width="100" height="50" fill="red" mask="url(#m)"/>'
            . '</svg>',
        );
        self::assertStringContainsString('/BBox [ 0 0 100 50 ]', $result['bytes']);
    }

    public function testMaskContentUnitsObjectBoundingBoxAppliesBboxMatrix(): void
    {
        // maskContentUnits=objectBoundingBox reifies the mask's
        // `width="1" height="1"` rect to the masked element's bbox
        // 50×30 at (10, 20) via a `cm 50 0 0 30 10 20`. (The SVG 2
        // default for maskContentUnits is userSpaceOnUse, so the
        // attribute is explicit here.)
        $result = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><mask id="m" maskContentUnits="objectBoundingBox">'
            . '<rect width="1" height="1" fill="white"/>'
            . '</mask></defs>'
            . '<rect x="10" y="20" width="50" height="30" fill="red" mask="url(#m)"/>'
            . '</svg>',
        );
        // The cm shows up in the FormXObject's content stream rather
        // than the page stream — assert against the full bytes.
        self::assertStringContainsString('50 0 0 30 10 20 cm', $result['bytes']);
    }

    public function testMissingMaskFallsBackToNoMask(): void
    {
        $result = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" fill="red" mask="url(#nope)"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString(' gs', $result['ops']);
        self::assertStringNotContainsString('/Subtype /Form', $result['bytes']);
        // The element still paints, just unmasked.
        self::assertStringContainsString('0 0 10 10 re', $result['ops']);
    }

    public function testMaskNoneKeywordIsTreatedAsAbsent(): void
    {
        $result = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" fill="red" mask="none"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString(' gs', $result['ops']);
    }

    public function testMalformedMaskReferenceFallsBackGracefully(): void
    {
        $result = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="10" height="10" fill="red" mask="not-a-url"/>'
            . '</svg>',
        );
        self::assertStringNotContainsString(' gs', $result['ops']);
        self::assertStringContainsString('0 0 10 10 re', $result['ops']);
    }

    public function testMaskDoesNotPaintItsChildrenAtDocumentLevel(): void
    {
        // Top-level `<mask>` (no `<defs>` wrapper) must skip painting —
        // it's a referenceable container like `<defs>` and `<clipPath>`.
        $result = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<mask id="m"><rect width="50" height="50" fill="white"/></mask>'
            . '<rect x="60" y="0" width="40" height="40" fill="blue"/>'
            . '</svg>',
        );
        // Only the blue rect should appear in the page content stream —
        // the mask's white rect lives in the FormXObject instead, not
        // the page (since nothing references the mask).
        self::assertStringContainsString('60 0 40 40 re', $result['ops']);
        // Without a reference no FormXObject is created.
        self::assertStringNotContainsString('/Subtype /Form', $result['bytes']);
    }

    public function testMaskWithTransformAndOpacityComposeInSameWrap(): void
    {
        // Confirms mask wraps alongside transform / opacity in the same
        // q/Q rather than nesting an extra pair.
        $result = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><mask id="m" maskContentUnits="userSpaceOnUse">'
            . '<rect width="10" height="10" fill="white"/>'
            . '</mask></defs>'
            . '<rect width="10" height="10" fill="red" '
            . 'transform="translate(5, 5)" mask="url(#m)"/>'
            . '</svg>',
        );
        $lines = explode("\n", $result['ops']);
        $qCount = count(array_filter($lines, static fn(string $l): bool => $l === 'q'));
        $bigQCount = count(array_filter($lines, static fn(string $l): bool => $l === 'Q'));
        self::assertSame(1, $qCount);
        self::assertSame(1, $bigQCount);
        self::assertStringContainsString('1 0 0 1 5 5 cm', $result['ops']);
    }

    public function testMaskFormXObjectContainsTheMaskChildrenPaintOps(): void
    {
        // The mask's <rect fill="white"> needs to produce a fill op in
        // the FormXObject's content stream so the luminance reaches 1.
        $result = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><mask id="m" maskContentUnits="userSpaceOnUse">'
            . '<rect width="100" height="50" fill="white"/>'
            . '</mask></defs>'
            . '<rect width="100" height="50" fill="red" mask="url(#m)"/>'
            . '</svg>',
        );
        // White fill in the mask = `1 1 1 rg`, and the rect's path is
        // emitted as `0 0 100 50 re` inside the form's content stream.
        self::assertStringContainsString('1 1 1 rg', $result['bytes']);
        // The page stream's red fill of the masked rect is also present.
        self::assertStringContainsString('1 0 0 rg', $result['bytes']);
    }
}
