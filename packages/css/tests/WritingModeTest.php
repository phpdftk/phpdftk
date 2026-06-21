<?php

declare(strict_types=1);

namespace Phpdftk\Css\Tests;

use Phpdftk\Css\Cascade\Cascade;
use Phpdftk\Css\Cascade\PropertyRegistry;
use Phpdftk\Css\Cascade\WritingMode;
use Phpdftk\Css\Parser;
use PHPUnit\Framework\TestCase;

final class WritingModeTest extends TestCase
{
    private Cascade $cascade;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->cascade = new Cascade(PropertyRegistry::default());
        $this->parser = new Parser();
    }

    public function testHorizontalTbDefaults(): void
    {
        $wm = new WritingMode();
        self::assertSame('y', $wm->blockAxis());
        self::assertSame('x', $wm->inlineAxis());
        self::assertSame(1, $wm->blockDirection());
        self::assertSame(1, $wm->inlineDirection());
        self::assertTrue($wm->isHorizontal());
        self::assertFalse($wm->isVertical());
    }

    public function testVerticalRlBlockGoesLeftInlineGoesDown(): void
    {
        $wm = new WritingMode(WritingMode::VERTICAL_RL);
        self::assertSame('x', $wm->blockAxis());
        self::assertSame('y', $wm->inlineAxis());
        self::assertSame(-1, $wm->blockDirection());
        self::assertSame(1, $wm->inlineDirection());
        self::assertTrue($wm->isVertical());
    }

    public function testVerticalLrBlockGoesRightInlineGoesDown(): void
    {
        $wm = new WritingMode(WritingMode::VERTICAL_LR);
        self::assertSame('x', $wm->blockAxis());
        self::assertSame('y', $wm->inlineAxis());
        self::assertSame(1, $wm->blockDirection());
        self::assertSame(1, $wm->inlineDirection());
    }

    public function testRtlInHorizontalTbFlipsInlineDirection(): void
    {
        $wm = new WritingMode(WritingMode::HORIZONTAL_TB, 'rtl');
        self::assertSame(-1, $wm->inlineDirection());
        self::assertSame(1, $wm->blockDirection());
    }

    public function testSidewaysModesAreVerticalAndMarkedSideways(): void
    {
        $srl = new WritingMode(WritingMode::SIDEWAYS_RL);
        self::assertTrue($srl->isVertical());
        self::assertTrue($srl->isSideways());
        self::assertSame(-1, $srl->blockDirection());

        $slr = new WritingMode(WritingMode::SIDEWAYS_LR);
        self::assertTrue($slr->isVertical());
        self::assertTrue($slr->isSideways());
        self::assertSame(1, $slr->blockDirection());
    }

    public function testPhysicalEdgeHorizontalTbLtr(): void
    {
        $wm = new WritingMode();
        self::assertSame('top', $wm->physicalEdge('block-start'));
        self::assertSame('bottom', $wm->physicalEdge('block-end'));
        self::assertSame('left', $wm->physicalEdge('inline-start'));
        self::assertSame('right', $wm->physicalEdge('inline-end'));
    }

    public function testPhysicalEdgeHorizontalTbRtl(): void
    {
        $wm = new WritingMode(WritingMode::HORIZONTAL_TB, 'rtl');
        self::assertSame('right', $wm->physicalEdge('inline-start'));
        self::assertSame('left', $wm->physicalEdge('inline-end'));
        // block axis unaffected by direction
        self::assertSame('top', $wm->physicalEdge('block-start'));
    }

    public function testPhysicalEdgeVerticalRl(): void
    {
        $wm = new WritingMode(WritingMode::VERTICAL_RL);
        // block flows right→left: start at right edge, end at left
        self::assertSame('right', $wm->physicalEdge('block-start'));
        self::assertSame('left', $wm->physicalEdge('block-end'));
        // inline goes top→bottom under ltr
        self::assertSame('top', $wm->physicalEdge('inline-start'));
        self::assertSame('bottom', $wm->physicalEdge('inline-end'));
    }

    public function testPhysicalEdgeVerticalLr(): void
    {
        $wm = new WritingMode(WritingMode::VERTICAL_LR);
        self::assertSame('left', $wm->physicalEdge('block-start'));
        self::assertSame('right', $wm->physicalEdge('block-end'));
        self::assertSame('top', $wm->physicalEdge('inline-start'));
        self::assertSame('bottom', $wm->physicalEdge('inline-end'));
    }

    public function testFromStyleReadsCascadedValues(): void
    {
        $sheet = $this->parser->parseStylesheet(
            'p { writing-mode: vertical-rl; direction: rtl; }',
        );
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $wm = WritingMode::fromStyle($values);
        self::assertSame(WritingMode::VERTICAL_RL, $wm->mode);
        self::assertSame('rtl', $wm->direction);
        self::assertSame('bottom', $wm->physicalEdge('inline-start'));
    }

    public function testFromStyleFallsBackToInitialOnUnknownKeyword(): void
    {
        $sheet = $this->parser->parseStylesheet('p { color: red; }');
        $values = $this->cascade->computeFor([$sheet], new FakeElement('p'));
        $wm = WritingMode::fromStyle($values);
        self::assertSame(WritingMode::HORIZONTAL_TB, $wm->mode);
        self::assertSame('ltr', $wm->direction);
    }
}
