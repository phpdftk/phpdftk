<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Cascade\ShorthandExpander;
use Phpdftk\Css\ValueParser;
use PHPUnit\Framework\TestCase;

/**
 * CSS Logical Properties 1 §5 / §6 logical-pair shorthand
 * expansions. `<prefix>: <start> [<end>]` form covers the
 * inset-block / inset-inline / margin-block / margin-inline /
 * padding-block / padding-inline shorthands.
 */
final class LogicalShorthandTest extends TestCase
{
    private ShorthandExpander $expander;
    private ValueParser $parser;

    protected function setUp(): void
    {
        $this->expander = new ShorthandExpander();
        $this->parser = new ValueParser();
    }

    private function value(string $css): \Phpdftk\Css\Value\Value
    {
        return $this->parser->parseFromString($css);
    }

    // -----------------------------------------------------------------------
    // inset-block / inset-inline
    // -----------------------------------------------------------------------

    public function testInsetBlockSingleValueAppliesToStartAndEnd(): void
    {
        $out = $this->expander->expand('inset-block', $this->value('10px'));
        self::assertArrayHasKey('inset-block-start', $out);
        self::assertArrayHasKey('inset-block-end', $out);
        self::assertSame(10.0, $out['inset-block-start']->value);
        self::assertSame(10.0, $out['inset-block-end']->value);
    }

    public function testInsetBlockTwoValuesMapStartThenEnd(): void
    {
        $out = $this->expander->expand('inset-block', $this->value('5px 15px'));
        self::assertSame(5.0, $out['inset-block-start']->value);
        self::assertSame(15.0, $out['inset-block-end']->value);
    }

    public function testInsetInlineExpands(): void
    {
        $out = $this->expander->expand('inset-inline', $this->value('1px 2px'));
        self::assertSame(1.0, $out['inset-inline-start']->value);
        self::assertSame(2.0, $out['inset-inline-end']->value);
    }

    // -----------------------------------------------------------------------
    // margin-block / margin-inline
    // -----------------------------------------------------------------------

    public function testMarginBlockSingleValueAppliesToBoth(): void
    {
        $out = $this->expander->expand('margin-block', $this->value('20px'));
        self::assertSame(20.0, $out['margin-block-start']->value);
        self::assertSame(20.0, $out['margin-block-end']->value);
    }

    public function testMarginInlineTwoValues(): void
    {
        $out = $this->expander->expand('margin-inline', $this->value('5px 10px'));
        self::assertSame(5.0, $out['margin-inline-start']->value);
        self::assertSame(10.0, $out['margin-inline-end']->value);
    }

    // -----------------------------------------------------------------------
    // padding-block / padding-inline
    // -----------------------------------------------------------------------

    public function testPaddingBlockExpands(): void
    {
        $out = $this->expander->expand('padding-block', $this->value('8px 12px'));
        self::assertSame(8.0, $out['padding-block-start']->value);
        self::assertSame(12.0, $out['padding-block-end']->value);
    }

    public function testPaddingInlineSingleValue(): void
    {
        $out = $this->expander->expand('padding-inline', $this->value('6px'));
        self::assertSame(6.0, $out['padding-inline-start']->value);
        self::assertSame(6.0, $out['padding-inline-end']->value);
    }
}
