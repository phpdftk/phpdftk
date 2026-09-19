<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\HarnessRunner;
use Phpdftk\WptHarness\Manifest;
use Phpdftk\WptHarness\Rasteriser;
use Phpdftk\WptHarness\Scorer;
use PHPUnit\Framework\TestCase;

/**
 * Extraction of `<meta name="fuzzy">` out of a fixture's markup.
 *
 * WPT finds these nodes with
 * `.//{http://www.w3.org/1999/xhtml}meta[@name='fuzzy']`, which is
 * indifferent to attribute order, attribute quoting and namespace
 * prefix. Each of the cases below was invisible to the previous
 * quoted-`name`-first matcher, which silently returned "no tolerance"
 * and let the fixture fall back to the harness default threshold.
 */
final class FuzzyMetaResolutionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpt-fuzzymeta-' . uniqid();
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->root);
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->root . '/' . $name;
        file_put_contents($path, $contents);
        return (string) realpath($path);
    }

    private function runner(): HarnessRunner
    {
        return new HarnessRunner(new Manifest(), new Rasteriser(), new Scorer(), $this->root);
    }

    public function testQuotedNameAttribute(): void
    {
        $ref = $this->write('t-ref.html', '<!doctype html>');
        $test = $this->write('t.html', '<!doctype html><meta name="fuzzy" content="maxDifference=0-5;totalPixels=0-100">');

        $fuzzy = $this->runner()->fuzzyFor($test, $ref);

        self::assertNotNull($fuzzy);
        self::assertSame([0, 5], $fuzzy->maxDifference);
        self::assertSame([0, 100], $fuzzy->totalPixels);
    }

    public function testUnquotedNameAttribute(): void
    {
        // 237 corpus fixtures (118 css + 119 html) write it this way.
        $ref = $this->write('u-ref.html', '<!doctype html>');
        $test = $this->write('u.html', '<!doctype html><meta name=fuzzy content="maxDifference=0-4; totalPixels=0-33000">');

        $fuzzy = $this->runner()->fuzzyFor($test, $ref);

        self::assertNotNull($fuzzy);
        self::assertSame([0, 4], $fuzzy->maxDifference);
        self::assertSame([0, 33000], $fuzzy->totalPixels);
    }

    public function testContentAttributeBeforeNameAttribute(): void
    {
        $ref = $this->write('o-ref.html', '<!doctype html>');
        $test = $this->write('o.html', '<!doctype html><meta content="0-2;0-500" name="fuzzy">');

        $fuzzy = $this->runner()->fuzzyFor($test, $ref);

        self::assertNotNull($fuzzy);
        self::assertSame([0, 2], $fuzzy->maxDifference);
        self::assertSame([0, 500], $fuzzy->totalPixels);
    }

    public function testNamespacedMetaElement(): void
    {
        $ref = $this->write('n-ref.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $test = $this->write('n.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:html="http://www.w3.org/1999/xhtml">
            	<html:meta name="fuzzy" content="maxDifference=0-3;totalPixels=0-9"/>
            	<html:link rel="match" href="n-ref.svg"/>
            </svg>
            SVG);

        $fuzzy = $this->runner()->fuzzyFor($test, $ref);

        self::assertNotNull($fuzzy);
        self::assertSame([0, 3], $fuzzy->maxDifference);
        self::assertSame([0, 9], $fuzzy->totalPixels);
    }

    public function testNoAnnotationYieldsNull(): void
    {
        $ref = $this->write('p-ref.html', '<!doctype html>');
        $test = $this->write('p.html', '<!doctype html><meta name="viewport" content="width=device-width">');

        self::assertNull($this->runner()->fuzzyFor($test, $ref));
    }

    public function testTemplatedPlaceholderFallsBackToNoTolerance(): void
    {
        // Six corpus fixtures ship `content="{{ fuzzy }}"` for the
        // wptserve template pipeline; there is nothing to honour.
        $ref = $this->write('tp-ref.html', '<!doctype html>');
        $test = $this->write('tp.html', '<!doctype html><meta name=fuzzy content="{{ fuzzy }}">');

        self::assertNull($this->runner()->fuzzyFor($test, $ref));
    }

    // ------------------------------------------------------------------
    // Reference-keyed declarations (WPT `parse_ref_keyed_meta`)
    // ------------------------------------------------------------------

    public function testReferenceKeyedDeclarationAppliesOnlyToItsOwnReference(): void
    {
        $mine = $this->write('k-a-ref.html', '<!doctype html>');
        $other = $this->write('k-b-ref.html', '<!doctype html>');
        $test = $this->write('k.html', <<<'HTML'
            <!doctype html>
            <meta name="fuzzy" content="k-a-ref.html:maxDifference=0-7;totalPixels=0-70">
            <meta name="fuzzy" content="k-b-ref.html:maxDifference=0-9;totalPixels=0-90">
            HTML);

        $forMine = $this->runner()->fuzzyFor($test, $mine);
        self::assertNotNull($forMine);
        self::assertSame([0, 7], $forMine->maxDifference);

        $forOther = $this->runner()->fuzzyFor($test, $other);
        self::assertNotNull($forOther);
        self::assertSame([0, 9], $forOther->maxDifference);
    }

    public function testUnkeyedDeclarationIsTheDefaultForAnyReference(): void
    {
        $keyedRef = $this->write('m-a-ref.html', '<!doctype html>');
        $plainRef = $this->write('m-b-ref.html', '<!doctype html>');
        $test = $this->write('m.html', <<<'HTML'
            <!doctype html>
            <meta name="fuzzy" content="m-a-ref.html:maxDifference=0-7;totalPixels=0-70">
            <meta name="fuzzy" content="maxDifference=0-1;totalPixels=0-10">
            HTML);

        $keyed = $this->runner()->fuzzyFor($test, $keyedRef);
        self::assertNotNull($keyed);
        self::assertSame([0, 7], $keyed->maxDifference);

        $default = $this->runner()->fuzzyFor($test, $plainRef);
        self::assertNotNull($default);
        self::assertSame([0, 1], $default->maxDifference);
    }
}
