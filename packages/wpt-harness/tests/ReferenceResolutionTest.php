<?php

declare(strict_types=1);

namespace Phpdftk\WptHarness\Tests;

use Phpdftk\WptHarness\HarnessRunner;
use Phpdftk\WptHarness\Manifest;
use Phpdftk\WptHarness\Rasteriser;
use Phpdftk\WptHarness\Scorer;
use PHPUnit\Framework\TestCase;

/**
 * Reference-resolution semantics, pinned against WPT's own manifest
 * generator (`tools/manifest/sourcefile.py`):
 *
 *     match_links = self.root.findall(
 *         ".//{http://www.w3.org/1999/xhtml}link[@rel='match']")
 *
 * Three consequences that the harness must honour:
 *
 *  1. The search is by **XHTML namespace**, not by literal tag
 *     spelling. In an SVG or XML document the reference is written
 *     `<html:link rel="match" href="…"/>` with `xmlns:html` bound to
 *     the XHTML namespace — still an XHTML `link` element, still a
 *     reference.
 *  2. The declared link is the **only** thing that makes a file a
 *     reftest. The `<stem>-ref.<ext>` filename convention is a
 *     legacy CSS-WG artefact; when both exist and disagree, the
 *     declared link wins.
 *  3. Attribute values may be unquoted (`rel=match`), and `href` may
 *     carry a fragment or query that is not part of the on-disk path.
 */
final class ReferenceResolutionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpt-refres-' . uniqid();
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        self::rrmdir($this->root);
    }

    private function write(string $relative, string $contents): string
    {
        $path = $this->root . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }
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

    // ------------------------------------------------------------------
    // 1. Namespaced <html:link rel="match">
    // ------------------------------------------------------------------

    public function testResolvesNamespacedHtmlLinkRelMatchInSvgDocument(): void
    {
        // Verbatim shape of css/css-masking/clip-path-svg-content/*.svg —
        // 102 fixtures that the plain `<link …>` matcher could not see.
        $test = $this->write('css/css-masking/clip-path-svg-content/clip-rule-001.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:html="http://www.w3.org/1999/xhtml">
            <g id="testmeta">
            	<html:link rel="help" href="http://www.w3.org/TR/css-masking-1/#the-clip-rule"/>
            	<html:link rel="match" href="reference/square-hole-001-ref.svg" />
            </g>
            <rect width="200" height="200" fill="green"/>
            </svg>
            SVG);
        $ref = $this->write(
            'css/css-masking/clip-path-svg-content/reference/square-hole-001-ref.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="200" height="200" fill="green"/></svg>',
        );

        self::assertSame(realpath($ref), $this->runner()->locateReference($test));
    }

    public function testResolvesArbitraryNamespacePrefix(): void
    {
        $test = $this->write('svg/struct/reftests/t.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:x="http://www.w3.org/1999/xhtml">
            	<x:link rel="match" href="green-ref.svg"/>
            </svg>
            SVG);
        $ref = $this->write('svg/struct/reftests/green-ref.svg', '<svg/>');

        self::assertSame(realpath($ref), $this->runner()->locateReference($test));
    }

    // ------------------------------------------------------------------
    // 2. Declared link outranks the legacy `-ref` filename sibling
    // ------------------------------------------------------------------

    public function testDeclaredLinkWinsOverRefFilenameSibling(): void
    {
        // Real shape of css/CSS2/normal-flow/block-in-inline-remove-*.xht:
        // a `-ref.xht` sibling exists but the test declares a different
        // `-nosplit-ref.xht` reference. WPT scores against the declared one.
        $test = $this->write('css/CSS2/normal-flow/bii-001.xht', <<<'XHT'
            <?xml version="1.0" encoding="UTF-8"?>
            <html xmlns="http://www.w3.org/1999/xhtml"><head>
            <link rel="match" href="bii-001-nosplit-ref.xht"/>
            </head><body/></html>
            XHT);
        $this->write('css/CSS2/normal-flow/bii-001-ref.xht', '<html/>');
        $declared = $this->write('css/CSS2/normal-flow/bii-001-nosplit-ref.xht', '<html/>');

        self::assertSame(realpath($declared), $this->runner()->locateReference($test));
    }

    public function testFilenameSiblingStillUsedWhenNoLinkDeclared(): void
    {
        $test = $this->write('css/legacy/t-001.html', '<!doctype html><title>no link</title>');
        $ref = $this->write('css/legacy/t-001-ref.html', '<!doctype html>');

        self::assertSame(realpath($ref), $this->runner()->locateReference($test));
    }

    public function testUnresolvableDeclaredLinkFallsBackToFilenameSibling(): void
    {
        $test = $this->write('css/legacy/t-002.html', <<<'HTML'
            <!doctype html><link rel="match" href="does-not-exist.html">
            HTML);
        $ref = $this->write('css/legacy/t-002-ref.html', '<!doctype html>');

        self::assertSame(realpath($ref), $this->runner()->locateReference($test));
    }

    // ------------------------------------------------------------------
    // 3. Attribute-syntax tolerance
    // ------------------------------------------------------------------

    public function testUnquotedRelAndHrefAttributes(): void
    {
        $test = $this->write('css/q/t.html', '<!doctype html><link rel=match href=t-expected.html>');
        $ref = $this->write('css/q/t-expected.html', '<!doctype html>');

        self::assertSame(realpath($ref), $this->runner()->locateReference($test));
    }

    public function testHrefFragmentAndQueryAreStrippedForDiskLookup(): void
    {
        $test = $this->write('css/q/frag.html', '<!doctype html><link rel="match" href="frag-expected.html#top">');
        $ref = $this->write('css/q/frag-expected.html', '<!doctype html>');

        self::assertSame(realpath($ref), $this->runner()->locateReference($test));
    }

    public function testRelMatchIsExactNotSubstring(): void
    {
        // `rel="mismatch"` is the negative relation — WPT's
        // `[@rel='match']` does not select it, and neither do we.
        $test = $this->write('css/q/mm.html', '<!doctype html><link rel="mismatch" href="mm-other.html">');
        $this->write('css/q/mm-other.html', '<!doctype html>');

        self::assertNull($this->runner()->locateReference($test));
    }

    public function testEarlierNonMatchLinksDoNotShadowTheMatchLink(): void
    {
        $test = $this->write('css/q/multi.html', <<<'HTML'
            <!doctype html>
            <link rel="author" title="Someone" href="mailto:a@example.com">
            <link rel="help" href="https://example.com/spec">
            <link rel="stylesheet" href="support/base.css">
            <link href="multi-expected.html" rel="match">
            HTML);
        $ref = $this->write('css/q/multi-expected.html', '<!doctype html>');

        self::assertSame(realpath($ref), $this->runner()->locateReference($test));
    }

    public function testDataHrefAttributeIsNotMistakenForHref(): void
    {
        $test = $this->write('css/q/dh.html', '<!doctype html><link rel=match data-href=wrong.html href=dh-expected.html>');
        $this->write('css/q/wrong.html', '<!doctype html>');
        $ref = $this->write('css/q/dh-expected.html', '<!doctype html>');

        self::assertSame(realpath($ref), $this->runner()->locateReference($test));
    }

    public function testRootRelativeHrefResolvesAgainstCorpusRoot(): void
    {
        $test = $this->write('css/q/abs.html', '<!doctype html><link rel="match" href="/css/shared/green-ref.html">');
        $ref = $this->write('css/shared/green-ref.html', '<!doctype html>');

        self::assertSame(realpath($ref), $this->runner()->locateReference($test));
    }
}
