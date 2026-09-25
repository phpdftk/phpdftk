<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * SVG 2 §5.8 — the conditional-processing attributes
 * (`requiredExtensions`, `systemLanguage`, and the legacy
 * `requiredFeatures`) apply to ANY direct rendering element, not only
 * to the children of a `<switch>`. An element whose conditions
 * evaluate false is not rendered.
 *
 * We only evaluated them while choosing a `<switch>` branch, and
 * treated a present-but-empty `requiredExtensions` as satisfied. The
 * spec is explicit that an empty list evaluates to false.
 */
final class ConditionalProcessingTest extends TestCase
{
    private function paint(string $body): string
    {
        $doc = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . $body . '</svg>',
        );
        $stream = new ContentStream();
        (new Translator())->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testEmptyRequiredExtensionsEvaluatesFalse(): void
    {
        $ops = $this->paint('<rect width="100" height="100" fill="red" requiredExtensions=""/>');
        self::assertSame('', $ops);
    }

    public function testRequiredExtensionsOutsideASwitchStillHidesTheElement(): void
    {
        $ops = $this->paint(
            '<rect width="100" height="100" fill="red"'
            . ' requiredExtensions="http://example.test/ext"/>',
        );
        self::assertSame('', $ops);
    }

    public function testSystemLanguageOutsideASwitchIsEvaluated(): void
    {
        $shown = $this->paint(
            '<rect width="10" height="10" fill="green" systemLanguage="en"/>',
        );
        $hidden = $this->paint(
            '<rect width="10" height="10" fill="green" systemLanguage="zz"/>',
        );
        self::assertStringContainsString('0 0 10 10 re', $shown);
        self::assertSame('', $hidden);
    }

    public function testAFailingConditionHidesTheElementsChildrenToo(): void
    {
        $ops = $this->paint(
            '<g requiredExtensions=""><rect width="10" height="10" fill="red"/></g>',
        );
        self::assertSame('', $ops);
    }

    public function testAnElementWithNoConditionalAttributesIsUnaffected(): void
    {
        // Guard: the check must not become a filter on ordinary content.
        $ops = $this->paint('<rect width="10" height="10" fill="green"/>');
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }

    public function testSwitchStillPicksTheFirstPassingBranch(): void
    {
        // Guard: the `<switch>` semantics are "first passing child
        // only", which is stricter than "every passing child".
        $ops = $this->paint(
            '<switch>'
            . '<rect width="10" height="10" fill="red" requiredExtensions="http://example.test/x"/>'
            . '<rect width="20" height="20" fill="green"/>'
            . '<rect width="30" height="30" fill="blue"/>'
            . '</switch>',
        );
        self::assertStringContainsString('0 0 20 20 re', $ops);
        self::assertStringNotContainsString('0 0 30 30 re', $ops);
        self::assertStringNotContainsString('0 0 10 10 re', $ops);
    }
}
