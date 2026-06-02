<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Filter\FeBlend;
use Phpdftk\Svg\Filter\FeColorMatrix;
use Phpdftk\Svg\Filter\FeComposite;
use Phpdftk\Svg\Filter\FeDropShadow;
use Phpdftk\Svg\Filter\FeFlood;
use Phpdftk\Svg\Filter\FeGaussianBlur;
use Phpdftk\Svg\Filter\FeMerge;
use Phpdftk\Svg\Filter\FeMergeNode;
use Phpdftk\Svg\Filter\FeMorphology;
use Phpdftk\Svg\Filter\FeOffset;
use Phpdftk\Svg\Parser;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 Filter Effects §15 — typed filter-primitive accessors.
 */
final class FilterPrimitiveTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    private function firstChild(string $primitive): mixed
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><filter id="f">' . $primitive . '</filter></svg>',
        );
        return $doc->children[0]->children[0];
    }

    public function testGaussianBlurIsotropic(): void
    {
        $blur = $this->firstChild('<feGaussianBlur stdDeviation="3"/>');
        self::assertInstanceOf(FeGaussianBlur::class, $blur);
        self::assertSame([3.0, 3.0], $blur->stdDeviation());
    }

    public function testGaussianBlurAnisotropic(): void
    {
        $blur = $this->firstChild('<feGaussianBlur stdDeviation="3 1"/>');
        self::assertSame([3.0, 1.0], $blur->stdDeviation());
        self::assertSame('duplicate', $blur->edgeMode());
    }

    public function testGaussianBlurEdgeMode(): void
    {
        $blur = $this->firstChild('<feGaussianBlur stdDeviation="3" edgeMode="wrap"/>');
        self::assertSame('wrap', $blur->edgeMode());
    }

    public function testOffsetAccessors(): void
    {
        $off = $this->firstChild('<feOffset dx="5" dy="-3" in="SourceAlpha" result="offset"/>');
        self::assertInstanceOf(FeOffset::class, $off);
        self::assertSame(5.0, $off->dx());
        self::assertSame(-3.0, $off->dy());
        self::assertSame('SourceAlpha', $off->in());
        self::assertSame('offset', $off->result());
    }

    public function testFloodAccessors(): void
    {
        $flood = $this->firstChild('<feFlood flood-color="red" flood-opacity="0.5"/>');
        self::assertInstanceOf(FeFlood::class, $flood);
        self::assertSame('red', $flood->floodColor());
        self::assertSame(0.5, $flood->floodOpacity());
    }

    public function testFloodDefaults(): void
    {
        $flood = $this->firstChild('<feFlood/>');
        self::assertSame('black', $flood->floodColor());
        self::assertSame(1.0, $flood->floodOpacity());
    }

    public function testBlendMode(): void
    {
        $blend = $this->firstChild('<feBlend mode="multiply" in="A" in2="B"/>');
        self::assertInstanceOf(FeBlend::class, $blend);
        self::assertSame('multiply', $blend->mode());
        self::assertSame('A', $blend->in());
        self::assertSame('B', $blend->in2());
    }

    public function testBlendUnknownModeFallsBackToNormal(): void
    {
        $blend = $this->firstChild('<feBlend mode="bogus"/>');
        self::assertSame('normal', $blend->mode());
    }

    public function testCompositeArithmetic(): void
    {
        $comp = $this->firstChild('<feComposite operator="arithmetic" k1="1" k2="2" k3="3" k4="4"/>');
        self::assertInstanceOf(FeComposite::class, $comp);
        self::assertSame('arithmetic', $comp->operator());
        self::assertSame(1.0, $comp->k1());
        self::assertSame(2.0, $comp->k2());
        self::assertSame(3.0, $comp->k3());
        self::assertSame(4.0, $comp->k4());
    }

    public function testMorphology(): void
    {
        $morph = $this->firstChild('<feMorphology operator="dilate" radius="2 4"/>');
        self::assertInstanceOf(FeMorphology::class, $morph);
        self::assertSame('dilate', $morph->operator());
        self::assertSame([2.0, 4.0], $morph->radius());
    }

    public function testMergeAndMergeNode(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><filter id="f">'
            . '<feMerge><feMergeNode in="shadow"/><feMergeNode in="SourceGraphic"/></feMerge>'
            . '</filter></svg>',
        );
        $merge = $doc->children[0]->children[0];
        self::assertInstanceOf(FeMerge::class, $merge);
        self::assertCount(2, $merge->children);
        self::assertInstanceOf(FeMergeNode::class, $merge->children[0]);
        self::assertSame('shadow', $merge->children[0]->in());
        self::assertSame('SourceGraphic', $merge->children[1]->in());
    }

    public function testColorMatrixTypeAndValues(): void
    {
        $cm = $this->firstChild('<feColorMatrix type="saturate" values="0.5"/>');
        self::assertInstanceOf(FeColorMatrix::class, $cm);
        self::assertSame('saturate', $cm->type());
        self::assertSame([0.5], $cm->values());
    }

    public function testColorMatrixCanonicalisesTypeKeyword(): void
    {
        $cm = $this->firstChild('<feColorMatrix type="luminanceToAlpha"/>');
        self::assertSame('luminanceToAlpha', $cm->type());
        // lowercase too.
        $cm2 = $this->firstChild('<feColorMatrix type="huerotate" values="45"/>');
        self::assertSame('hueRotate', $cm2->type());
    }

    public function testDropShadowAccessors(): void
    {
        $shadow = $this->firstChild(
            '<feDropShadow dx="3" dy="4" stdDeviation="2 1" '
            . 'flood-color="rgba(0,0,0,0.5)" flood-opacity="0.7"/>',
        );
        self::assertInstanceOf(FeDropShadow::class, $shadow);
        self::assertSame(3.0, $shadow->dx());
        self::assertSame(4.0, $shadow->dy());
        self::assertSame([2.0, 1.0], $shadow->stdDeviation());
        self::assertSame('rgba(0,0,0,0.5)', $shadow->floodColor());
        self::assertSame(0.7, $shadow->floodOpacity());
    }

    public function testPrimitiveSubregionAttributes(): void
    {
        $blur = $this->firstChild('<feGaussianBlur x="10" y="20" width="100" height="50" stdDeviation="3"/>');
        self::assertSame(10.0, $blur->x());
        self::assertSame(20.0, $blur->y());
        self::assertSame(100.0, $blur->width());
        self::assertSame(50.0, $blur->height());
    }
}
