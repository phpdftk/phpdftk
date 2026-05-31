<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Value\CssFunction;
use Phpdftk\Css\Value\PaintFunction;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Painting API Level 1 — `paint()` typed parser. For print
 * rendering this is purely declarative preservation; no JS
 * worklet runs.
 */
final class PaintFunctionTest extends TestCase
{
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ValueParser();
    }

    public function testBarePainterName(): void
    {
        $v = $this->parser->parseFromString('paint(myCheckerboard)');
        self::assertInstanceOf(PaintFunction::class, $v);
        self::assertSame('myCheckerboard', $v->name);
        self::assertSame([], $v->arguments);
    }

    public function testPainterWithArguments(): void
    {
        $v = $this->parser->parseFromString('paint(checker, blue, 16px)');
        self::assertInstanceOf(PaintFunction::class, $v);
        self::assertSame('checker', $v->name);
        self::assertCount(2, $v->arguments);
    }

    public function testMissingNameRejected(): void
    {
        $v = $this->parser->parseFromString('paint()');
        self::assertNotInstanceOf(PaintFunction::class, $v);
        self::assertInstanceOf(CssFunction::class, $v);
    }

    public function testRoundTripBare(): void
    {
        $v = $this->parser->parseFromString('paint(myCheckerboard)');
        self::assertInstanceOf(PaintFunction::class, $v);
        self::assertSame('paint(myCheckerboard)', $v->toCss());
    }
}
