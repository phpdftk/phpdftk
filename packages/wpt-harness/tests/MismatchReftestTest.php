<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\HarnessRunner;
use Phpdftk\WptHarness\Manifest;
use Phpdftk\WptHarness\Rasteriser;
use Phpdftk\WptHarness\ReferenceRelation;
use Phpdftk\WptHarness\Scorer;
use Phpdftk\WptHarness\TestStatus;
use PHPUnit\Framework\TestCase;

/**
 * `rel="mismatch"` end-to-end, through the real render → rasterise →
 * diff pipeline.
 *
 * A mismatch reftest asserts the opposite of a match reftest: the
 * test must NOT render like the reference. The verdict over a fixture
 * carrying several references is WPT's
 * (`docs/writing-tests/reftests.md`, implemented by the reference
 * tree in `wpttest.py::ReftestTest.from_manifest`):
 *
 *     If there are any match references, at least one must match, and
 *     if there are any mismatch references, all must mismatch.
 *
 * Both halves are load-bearing and both are pinned below, because
 * getting either one backwards silently turns a negative assertion
 * into a second way to pass.
 */
final class MismatchReftestTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        if (!(new Rasteriser())->isAvailable()) {
            self::markTestSkipped('Ghostscript (`gs`) not installed');
        }
        if (!(new Scorer())->isAvailable()) {
            self::markTestSkipped('ImageMagick `compare` not installed');
        }
        $this->root = sys_get_temp_dir() . '/wpt-mismatch-' . uniqid();
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            self::rrmdir($this->root);
        }
    }

    public function testRenderingUnlikeAMismatchReferencePasses(): void
    {
        $this->write('square.html', $this->page('<div class=box style="background:green"></div>', [
            ['mismatch', 'other.html'],
        ]));
        $this->write('other.html', $this->page('<div class=box style="background:red"></div>'));

        $result = $this->runner()->runOne('square');

        self::assertSame(TestStatus::Pass, $result->status, (string) $result->reason);
    }

    public function testRenderingLIKEAMismatchReferenceFails(): void
    {
        // The fixture says "I must not look like this". Looking
        // exactly like it is the failure, however perfect the score.
        $this->write('square.html', $this->page('<div class=box style="background:green"></div>', [
            ['mismatch', 'twin.html'],
        ]));
        $this->write('twin.html', $this->page('<div class=box style="background:green"></div>'));

        $result = $this->runner()->runOne('square');

        self::assertSame(TestStatus::Fail, $result->status);
        self::assertStringContainsString('rel=mismatch', (string) $result->reason);
    }

    public function testTwoBlankRendersFailAMismatchInsteadOfPassing(): void
    {
        // This is why mismatch fixtures are worth having in a harness
        // that renders both sides itself: an unimplemented feature
        // blanks the test and the reference alike, two blank pages are
        // identical, and identical is exactly what a mismatch fixture
        // forbids. The same pair under `rel=match` would pass with no
        // evidence at all.
        $this->write('blank.html', $this->page('', [['mismatch', 'alsoblank.html']]));
        $this->write('alsoblank.html', $this->page(''));

        $result = $this->runner()->runOne('blank');

        self::assertSame(TestStatus::Fail, $result->status);
        self::assertFalse($result->bothRendersSolid, 'a mismatch failure is not a blank PASS');
    }

    public function testASatisfiedMatchDoesNotExcuseAViolatedMismatch(): void
    {
        // The conjunction. The test looks like its match reference, so
        // a first-satisfied-reference walk would stop there and call
        // it a pass — while the fixture's other assertion, that it
        // must not look like `twin.html`, is being violated.
        $this->write('square.html', $this->page('<div class=box style="background:green"></div>', [
            ['match', 'ref.html'],
            ['mismatch', 'twin.html'],
        ]));
        $this->write('ref.html', $this->page('<div class=box style="background:green"></div>'));
        $this->write('twin.html', $this->page('<div class=box style="background:green"></div>'));

        $result = $this->runner()->runOne('square');

        self::assertSame(TestStatus::Fail, $result->status);
        self::assertStringContainsString('rel=mismatch', (string) $result->reason);
    }

    public function testASatisfiedMismatchIsNoSubstituteForAFailedMatch(): void
    {
        // The other direction. The test does not look like its match
        // reference, which is a failure; that it also does not look
        // like the mismatch reference is not an alternative way to
        // pass, because the two are conditions, not options.
        $this->write('square.html', $this->page('<div class=box style="background:green"></div>', [
            ['match', 'ref.html'],
            ['mismatch', 'other.html'],
        ]));
        $this->write('ref.html', $this->page('<div class=box style="background:red"></div>'));
        $this->write('other.html', $this->page('<div class=box style="background:blue"></div>'));

        $result = $this->runner()->runOne('square');

        self::assertSame(TestStatus::Fail, $result->status);
    }

    public function testBothMatchAndMismatchSatisfiedPasses(): void
    {
        $this->write('square.html', $this->page('<div class=box style="background:green"></div>', [
            ['match', 'ref.html'],
            ['mismatch', 'other.html'],
        ]));
        $this->write('ref.html', $this->page('<div class=box style="background:green"></div>'));
        $this->write('other.html', $this->page('<div class=box style="background:red"></div>'));

        $result = $this->runner()->runOne('square');

        self::assertSame(TestStatus::Pass, $result->status, (string) $result->reason);
    }

    public function testMismatchOnlyFixtureIsNoLongerSkipped(): void
    {
        // Before mismatch semantics existed these fixtures resolved to
        // "no reference" and dropped out of the denominator entirely.
        $this->write('square.html', $this->page('<div class=box style="background:green"></div>', [
            ['mismatch', 'other.html'],
        ]));
        $this->write('other.html', $this->page('<div class=box style="background:red"></div>'));

        $result = $this->runner()->runOne('square');

        self::assertNotSame(TestStatus::Skipped, $result->status);
    }

    public function testReferenceListPutsMatchesBeforeMismatches(): void
    {
        // WPT concatenates `match_links + mismatch_links`, so the
        // relation decides the order, not the document.
        $test = $this->write('square.html', $this->page('<div class=box></div>', [
            ['mismatch', 'other.html'],
            ['match', 'ref.html'],
        ]));
        $this->write('ref.html', $this->page(''));
        $this->write('other.html', $this->page(''));

        $references = $this->runner()->references($test);

        self::assertSame(
            [ReferenceRelation::Match, ReferenceRelation::Mismatch],
            array_map(static fn($r) => $r->relation, $references),
        );
    }

    public function testADeclaredMismatchOutranksARefSibling(): void
    {
        // A `rel=mismatch` link makes the file a reftest just as much
        // as `rel=match` does, so the legacy `-ref` sibling — which
        // the fixture never pointed at — must not be picked up. 28
        // corpus fixtures carry exactly this combination.
        $test = $this->write('square.html', $this->page('<div class=box></div>', [
            ['mismatch', 'other.html'],
        ]));
        $this->write('other.html', $this->page(''));
        $this->write('square-ref.html', $this->page(''));

        $references = $this->runner()->references($test);

        self::assertCount(1, $references);
        self::assertSame(ReferenceRelation::Mismatch, $references[0]->relation);
        self::assertSame(realpath($this->root . '/other.html'), $references[0]->path);
    }

    public function testAnUnresolvableMismatchStillFallsBackToTheRefSibling(): void
    {
        $test = $this->write('square.html', $this->page('<div class=box></div>', [
            ['mismatch', 'gone.html'],
        ]));
        $ref = $this->write('square-ref.html', $this->page(''));

        $references = $this->runner()->references($test);

        self::assertCount(1, $references);
        self::assertSame(ReferenceRelation::Match, $references[0]->relation);
        self::assertSame(realpath($ref), $references[0]->path);
    }

    /**
     * @param list<array{string, string}> $links
     */
    private function page(string $body, array $links = []): string
    {
        $head = '';
        foreach ($links as [$rel, $href]) {
            $head .= sprintf('<link rel="%s" href="%s">', $rel, $href);
        }

        return '<!doctype html><html><head><meta charset=utf-8>' . $head
            . '<style>.box{width:100px;height:100px}</style></head><body>'
            . $body . '</body></html>';
    }

    private function write(string $relative, string $contents): string
    {
        $path = $this->root . '/' . $relative;
        file_put_contents($path, $contents);

        return $path;
    }

    private function runner(): HarnessRunner
    {
        return new HarnessRunner(
            new Manifest(),
            new Rasteriser(),
            new Scorer(),
            $this->root,
        );
    }

    private static function rrmdir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? self::rrmdir($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
