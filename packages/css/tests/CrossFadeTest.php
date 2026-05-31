<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CrossFade;
use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\Url;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Images 4 §4 — `cross-fade()` typed parser.
 */
final class CrossFadeTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testTwoUnlabelledImages(): void
    {
        $v = $this->parser->parseFromString('cross-fade(url(a.png), url(b.png))');
        self::assertInstanceOf(CrossFade::class, $v);
        self::assertCount(2, $v->options);
        self::assertNull($v->options[0]->percent);
        self::assertInstanceOf(Url::class, $v->options[0]->image);
        self::assertSame('a.png', $v->options[0]->image->url);
    }

    public function testWeightedEntries(): void
    {
        $v = $this->parser->parseFromString('cross-fade(25% url(a.png), 75% url(b.png))');
        self::assertInstanceOf(CrossFade::class, $v);
        self::assertSame(25.0, $v->options[0]->percent);
        self::assertSame(75.0, $v->options[1]->percent);
    }

    public function testMixedWeightedAndUnweighted(): void
    {
        $v = $this->parser->parseFromString('cross-fade(50% url(a.png), url(b.png))');
        self::assertInstanceOf(CrossFade::class, $v);
        self::assertSame(50.0, $v->options[0]->percent);
        self::assertNull($v->options[1]->percent);
    }

    public function testPercentageOutOfRangeRejected(): void
    {
        $v = $this->parser->parseFromString('cross-fade(150% url(a.png), url(b.png))');
        self::assertNotInstanceOf(CrossFade::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testNegativePercentageRejected(): void
    {
        $v = $this->parser->parseFromString('cross-fade(-10% url(a.png), url(b.png))');
        self::assertNotInstanceOf(CrossFade::class, $v);
    }

    public function testEmptyArgRejected(): void
    {
        $v = $this->parser->parseFromString('cross-fade()');
        self::assertNotInstanceOf(CrossFade::class, $v);
    }

    public function testRoundTrip(): void
    {
        $v = $this->parser->parseFromString('cross-fade(25% url(a.png), 75% url(b.png))');
        self::assertInstanceOf(CrossFade::class, $v);
        self::assertSame('cross-fade(25% url("a.png"), 75% url("b.png"))', $v->toCss());
    }

    public function testRoundTripUnweighted(): void
    {
        $v = $this->parser->parseFromString('cross-fade(url(a.png), url(b.png))');
        self::assertInstanceOf(CrossFade::class, $v);
        self::assertSame('cross-fade(url("a.png"), url("b.png"))', $v->toCss());
    }
}
