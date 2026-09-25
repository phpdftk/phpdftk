<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §8.4 / coords.html#TransformProperty — `transform` applies to
 * the OUTERMOST `<svg>` too, transforming everything it contains.
 *
 * `Translator::paint()` recursed straight into `paintChildren()` and
 * never ran the root element through `paintElement()`, so the root's
 * own `transform` was the one transform in the document that was
 * silently dropped.
 */
final class RootSvgTransformTest extends TestCase
{
    private function paint(string $svg): string
    {
        $doc = (new SvgParser())->parse($svg);
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testRootTransformIsApplied(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"'
            . ' transform="translate(100,0)">'
            . '<rect width="50" height="50" fill="green"/></svg>',
        );
        self::assertStringContainsString('1 0 0 1 100 0 cm', $ops);
    }

    public function testRootTransformComposesBeforeTheViewBoxShift(): void
    {
        // The viewBox origin shift is part of the coordinate system the
        // root transform acts ON, so the transform has to be
        // concatenated first.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"'
            . ' viewBox="10 20 400 400" transform="scale(2)">'
            . '<rect width="50" height="50" fill="green"/></svg>',
        );
        $scale = strpos($ops, '2 0 0 2 0 0 cm');
        $shift = strpos($ops, '1 0 0 1 -10 -20 cm');
        self::assertIsInt($scale);
        self::assertIsInt($shift);
        self::assertLessThan($shift, $scale);
    }

    public function testNoRootTransformEmitsNoMatrix(): void
    {
        // Guard: the common case must not grow a `q`/`cm`/`Q` wrap.
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400">'
            . '<rect width="50" height="50" fill="green"/></svg>',
        );
        self::assertStringNotContainsString(' cm', $ops);
    }

    public function testAnInvalidRootTransformIsIgnored(): void
    {
        $ops = $this->paint(
            '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"'
            . ' transform="nonsense(1)">'
            . '<rect width="50" height="50" fill="green"/></svg>',
        );
        self::assertStringNotContainsString(' cm', $ops);
        self::assertStringContainsString('0 0 50 50 re', $ops);
    }
}
