<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tests\Dom;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\LengthContext;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Parser as CssParser;
use Phpdftk\Css\Value\Color;
use Phpdftk\Css\Value\Length;
use Phpdftk\Css\Value\LengthUnit;
use Phpdftk\Html\Parser;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end smoke: parsed HTML → DOM → CSS cascade → resolved values.
 * Confirms `Element` correctly implements `MatchableElement` so the cascade
 * matches against real WHATWG DOM trees.
 */
final class CssCascadeIntegrationTest extends TestCase
{
    public function testCascadeAgainstParsedHtml(): void
    {
        $html = '<!DOCTYPE html><html><body><article><p id="lead" class="intro">Hello</p></article></body></html>';
        $doc = (new Parser())->parseDocument($html);
        $root = $doc->documentElement;
        self::assertNotNull($root);
        $p = $root->querySelector('p');
        self::assertNotNull($p);

        $cssParser = new CssParser();
        $sheet = $cssParser->parseStylesheet('
            p { color: blue; }
            .intro { color: green; }
            #lead { color: red; }
            article { font-size: 20px; }
        ');

        $cascade = new Cascade(PropertyRegistry::default());
        $article = $p->parentElement();
        self::assertNotNull($article);
        $body = $article->parentElement();
        self::assertNotNull($body);
        $html = $body->parentElement();
        self::assertNotNull($html);

        $htmlVals = $cascade->computeFor([$sheet], $html);
        $bodyVals = $cascade->computeFor([$sheet], $body, $htmlVals);
        $artVals = $cascade->computeFor([$sheet], $article, $bodyVals);
        $pVals = $cascade->computeFor([$sheet], $p, $artVals);

        // #lead beats .intro beats p — color should be red.
        $color = $pVals->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);
        self::assertSame(0.0, $color->b);

        // font-size declared on article; inherits down to p.
        $fontSize = $pVals->get('font-size');
        self::assertInstanceOf(Length::class, $fontSize);
        self::assertSame(20.0, $fontSize->value);
    }

    public function testVarAndLengthResolutionPipeline(): void
    {
        // End-to-end: parse HTML, parse CSS with var() + em, run the cascade
        // through the html-element bridge, then resolve lengths.
        $html = '<!DOCTYPE html><html><body><section><p class="lead">Hi</p></section></body></html>';
        $doc = (new Parser())->parseDocument($html);
        $root = $doc->documentElement;
        self::assertNotNull($root);
        $p = $root->querySelector('p');
        self::assertNotNull($p);

        $sheet = (new CssParser())->parseStylesheet('
            :root { --accent: red; --gap: 12pt; }
            section { font-size: 20px; }
            .lead { color: var(--accent); margin-top: 2em; padding-left: var(--gap); }
        ');

        $cascade = new Cascade(PropertyRegistry::default());
        $section = $p->parentElement();
        $body = $section->parentElement();
        $htmlEl = $body->parentElement();

        $htmlVals = $cascade->computeFor([$sheet], $htmlEl);
        $bodyVals = $cascade->computeFor([$sheet], $body, $htmlVals);
        $secVals = $cascade->computeFor([$sheet], $section, $bodyVals);
        $pVals = $cascade->computeFor([$sheet], $p, $secVals);

        $cascade->resolveLengths(
            $pVals,
            new LengthContext(parentFontSize: 20.0, rootFontSize: 16.0),
        );

        // var(--accent) → red
        $color = $pVals->get('color');
        self::assertInstanceOf(Color::class, $color);
        self::assertSame(1.0, $color->r);

        // margin-top: 2em against parent's 20px = 40px
        $marginTop = $pVals->get('margin-top');
        self::assertInstanceOf(Length::class, $marginTop);
        self::assertSame(40.0, $marginTop->value);
        self::assertSame(LengthUnit::Px, $marginTop->unit);

        // padding-left: var(--gap) → 12pt → 16px
        $padLeft = $pVals->get('padding-left');
        self::assertInstanceOf(Length::class, $padLeft);
        self::assertEqualsWithDelta(16.0, $padLeft->value, 0.001);
        self::assertSame(LengthUnit::Px, $padLeft->unit);
    }
}
