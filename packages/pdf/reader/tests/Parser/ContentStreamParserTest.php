<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tests\Parser;

use Phpdftk\Pdf\Reader\Parser\ContentStreamParser;
use PHPUnit\Framework\TestCase;

class ContentStreamParserTest extends TestCase
{
    public function testInlineDictionaryOperand(): void
    {
        $parser = new ContentStreamParser();
        // Use an inline dict as an operand (e.g., for `gs` with a named state, but here just exercising the parser).
        $ops = $parser->parse("<< /Length 10 /Filter /FlateDecode >> Do");

        $this->assertCount(1, $ops);
        $this->assertSame('Do', $ops[0]->operator);
        $this->assertStringContainsString('/Length', $ops[0]->operands[0]);
        $this->assertStringContainsString('/Filter', $ops[0]->operands[0]);
    }

    public function testLiteralStringWithEscapes(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("(escaped \\n \\\\ \\( inside) Tj");
        $this->assertCount(1, $ops);
        $this->assertSame('Tj', $ops[0]->operator);
    }

    public function testNestedParenthesesInLiteralString(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("(outer (inner) end) Tj");
        $this->assertCount(1, $ops);
        $this->assertStringContainsString('outer', $ops[0]->operands[0]);
        $this->assertStringContainsString('inner', $ops[0]->operands[0]);
    }

    public function testBooleanAndNullOperands(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("true false null SomeOp");
        $this->assertCount(1, $ops);
        $this->assertSame('SomeOp', $ops[0]->operator);
        $this->assertSame(['true', 'false', 'null'], $ops[0]->operands);
    }

    public function testNamesAsOperands(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("/F1 12 Tf");
        $this->assertCount(1, $ops);
        $this->assertSame('Tf', $ops[0]->operator);
        $this->assertSame('/F1', $ops[0]->operands[0]);
        $this->assertSame('12', $ops[0]->operands[1]);
    }

    public function testNegativeAndDecimalNumbers(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("-5 .25 +3 12.5 someOp");
        $this->assertCount(1, $ops);
        $this->assertSame(['-5', '.25', '+3', '12.5'], $ops[0]->operands);
    }

    public function testInlineImageBiIdEi(): void
    {
        $parser = new ContentStreamParser();
        // BI ... ID ... EI - inline image
        $stream = "BI /W 2 /H 2 /BPC 8 /CS /DeviceRGB ID \x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\nEI";
        $ops = $parser->parse($stream);

        $found = false;
        foreach ($ops as $op) {
            if ($op->operator === 'EI' || $op->operator === 'BI') {
                $found = true;
            }
        }
        $this->assertTrue($found, 'parser should produce an inline-image op');
    }

    public function testWhitespaceInterleaving(): void
    {
        $parser = new ContentStreamParser();
        // Lots of whitespace types: spaces, tabs, newlines, null, form-feed
        $stream = "  \t\r\n BT \t ET \r\n";
        $ops = $parser->parse($stream);
        $this->assertCount(2, $ops);
        $this->assertSame('BT', $ops[0]->operator);
        $this->assertSame('ET', $ops[1]->operator);
    }

    public function testCommentMidStream(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("BT % start text\n(hello) Tj ET");
        $this->assertCount(3, $ops);
    }

    public function testCarriageReturnTerminatedComment(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("BT % comment\r(test) Tj ET");
        $this->assertCount(3, $ops);
    }

    public function testHexStringWithoutClosing(): void
    {
        // Unclosed hex string is read until end of stream — produces an operand but no operator,
        // so no ops are emitted.
        $parser = new ContentStreamParser();
        $ops = $parser->parse("<deadbeef Tj");
        $this->assertSame([], $ops);
    }

    public function testEmptyArrayOperand(): void
    {
        $parser = new ContentStreamParser();
        $ops = $parser->parse("[] TJ");
        $this->assertCount(1, $ops);
        $this->assertSame('TJ', $ops[0]->operator);
        $this->assertSame('[]', $ops[0]->operands[0]);
    }
}
