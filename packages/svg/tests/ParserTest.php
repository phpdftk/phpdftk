<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Tests;

use Phpdftk\Svg\Exception\InvalidSvgException;
use Phpdftk\Svg\GenericElement;
use Phpdftk\Svg\Group;
use Phpdftk\Svg\Parser;
use Phpdftk\Svg\Path;
use Phpdftk\Svg\Path\MoveTo;
use Phpdftk\Svg\Shape\Circle;
use Phpdftk\Svg\Shape\Ellipse;
use Phpdftk\Svg\Shape\Line;
use Phpdftk\Svg\Shape\Polygon;
use Phpdftk\Svg\Shape\Polyline;
use Phpdftk\Svg\Shape\Rect;
use Phpdftk\Svg\SvgDocument;
use Phpdftk\Svg\Text;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParsesMinimalSvgRoot(): void
    {
        $doc = $this->parser->parse('<svg xmlns="http://www.w3.org/2000/svg"/>');
        self::assertInstanceOf(SvgDocument::class, $doc);
        self::assertSame('svg', $doc->localName);
        self::assertSame([], $doc->children);
    }

    public function testParsesRootAttributes(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100" viewBox="0 0 200 100"/>',
        );
        self::assertSame('200', $doc->widthAttribute());
        self::assertSame('100', $doc->heightAttribute());
        self::assertSame([0.0, 0.0, 200.0, 100.0], $doc->viewBox());
    }

    public function testViewBoxAcceptsCommaSeparated(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0,0,100,100"/>',
        );
        self::assertSame([0.0, 0.0, 100.0, 100.0], $doc->viewBox());
    }

    public function testViewBoxRejectsNegativeDimensions(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 -10 100"/>',
        );
        self::assertNull($doc->viewBox());
    }

    public function testViewBoxRejectsWrongCount(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100"/>',
        );
        self::assertNull($doc->viewBox());
    }

    public function testViewBoxRejectsNonNumeric(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 abc"/>',
        );
        self::assertNull($doc->viewBox());
    }

    public function testParsesRectChildAsTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect x="10" y="20" width="30" height="40"/></svg>',
        );
        self::assertCount(1, $doc->children);
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(10.0, $rect->x());
        self::assertSame(20.0, $rect->y());
        self::assertSame(30.0, $rect->width());
        self::assertSame(40.0, $rect->height());
    }

    public function testRectDefaultsXYToZero(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="30" height="40"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(0.0, $rect->x());
        self::assertSame(0.0, $rect->y());
    }

    public function testRectStripsUnitFromLength(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="30px" height="40mm"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(30.0, $rect->width());
        self::assertSame(40.0, $rect->height());
    }

    public function testRectInvalidLengthFallsBackToZero(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="not-a-length" height="40"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(0.0, $rect->width());
        self::assertSame(40.0, $rect->height());
    }

    public function testRectRxFallsBackToRyAndViceVersa(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10" rx="3"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertSame(3.0, $rect->rx());
        self::assertSame(3.0, $rect->ry(), 'ry should fall back to rx when not set');
    }

    public function testRectRxRyNullWhenNeitherSet(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        self::assertNull($rect->rx());
        self::assertNull($rect->ry());
    }

    public function testUnknownElementBecomesGenericElement(): void
    {
        // `<arbitrary>` isn't a known SVG tag; should land as
        // GenericElement so attribute access still works.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><arbitrary x="0" data-something="hello"/></svg>',
        );
        self::assertCount(1, $doc->children);
        $node = $doc->children[0];
        self::assertInstanceOf(GenericElement::class, $node);
        self::assertSame('arbitrary', $node->localName);
        self::assertSame('hello', $node->getAttribute('data-something'));
    }

    public function testMarkerTypedAccessors(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<marker id="arrow" viewBox="0 0 10 10" refX="center" refY="5" '
            . 'markerWidth="6" markerHeight="6" orient="auto" markerUnits="userSpaceOnUse">'
            . '<path d="M 0 0 L 10 5 L 0 10 z"/>'
            . '</marker>'
            . '</svg>',
        );
        $marker = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Marker::class, $marker);
        self::assertSame([0.0, 0.0, 10.0, 10.0], $marker->viewBox());
        self::assertSame(5.0, $marker->refX()); // center on 0-10 viewBox = 5
        self::assertSame(5.0, $marker->refY());
        self::assertSame(6.0, $marker->markerWidth());
        self::assertSame(6.0, $marker->markerHeight());
        self::assertSame('auto', $marker->orient());
        self::assertSame('userSpaceOnUse', $marker->markerUnits());
    }

    public function testMarkerOrientAcceptsAngles(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<marker id="m" orient="45deg"/>'
            . '<marker id="m2" orient="0.5turn"/>'
            . '<marker id="m3" orient="auto-start-reverse"/>'
            . '</svg>',
        );
        self::assertSame(45.0, $doc->children[0]->orient());
        self::assertSame(180.0, $doc->children[1]->orient());
        self::assertSame('auto-start-reverse', $doc->children[2]->orient());
    }

    public function testForeignObjectTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<foreignObject x="10" y="20" width="100" height="50"/>'
            . '</svg>',
        );
        $fo = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\ForeignObject::class, $fo);
        self::assertSame(10.0, $fo->x());
        self::assertSame(20.0, $fo->y());
        self::assertSame(100.0, $fo->width());
        self::assertSame(50.0, $fo->height());
    }

    public function testParsesCircle(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="60" r="25"/></svg>',
        );
        $circle = $doc->children[0];
        self::assertInstanceOf(Circle::class, $circle);
        self::assertSame(50.0, $circle->cx());
        self::assertSame(60.0, $circle->cy());
        self::assertSame(25.0, $circle->r());
    }

    public function testCircleDefaultsCxCyToZero(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><circle r="10"/></svg>',
        );
        $circle = $doc->children[0];
        self::assertInstanceOf(Circle::class, $circle);
        self::assertSame(0.0, $circle->cx());
        self::assertSame(0.0, $circle->cy());
        self::assertSame(10.0, $circle->r());
    }

    public function testParsesEllipse(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><ellipse cx="50" cy="60" rx="30" ry="20"/></svg>',
        );
        $ellipse = $doc->children[0];
        self::assertInstanceOf(Ellipse::class, $ellipse);
        self::assertSame(50.0, $ellipse->cx());
        self::assertSame(60.0, $ellipse->cy());
        self::assertSame(30.0, $ellipse->rx());
        self::assertSame(20.0, $ellipse->ry());
    }

    public function testEllipseRxFallsBackToRyAndViceVersa(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><ellipse rx="5"/></svg>',
        );
        $ellipse = $doc->children[0];
        self::assertInstanceOf(Ellipse::class, $ellipse);
        self::assertSame(5.0, $ellipse->rx());
        self::assertSame(5.0, $ellipse->ry(), 'ry should fall back to rx when not set');
    }

    public function testEllipseRxRyNullWhenNeitherSet(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><ellipse cx="0" cy="0"/></svg>',
        );
        $ellipse = $doc->children[0];
        self::assertInstanceOf(Ellipse::class, $ellipse);
        self::assertNull($ellipse->rx());
        self::assertNull($ellipse->ry());
    }

    public function testParsesLine(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><line x1="1" y1="2" x2="3" y2="4"/></svg>',
        );
        $line = $doc->children[0];
        self::assertInstanceOf(Line::class, $line);
        self::assertSame(1.0, $line->x1());
        self::assertSame(2.0, $line->y1());
        self::assertSame(3.0, $line->x2());
        self::assertSame(4.0, $line->y2());
    }

    public function testLineDefaultsAllCoordsToZero(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><line/></svg>',
        );
        $line = $doc->children[0];
        self::assertInstanceOf(Line::class, $line);
        self::assertSame(0.0, $line->x1());
        self::assertSame(0.0, $line->y1());
        self::assertSame(0.0, $line->x2());
        self::assertSame(0.0, $line->y2());
    }

    public function testLineStripsUnitsFromCoords(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><line x1="10px" y1="20mm" x2="30em" y2="40%"/></svg>',
        );
        $line = $doc->children[0];
        self::assertInstanceOf(Line::class, $line);
        self::assertSame(10.0, $line->x1());
        self::assertSame(20.0, $line->y1());
        self::assertSame(30.0, $line->x2());
        self::assertSame(40.0, $line->y2());
    }

    public function testParsesPolylinePointsCommaAndSpaceSeparated(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><polyline points="0,0 10,10 20 20, 30 30"/></svg>',
        );
        $poly = $doc->children[0];
        self::assertInstanceOf(Polyline::class, $poly);
        self::assertSame(
            [[0.0, 0.0], [10.0, 10.0], [20.0, 20.0], [30.0, 30.0]],
            $poly->points(),
        );
    }

    public function testPolylineEmptyPointsAttributeYieldsEmptyList(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><polyline/></svg>',
        );
        $poly = $doc->children[0];
        self::assertInstanceOf(Polyline::class, $poly);
        self::assertSame([], $poly->points());
    }

    public function testPolylineOddCoordinateCountTrimsLastUnpaired(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><polyline points="1 2 3 4 5"/></svg>',
        );
        $poly = $doc->children[0];
        self::assertInstanceOf(Polyline::class, $poly);
        self::assertSame([[1.0, 2.0], [3.0, 4.0]], $poly->points());
    }

    public function testParsesPolygonPoints(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><polygon points="0,0 50,0 50,50 0,50"/></svg>',
        );
        $poly = $doc->children[0];
        self::assertInstanceOf(Polygon::class, $poly);
        self::assertSame(
            [[0.0, 0.0], [50.0, 0.0], [50.0, 50.0], [0.0, 50.0]],
            $poly->points(),
        );
    }

    public function testPolygonPointsAcceptsExponentNotation(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><polygon points="1e2,2e1 .5,-.5"/></svg>',
        );
        $poly = $doc->children[0];
        self::assertInstanceOf(Polygon::class, $poly);
        self::assertSame([[100.0, 20.0], [0.5, -0.5]], $poly->points());
    }

    public function testTextDataIsPreserved(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><title>my graphic</title></svg>',
        );
        $title = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Title::class, $title);
        self::assertCount(1, $title->children);
        $text = $title->children[0];
        self::assertInstanceOf(Text::class, $text);
        self::assertSame('my graphic', $text->data);
        self::assertSame('my graphic', $title->text());
    }

    public function testNestedFindByTagReturnsAllDescendantsInDocumentOrder(): void
    {
        $doc = $this->parser->parse(<<<XML
            <svg xmlns="http://www.w3.org/2000/svg">
              <g>
                <rect x="0" y="0" width="10" height="10"/>
                <g><rect x="10" y="10" width="10" height="10"/></g>
              </g>
              <rect x="20" y="20" width="10" height="10"/>
            </svg>
            XML);
        $rects = $doc->findByTag('rect');
        self::assertCount(3, $rects);
        $xs = [];
        foreach ($rects as $r) {
            self::assertInstanceOf(Rect::class, $r);
            $xs[] = $r->x();
        }
        self::assertSame([0.0, 10.0, 20.0], $xs);
    }

    public function testCommentsAreStripped(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><!-- a comment --><rect width="1" height="1"/></svg>',
        );
        self::assertCount(1, $doc->children);
        self::assertInstanceOf(Rect::class, $doc->children[0]);
    }

    public function testNamespaceDeclarationsAreNotCopiedAsAttributes(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"/>',
        );
        self::assertFalse($doc->hasAttribute('xmlns'));
        self::assertFalse($doc->hasAttribute('xmlns:xlink'));
    }

    public function testRejectsEmptyInput(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->parser->parse('');
    }

    public function testRejectsMalformedXml(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->parser->parse('<svg><rect></svg>');
    }

    public function testRejectsNonSvgRoot(): void
    {
        $this->expectException(InvalidSvgException::class);
        $this->expectExceptionMessage('Expected <svg> root');
        $this->parser->parse('<html><body/></html>');
    }

    public function testParsesGroupAsTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><g><rect width="1" height="1"/></g></svg>',
        );
        $group = $doc->children[0];
        self::assertInstanceOf(Group::class, $group);
        self::assertSame('g', $group->localName);
        self::assertCount(1, $group->children);
        self::assertInstanceOf(Rect::class, $group->children[0]);
    }

    public function testElementTransformAccessorReturnsNullWhenAbsent(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>',
        );
        $group = $doc->children[0];
        self::assertInstanceOf(Group::class, $group);
        self::assertNull($group->transform());
    }

    public function testElementTransformAccessorParsesAttribute(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><g transform="translate(10, 20)"/></svg>',
        );
        $group = $doc->children[0];
        self::assertInstanceOf(Group::class, $group);
        $t = $group->transform();
        self::assertNotNull($t);
        self::assertSame([1.0, 0.0, 0.0, 1.0, 10.0, 20.0], $t->toMatrix());
    }

    public function testElementTransformAccessorTreatsMalformedAsNull(): void
    {
        // SVG 2: invalid transform-attribute → element renders as if no
        // transform were specified. Our accessor returns null rather
        // than bubbling the InvalidArgumentException.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><g transform="nonsense(1 2 3)"/></svg>',
        );
        $group = $doc->children[0];
        self::assertInstanceOf(Group::class, $group);
        self::assertNull($group->transform());
    }

    public function testParsesPathAsTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><path d="M 10 20 L 30 40"/></svg>',
        );
        $path = $doc->children[0];
        self::assertInstanceOf(Path::class, $path);
        self::assertSame('M 10 20 L 30 40', $path->dRaw());
        $cmds = $path->d()->commands;
        self::assertCount(2, $cmds);
        self::assertInstanceOf(MoveTo::class, $cmds[0]);
    }

    public function testPathDAccessorReturnsEmptyDataWhenAttributeAbsent(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><path/></svg>',
        );
        $path = $doc->children[0];
        self::assertInstanceOf(Path::class, $path);
        self::assertNull($path->dRaw());
        self::assertSame([], $path->d()->commands);
    }

    public function testTransformAvailableOnAnyElementNotJustGroup(): void
    {
        // The accessor lives on Element so shapes can carry it directly.
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" transform="scale(2)"/></svg>',
        );
        $rect = $doc->children[0];
        self::assertInstanceOf(Rect::class, $rect);
        $t = $rect->transform();
        self::assertNotNull($t);
        self::assertSame([2.0, 0.0, 0.0, 2.0, 0.0, 0.0], $t->toMatrix());
    }

    public function testAnchorElementHasTypedHrefAccessor(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<a href="https://example.com" target="_blank">'
            . '<rect x="0" y="0" width="10" height="10"/>'
            . '</a></svg>',
        );
        $a = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\A_::class, $a);
        self::assertSame('https://example.com', $a->href());
        self::assertSame('_blank', $a->target());
    }

    public function testAnchorElementSupportsLegacyXlinkHref(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<a xlink:href="https://example.com"><rect width="1" height="1"/></a>'
            . '</svg>',
        );
        $a = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\A_::class, $a);
        self::assertSame('https://example.com', $a->href());
    }

    public function testDescTypedElement(): void
    {
        $doc = $this->parser->parse(
            '<svg xmlns="http://www.w3.org/2000/svg"><desc>Long description</desc></svg>',
        );
        $desc = $doc->children[0];
        self::assertInstanceOf(\Phpdftk\Svg\Desc::class, $desc);
        self::assertSame('Long description', $desc->text());
    }
}
