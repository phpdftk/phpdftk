<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer\Tests;

use Phpdftk\Pdf\Writer\Pdf;
use Phpdftk\Tests\Support\QpdfValidationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group("qpdf")]
class AutoOutlineTest extends TestCase
{
    use QpdfValidationTrait;

    public function testEnableOutlineThenHeadingRegistersOutlinesEntry(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->enableOutline();
        $pdf->addHeading('Chapter 1', 1);
        $pdf->addText('Body text');

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('/Outlines', $bytes);
        self::assertStringContainsString('/Type /Outlines', $bytes);
        self::assertStringContainsString('Chapter 1', $bytes);
        $this->assertQpdfValidBytes($bytes);
    }

    public function testHeadingDestinationUsesXyzWithCurrentPageRef(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->enableOutline();
        $pdf->addHeading('Anchor', 1);
        $bytes = $pdf->toBytes();

        // OutlineItem dest should be an explicit /XYZ destination
        // referencing the current page.
        self::assertMatchesRegularExpression(
            '/\/Dest \[ \d+ 0 R \/XYZ \d+(?:\.\d+)? \d+(?:\.\d+)? \d+(?:\.\d+)? \]/',
            $bytes,
        );
    }

    public function testHierarchyParentsH2UnderPriorH1(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->enableOutline();
        $pdf->addHeading('Part one', 1);
        $pdf->addHeading('Section A', 2);
        $pdf->addHeading('Section B', 2);
        $pdf->addHeading('Part two', 1);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Part one', $bytes);
        self::assertStringContainsString('Section A', $bytes);
        self::assertStringContainsString('Section B', $bytes);
        self::assertStringContainsString('Part two', $bytes);
        // A level-2 entry must reference its level-1 parent. Look for the
        // pattern "Title (Section A) … Parent N 0 R" where N matches the
        // object number of the "Part one" item.
        if (!preg_match('/(\d+) 0 obj\s+<<\s+\/Title \(Part one\)/', $bytes, $partOne)) {
            self::fail('Did not find Part one outline object');
        }
        $partOneObj = (int) $partOne[1];
        self::assertMatchesRegularExpression(
            '/\/Title \(Section A\)\s+\/Parent ' . $partOneObj . ' 0 R/',
            $bytes,
            'Section A should be parented under Part one',
        );
    }

    public function testOutlineCountReflectsTotalEntries(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->enableOutline();
        $pdf->addHeading('A', 1);
        $pdf->addHeading('B', 2);
        $pdf->addHeading('C', 2);
        $pdf->addHeading('D', 1);

        $bytes = $pdf->toBytes();
        // The Outlines dictionary should have /Count 4 — all entries.
        self::assertMatchesRegularExpression(
            '/\/Type \/Outlines\s*\/First \d+ 0 R\s*\/Last \d+ 0 R\s*\/Count 4/s',
            $bytes,
        );
    }

    public function testDisablingOutlineStopsRecordingFurtherHeadings(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->enableOutline();
        $pdf->addHeading('Recorded', 1);
        $pdf->enableOutline(false);
        $pdf->addHeading('Skipped', 1);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Recorded', $bytes);
        self::assertStringContainsString('Skipped', $bytes); // still in body content
        // The outline /Count should be 1 — only the first heading is recorded.
        self::assertMatchesRegularExpression('/\/Count 1\b/', $bytes);
    }

    public function testEnablingOutlineAfterFirstHeadingMissesEarlierHeadings(): void
    {
        // Documented behavior: outline only captures headings after
        // enableOutline() is called. Pin this so a future refactor
        // can't silently start backfilling.
        $pdf = new Pdf(compressStreams: false);
        $pdf->addHeading('Missed', 1);
        $pdf->enableOutline();
        $pdf->addHeading('Captured', 1);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Missed', $bytes);
        self::assertStringContainsString('Captured', $bytes);
        // /Count should be 1: only the heading after enableOutline().
        self::assertMatchesRegularExpression('/\/Count 1\b/', $bytes);
    }

    public function testEnableOutlineIsIdempotent(): void
    {
        // Calling enableOutline() twice must not create two roots —
        // the second call is a no-op when one is already wired.
        $pdf = new Pdf(compressStreams: false);
        $pdf->enableOutline();
        $pdf->enableOutline();
        $pdf->addHeading('Hello', 1);

        $bytes = $pdf->toBytes();
        // Exactly one /Type /Outlines object in the file.
        self::assertSame(1, substr_count($bytes, '/Type /Outlines'));
    }

    public function testHeadingsWithoutEnableOutlineProduceNoOutlinesEntry(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->addHeading('Untracked', 1);
        $pdf->addHeading('Sub', 2);

        $bytes = $pdf->toBytes();
        self::assertStringContainsString('Untracked', $bytes);
        // Without enableOutline(), no /Outlines entry on the catalog.
        self::assertStringNotContainsString('/Type /Outlines', $bytes);
    }

    public function testSiblingChainAtSameLevelLinksPrevAndNext(): void
    {
        $pdf = new Pdf(compressStreams: false);
        $pdf->enableOutline();
        $pdf->addHeading('First', 1);
        $pdf->addHeading('Second', 1);
        $pdf->addHeading('Third', 1);

        $bytes = $pdf->toBytes();
        // The second item should carry a /Prev reference (back to the first).
        // The first carries a /Next pointer forward.
        self::assertMatchesRegularExpression('/\/Title \(Second\).+\/Prev \d+ 0 R/s', $bytes);
        self::assertMatchesRegularExpression('/\/Title \(First\).+\/Next \d+ 0 R/s', $bytes);
    }
}
