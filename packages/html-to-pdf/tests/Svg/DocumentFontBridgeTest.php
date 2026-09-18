<?php

declare(strict_types=1);

namespace Phpdftk\HtmlToPdf\Tests\Svg;

use Phpdftk\FontParser\OpenTypeParser;
use Phpdftk\HtmlToPdf\Layout\FontFace;
use Phpdftk\HtmlToPdf\Layout\FontResolver;
use Phpdftk\HtmlToPdf\Svg\DocumentFontBridge;
use Phpdftk\Pdf\Writer\Font as WriterFont;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §11.5 — SVG text selects fonts through CSS Fonts 4, so
 * `<svg><text>` in an HTML document must resolve against the same face
 * set (and the same already-embedded PDF font handles) as the HTML
 * around it.
 */
final class DocumentFontBridgeTest extends TestCase
{
    private const string FONT = __DIR__ . '/../../../../tests/fixtures/fonts/NotoSans-Regular.otf';

    /**
     * @param array<string, list<FontFace>> $faceMap
     * @param array<string, \Phpdftk\Pdf\Core\Font\RegisteredFont> $registered
     */
    private function bridge(array $faceMap, array $registered, ?\Phpdftk\FontParser\FontFaceData $default = null): DocumentFontBridge
    {
        return new DocumentFontBridge(
            new FontResolver(fontMap: [], defaultFont: $default, faceMap: $faceMap),
            $registered,
        );
    }

    private function parseFont(): \Phpdftk\FontParser\FontFaceData
    {
        if (!is_file(self::FONT)) {
            self::markTestSkipped('Latin fixture font missing');
        }
        return (new OpenTypeParser(self::FONT))->parse();
    }

    public function testUnknownFamilyDeclinesSoTheStandard14FallbackKeepsWorking(): void
    {
        $face = $this->parseFont();
        $bridge = $this->bridge(
            ['inter' => [new FontFace($face, weight: 400, style: 'normal')]],
            [$face->postScriptName => new WriterFont('F1', 'Inter')],
            // A default font is deliberately present: an unmatched family
            // must NOT silently collapse onto it, or every generic SVG
            // stack would stop using the standalone resolver.
            default: $face,
        );
        self::assertNull($bridge->resolveDocumentFont(['Comic Unmatched'], null, null));
    }

    public function testEmptyFamilyListDeclines(): void
    {
        $face = $this->parseFont();
        $bridge = $this->bridge(
            ['inter' => [new FontFace($face, weight: 400, style: 'normal')]],
            [$face->postScriptName => new WriterFont('F1', 'Inter')],
        );
        self::assertNull($bridge->resolveDocumentFont([], null, null));
    }

    public function testMatchedFamilyWithNoRegisteredHandleDeclines(): void
    {
        $face = $this->parseFont();
        // Family matches, but the face was never embedded on this page —
        // handing back a phantom handle would emit a `Tf` for a resource
        // that does not exist and the run would paint blank.
        $bridge = $this->bridge(
            ['inter' => [new FontFace($face, weight: 400, style: 'normal')]],
            ['SomeOtherFont' => new WriterFont('F1', 'Other')],
        );
        self::assertNull($bridge->resolveDocumentFont(['Inter'], null, null));
    }

    public function testEmptyRegisteredMapDeclines(): void
    {
        $face = $this->parseFont();
        $bridge = $this->bridge(['inter' => [new FontFace($face, weight: 400, style: 'normal')]], []);
        self::assertNull($bridge->resolveDocumentFont(['Inter'], null, null));
    }

    public function testMatchedFamilyReturnsTheRegisteredHandleAndItsGidMap(): void
    {
        $face = $this->parseFont();
        $handle = new WriterFont('F7', 'Inter', unicodeToGid: [0x41 => 36]);
        $bridge = $this->bridge(
            ['inter' => [new FontFace($face, weight: 400, style: 'normal')]],
            [$face->postScriptName => $handle],
        );
        $resolved = $bridge->resolveDocumentFont(['Inter'], null, null);
        self::assertNotNull($resolved);
        self::assertSame($handle, $resolved->font);
        // Composite fonts have no single-byte encoder, so the GID map has
        // to travel with the handle or `Tj` would emit raw UTF-8 bytes.
        self::assertSame([0x41 => 36], $resolved->unicodeToGid);
    }

    public function testLaterFamilyInTheStackWinsWhenTheFirstIsUnknown(): void
    {
        $face = $this->parseFont();
        $handle = new WriterFont('F2', 'Inter');
        $bridge = $this->bridge(
            ['inter' => [new FontFace($face, weight: 400, style: 'normal')]],
            [$face->postScriptName => $handle],
        );
        $resolved = $bridge->resolveDocumentFont(['Nonexistent', 'Inter'], null, null);
        self::assertNotNull($resolved);
        self::assertSame($handle, $resolved->font);
    }

    /**
     * CSS Fonts 4 §2.3 / §6 — the raw SVG `font-weight` / `font-style`
     * strings have to reach the matcher as the numeric weight axis and
     * the normalised style keyword.
     */
    /**
     * CSS Fonts 4 §2.3 / §6 — the raw SVG `font-weight` / `font-style`
     * strings are keyword or numeric text, not the numeric axis and
     * normalised keyword the layout matcher takes. Every form the SVG
     * accessors can produce has to survive the translation.
     */
    public function testEveryWeightAndStyleFormTheSvgAccessorsProduceStillResolves(): void
    {
        $face = $this->parseFont();
        $handle = new WriterFont('F1', 'Inter');
        $bridge = $this->bridge(
            ['inter' => [
                new FontFace($face, weight: 400, style: 'normal'),
                new FontFace($face, weight: 700, style: 'normal'),
                new FontFace($face, weight: 400, style: 'italic'),
            ]],
            [$face->postScriptName => $handle],
        );
        $forms = [
            [null, null], ['normal', 'normal'], ['bold', null], ['bolder', null],
            ['lighter', null], ['600', null], ['100', null], [' BOLD ', null],
            [null, 'italic'], [null, 'oblique'], ['700', 'italic'],
        ];
        foreach ($forms as [$w, $s]) {
            $resolved = $bridge->resolveDocumentFont(['Inter'], $w, $s);
            self::assertNotNull(
                $resolved,
                'weight=' . var_export($w, true) . ' style=' . var_export($s, true),
            );
            self::assertSame($handle, $resolved->font);
        }
    }

    public function testOutOfRangeNumericWeightIsClampedRatherThanRejected(): void
    {
        $face = $this->parseFont();
        $handle = new WriterFont('F1', 'Inter');
        $bridge = $this->bridge(
            ['inter' => [new FontFace($face, weight: 400, style: 'normal')]],
            [$face->postScriptName => $handle],
        );
        foreach (['0', '-100', '9999', 'not-a-weight'] as $weight) {
            $resolved = $bridge->resolveDocumentFont(['Inter'], $weight, null);
            self::assertNotNull($resolved, "weight=$weight");
        }
    }
}
