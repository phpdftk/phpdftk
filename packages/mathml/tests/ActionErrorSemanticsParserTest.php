<?php

declare(strict_types=1);

namespace Phpdftk\Mathml\Tests;

use Phpdftk\Mathml\Maction;
use Phpdftk\Mathml\Merror;
use Phpdftk\Mathml\Parser;
use Phpdftk\Mathml\Semantics;
use PHPUnit\Framework\TestCase;

/**
 * Parser tests confirming `<maction>`, `<merror>`, and
 * `<semantics>` produce their dedicated typed Element classes
 * instead of falling through to GenericElement.
 */
final class ActionErrorSemanticsParserTest extends TestCase
{
    public function testMactionDefaultsToSelection1(): void
    {
        $doc = (new Parser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<maction><mi>x</mi><mi>y</mi></maction>'
            . '</math>',
        );
        $maction = $this->firstAction($doc);
        self::assertSame(1, $maction->selection());
    }

    public function testMactionSelectionAttributeParses(): void
    {
        $doc = (new Parser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<maction selection="2"><mi>x</mi><mi>y</mi></maction>'
            . '</math>',
        );
        self::assertSame(2, $this->firstAction($doc)->selection());
    }

    public function testMactionInvalidSelectionClampsToOne(): void
    {
        foreach (['banana', '0', '-3', ''] as $bad) {
            $doc = (new Parser())->parse(
                '<math xmlns="http://www.w3.org/1998/Math/MathML">'
                . '<maction selection="' . htmlspecialchars($bad) . '">'
                . '<mi>x</mi></maction>'
                . '</math>',
            );
            self::assertSame(
                1,
                $this->firstAction($doc)->selection(),
                "selection=\"$bad\" should fall back to 1",
            );
        }
    }

    public function testMerrorElementType(): void
    {
        $doc = (new Parser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<merror><mtext>broken</mtext></merror>'
            . '</math>',
        );
        $children = array_values(array_filter(
            $doc->children,
            static fn($c) => $c instanceof Merror,
        ));
        self::assertCount(1, $children);
    }

    public function testSemanticsElementType(): void
    {
        $doc = (new Parser())->parse(
            '<math xmlns="http://www.w3.org/1998/Math/MathML">'
            . '<semantics><mi>x</mi>'
            . '<annotation encoding="TeX">x</annotation>'
            . '</semantics>'
            . '</math>',
        );
        $children = array_values(array_filter(
            $doc->children,
            static fn($c) => $c instanceof Semantics,
        ));
        self::assertCount(1, $children);
    }

    private function firstAction(\Phpdftk\Mathml\Element $doc): Maction
    {
        foreach ($doc->children as $child) {
            if ($child instanceof Maction) {
                return $child;
            }
        }
        self::fail('no <maction> child in document');
    }
}
