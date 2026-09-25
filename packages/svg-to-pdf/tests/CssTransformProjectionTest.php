<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Element;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\SvgCascadeProjector;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §6.1 / CSS Transforms 1 — `transform`, `transform-origin` and
 * `transform-box` are CSS properties on SVG elements, not just
 * presentation attributes, so a `<style>` rule has to reach the
 * painter.
 *
 * The three accessors already read through
 * `Element::presentationOrStyle()`; what makes a stylesheet rule
 * visible to them is the cascade projection. These tests pin that
 * wiring end to end, at the emitted `cm` operator.
 */
final class CssTransformProjectionTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="200"'
            . ' viewBox="0 0 300 200">' . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    private function firstRect(string $body): Element
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">' . $body . '</svg>',
        );
        (new SvgCascadeProjector())->project($doc);
        $rect = $doc->findByTag('rect')[0] ?? null;
        self::assertInstanceOf(Element::class, $rect);
        return $rect;
    }

    public function testStylesheetTransformReachesThePainter(): void
    {
        $ops = $this->paint(
            '<style>rect { fill: cyan; transform: translate(29px, 11px); }</style>'
            . '<rect x="140" y="130" width="40" height="10"/>',
        );
        self::assertStringContainsString('1 0 0 1 29 11 cm', $ops);
    }

    public function testStylesheetTransformOriginReachesThePainter(): void
    {
        $rect = $this->firstRect(
            '<style>rect { transform-origin: 30px 10px; transform: scale(2, 3); }</style>'
            . '<rect x="0" y="0" width="40" height="10"/>',
        );
        self::assertSame('30px 10px', $rect->transformOrigin());
    }

    public function testStylesheetTransformBoxReachesThePainter(): void
    {
        $rect = $this->firstRect(
            '<style>rect { transform-box: fill-box; transform: scale(2); }</style>'
            . '<rect x="0" y="0" width="40" height="10"/>',
        );
        self::assertSame('fill-box', $rect->transformBox());
    }

    public function testUndeclaredTransformOriginStaysNull(): void
    {
        // Guard. `transform-origin`'s registered CSS initial value is
        // `50% 50%`, but an SVG element has no CSS layout box and
        // pivots on the user-space origin instead. Projecting the
        // registry initial onto every element would silently move every
        // rotation and scale in every document, so the projection must
        // fire only on a value a DECLARATION won.
        $rect = $this->firstRect(
            '<style>rect { fill: cyan; }</style>'
            . '<rect x="0" y="0" width="40" height="10" transform="scale(2)"/>',
        );
        self::assertNull($rect->transformOrigin());
        self::assertNull($rect->transformBox());
    }

    public function testUndeclaredTransformStaysNull(): void
    {
        // Same guard for `transform` itself: the initial value is
        // `none`, and stamping it everywhere would be noise in the
        // projected style at best.
        $rect = $this->firstRect(
            '<style>rect { fill: cyan; }</style><rect width="10" height="10"/>',
        );
        self::assertNull($rect->transform());
        self::assertStringNotContainsString(
            'transform',
            $rect->getAttribute('style') ?? '',
        );
    }

    public function testTransformAttributeOnTheElementIsNotOverwritten(): void
    {
        // The projector's standing rule: a value the author put on the
        // element itself still reaches the painter first.
        $rect = $this->firstRect(
            '<style>rect { transform: translate(5px, 5px); }</style>'
            . '<rect width="10" height="10" transform="translate(100, 100)"/>',
        );
        $transform = $rect->transform();
        self::assertNotNull($transform);
        $m = $transform->toMatrix();
        self::assertEqualsWithDelta(100.0, $m[4], 1.0e-9);
        self::assertEqualsWithDelta(100.0, $m[5], 1.0e-9);
    }
}
