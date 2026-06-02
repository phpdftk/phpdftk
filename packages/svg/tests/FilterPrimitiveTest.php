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

    public function testTurbulenceAccessors(): void
    {
        $t = $this->firstChild(
            '<feTurbulence baseFrequency="0.05 0.1" numOctaves="3" seed="42" '
            . 'stitchTiles="stitch" type="fractalNoise"/>',
        );
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeTurbulence::class, $t);
        self::assertSame([0.05, 0.1], $t->baseFrequency());
        self::assertSame(3, $t->numOctaves());
        self::assertSame(42.0, $t->seed());
        self::assertSame('stitch', $t->stitchTiles());
        self::assertSame('fractalNoise', $t->type());
    }

    public function testTurbulenceDefaults(): void
    {
        $t = $this->firstChild('<feTurbulence/>');
        self::assertSame([0.0, 0.0], $t->baseFrequency());
        self::assertSame(1, $t->numOctaves());
        self::assertSame('noStitch', $t->stitchTiles());
        self::assertSame('turbulence', $t->type());
    }

    public function testImageAccessors(): void
    {
        $img = $this->firstChild(
            '<feImage href="https://example.com/x.png" preserveAspectRatio="xMidYMid meet"/>',
        );
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeImage::class, $img);
        self::assertSame('https://example.com/x.png', $img->href());
        self::assertSame('xMidYMid meet', $img->preserveAspectRatio());
    }

    public function testImageLegacyXlinkHref(): void
    {
        $img = $this->firstChild('<feImage xlink:href="local.png"/>');
        self::assertSame('local.png', $img->href());
    }

    public function testTileIsTypedPrimitive(): void
    {
        $tile = $this->firstChild('<feTile in="src" result="tiled"/>');
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeTile::class, $tile);
        self::assertSame('src', $tile->in());
        self::assertSame('tiled', $tile->result());
    }

    public function testDisplacementMapAccessors(): void
    {
        $dm = $this->firstChild(
            '<feDisplacementMap in="A" in2="noise" scale="20" '
            . 'xChannelSelector="R" yChannelSelector="G"/>',
        );
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeDisplacementMap::class, $dm);
        self::assertSame('A', $dm->in());
        self::assertSame('noise', $dm->in2());
        self::assertSame(20.0, $dm->scale());
        self::assertSame('R', $dm->xChannelSelector());
        self::assertSame('G', $dm->yChannelSelector());
    }

    public function testDisplacementMapDefaults(): void
    {
        $dm = $this->firstChild('<feDisplacementMap/>');
        self::assertSame(0.0, $dm->scale());
        self::assertSame('A', $dm->xChannelSelector());
        self::assertSame('A', $dm->yChannelSelector());
    }

    public function testConvolveMatrixAccessors(): void
    {
        $cm = $this->firstChild(
            '<feConvolveMatrix order="3" kernelMatrix="0 -1 0 -1 5 -1 0 -1 0" '
            . 'divisor="1" bias="0" targetX="1" targetY="1" edgeMode="wrap" preserveAlpha="true"/>',
        );
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeConvolveMatrix::class, $cm);
        self::assertSame([3, 3], $cm->order());
        self::assertSame([0.0, -1.0, 0.0, -1.0, 5.0, -1.0, 0.0, -1.0, 0.0], $cm->kernelMatrix());
        self::assertSame(1.0, $cm->divisor());
        self::assertSame(0.0, $cm->bias());
        self::assertSame(1, $cm->targetX());
        self::assertSame(1, $cm->targetY());
        self::assertSame('wrap', $cm->edgeMode());
        self::assertTrue($cm->preserveAlpha());
    }

    public function testDiffuseLightingWithDistantLight(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><filter id="f">'
            . '<feDiffuseLighting lighting-color="#ffcc88" surfaceScale="2" diffuseConstant="1.5">'
            . '<feDistantLight azimuth="45" elevation="60"/>'
            . '</feDiffuseLighting>'
            . '</filter></svg>',
        );
        $diffuse = $doc->children[0]->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeDiffuseLighting::class, $diffuse);
        self::assertSame('#ffcc88', $diffuse->lightingColor());
        self::assertSame(2.0, $diffuse->surfaceScale());
        self::assertSame(1.5, $diffuse->diffuseConstant());
        $light = $diffuse->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeDistantLight::class, $light);
        self::assertSame(45.0, $light->azimuth());
        self::assertSame(60.0, $light->elevation());
    }

    public function testSpecularLightingWithPointLight(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><filter id="f">'
            . '<feSpecularLighting specularConstant="0.8" specularExponent="20">'
            . '<fePointLight x="10" y="20" z="5"/>'
            . '</feSpecularLighting>'
            . '</filter></svg>',
        );
        $spec = $doc->children[0]->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeSpecularLighting::class, $spec);
        self::assertSame(0.8, $spec->specularConstant());
        self::assertSame(20.0, $spec->specularExponent());
        $light = $spec->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FePointLight::class, $light);
        self::assertSame(10.0, $light->x());
        self::assertSame(20.0, $light->y());
        self::assertSame(5.0, $light->z());
    }

    public function testSpotLightAccessors(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><filter id="f">'
            . '<feSpecularLighting>'
            . '<feSpotLight x="0" y="0" z="100" pointsAtX="50" pointsAtY="50" '
            . 'pointsAtZ="0" specularExponent="4" limitingConeAngle="30"/>'
            . '</feSpecularLighting>'
            . '</filter></svg>',
        );
        $light = $doc->children[0]->children[0]->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeSpotLight::class, $light);
        self::assertSame(0.0, $light->x());
        self::assertSame(100.0, $light->z());
        self::assertSame(50.0, $light->pointsAtX());
        self::assertSame(4.0, $light->specularExponent());
        self::assertSame(30.0, $light->limitingConeAngle());
    }

    public function testComponentTransferWithFuncChildren(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><filter id="f">'
            . '<feComponentTransfer>'
            . '<feFuncR type="gamma" amplitude="1" exponent="0.5" offset="0"/>'
            . '<feFuncG type="linear" slope="1.5" intercept="-0.25"/>'
            . '<feFuncB type="discrete" tableValues="0 0.5 1"/>'
            . '<feFuncA type="identity"/>'
            . '</feComponentTransfer>'
            . '</filter></svg>',
        );
        $ct = $doc->children[0]->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeComponentTransfer::class, $ct);
        self::assertCount(4, $ct->children);
        $r = $ct->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeFuncR::class, $r);
        self::assertSame('gamma', $r->type());
        self::assertSame(0.5, $r->exponent());
        $g = $ct->children[1];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeFuncG::class, $g);
        self::assertSame('linear', $g->type());
        self::assertSame(1.5, $g->slope());
        self::assertSame(-0.25, $g->intercept());
        $b = $ct->children[2];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeFuncB::class, $b);
        self::assertSame([0.0, 0.5, 1.0], $b->tableValues());
        $a = $ct->children[3];
        self::assertInstanceOf(\Phpdftk\Svg\Filter\FeFuncA::class, $a);
        self::assertSame('identity', $a->type());
    }
}
