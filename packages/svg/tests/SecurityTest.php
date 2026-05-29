<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Exception\InvalidSvgException;
use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Text;
use PHPUnit\Framework\TestCase;

/**
 * Negative-bias tests on the secure-XML loader. These assert that the
 * Phase-3 parser does NOT silently dereference external entities, fetch
 * over the network, or resolve XInclude. A regression in any of these is
 * a high-severity bug — SVG is frequently user-supplied content.
 */
final class SecurityTest extends TestCase
{
    private Parser $parser;
    private string $secret;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        // Put a known-secret file on disk in a temp dir and confirm
        // that a classic XXE payload pointing at it does NOT end up in
        // the parsed tree.
        $this->secret = tempnam(sys_get_temp_dir(), 'phpdftk-svg-xxe-');
        file_put_contents($this->secret, 'TOPSECRET_PHPDFTK_XXE_CANARY');
    }

    protected function tearDown(): void
    {
        @unlink($this->secret);
    }

    private function collectAllTextData(\Phpdftk\Svg\Element $el): string
    {
        $out = '';
        foreach ($el->children as $child) {
            if ($child instanceof Text) {
                $out .= $child->data;
            } elseif ($child instanceof \Phpdftk\Svg\Element) {
                $out .= $this->collectAllTextData($child);
            }
        }
        return $out;
    }

    public function testXxeFileEntityDoesNotResolve(): void
    {
        $svg = sprintf(<<<XML
            <?xml version="1.0"?>
            <!DOCTYPE svg [<!ENTITY xxe SYSTEM "file://%s">]>
            <svg xmlns="http://www.w3.org/2000/svg">
              <title>&xxe;</title>
            </svg>
            XML, $this->secret);
        $doc = $this->parser->parse($svg);

        // The text child of <title>, if present, must NOT contain the
        // file contents. PHP's libxml may still surface the
        // *entity-reference* node, but only if substituteEntities is
        // active does the content get inlined — and we explicitly
        // leave that off (the bug is `LIBXML_NOENT`, the fix is its
        // absence).
        $titles = $doc->findByTag('title');
        self::assertCount(1, $titles);
        $title = $titles[0];
        foreach ($title->children as $child) {
            if ($child instanceof Text) {
                self::assertStringNotContainsString(
                    'TOPSECRET_PHPDFTK_XXE_CANARY',
                    $child->data,
                    'XXE payload leaked the canary file contents into the parsed tree.',
                );
            }
        }
    }

    public function testXxeNetworkEntityDoesNotFetch(): void
    {
        // A SYSTEM URL pointing at a port nothing listens on — if the
        // parser tried to fetch it, libxml would either succeed (very
        // bad) or block for the TCP timeout (also bad). LIBXML_NONET
        // makes the entity resolution fail synchronously instead.
        $svg = <<<'XML'
            <?xml version="1.0"?>
            <!DOCTYPE svg [<!ENTITY xxe SYSTEM "http://127.0.0.1:1/never-listened">]>
            <svg xmlns="http://www.w3.org/2000/svg">
              <title>&xxe;</title>
            </svg>
            XML;
        // Should parse OK (the entity is just not resolved), and
        // should return in well under a second — anything longer
        // means we attempted a TCP connect.
        $start = microtime(true);
        $this->parser->parse($svg);
        $elapsed = microtime(true) - $start;
        self::assertLessThan(
            0.5,
            $elapsed,
            'Parser appears to have attempted a network fetch for an external entity.',
        );
    }

    public function testXIncludeIsNotResolved(): void
    {
        // XInclude is opt-in via DOMDocument::xinclude() — we never
        // call it, so the element should pass through as a generic
        // unknown element and its `href` target should NOT be
        // fetched / inlined.
        $svg = sprintf(<<<XML
            <svg xmlns="http://www.w3.org/2000/svg"
                 xmlns:xi="http://www.w3.org/2001/XInclude">
              <xi:include href="file://%s"/>
            </svg>
            XML, $this->secret);
        $doc = $this->parser->parse($svg);
        $serialised = $this->collectAllTextData($doc);
        self::assertStringNotContainsString(
            'TOPSECRET_PHPDFTK_XXE_CANARY',
            $serialised,
            'XInclude appears to have been resolved.',
        );
    }
}
