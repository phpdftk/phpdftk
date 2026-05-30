<?php

declare(strict_types=1);

namespace Phpdftk\SvgToPdf\Tests;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Writer\PdfWriter;
use Phpdftk\Svg\Parser as SvgParser;
use Phpdftk\SvgToPdf\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 3Q — referenceable containers (`<defs>`, `<symbol>`) and the painters
 * that pull them in (`<use>`, `<image>`).
 *
 * `<defs>` and `<symbol>` are intentionally walked past at the document
 * level — they only paint when a `<use>` references them. `<image>`
 * resolves local-path hrefs through `PdfWriter::addImage` and rejects
 * `data:` / `http(s):` until the resource-loader gate from 1L lands.
 * `<clipPath>` and `<mask>` are deferred — documented in the plan +
 * README.
 */
final class UseAndImageTest extends TestCase
{
    private SvgParser $svgParser;
    private Translator $translator;

    protected function setUp(): void
    {
        $this->svgParser = new SvgParser();
        $this->translator = new Translator();
    }

    /**
     * @return array{ops: string, bytes: string}
     */
    private function paintWithWriter(string $svg): array
    {
        $writer = new PdfWriter();
        $page = $writer->addPage();
        $stream = $writer->addContentStream($page);
        $doc = $this->svgParser->parse($svg);
        $this->translator->paint($doc, $stream, $page, $writer);
        return [
            'ops' => implode("\n", $stream->getOperators()),
            'bytes' => $writer->toBytes(),
        ];
    }

    private function paintOpsOnly(string $svg): string
    {
        $doc = $this->svgParser->parse($svg);
        $stream = new ContentStream();
        $this->translator->paint($doc, $stream);
        return implode("\n", $stream->getOperators());
    }

    public function testDefsDoesNotPaintItsChildren(): void
    {
        $ops = $this->paintOpsOnly(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><rect width="10" height="10" fill="red"/></defs></svg>',
        );
        // The rect inside <defs> shouldn't reach the painter.
        self::assertSame('', $ops);
    }

    public function testSymbolDoesNotPaintItsChildren(): void
    {
        $ops = $this->paintOpsOnly(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<symbol id="s"><rect width="10" height="10" fill="red"/></symbol></svg>',
        );
        self::assertSame('', $ops);
    }

    public function testUseExpandsReferencedShapeAtTranslatedPosition(): void
    {
        $ops = $this->paintOpsOnly(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><rect id="box" width="10" height="10" fill="red"/></defs>'
            . '<use href="#box" x="50" y="20"/>'
            . '</svg>',
        );
        // Translate by (50, 20) → cm 1 0 0 1 50 20.
        self::assertStringContainsString('1 0 0 1 50 20 cm', $ops);
        // The referenced rect paints with its own coords.
        self::assertStringContainsString('0 0 10 10 re', $ops);
        // q / Q wrap so the translation doesn't leak.
        self::assertSame(1, substr_count("\n" . $ops . "\n", "\nq\n"));
        self::assertSame(1, substr_count("\n" . $ops . "\n", "\nQ\n"));
    }

    public function testUseAtZeroDoesNotEmitTranslationOp(): void
    {
        // No translation needed at (0, 0) → skip the q/cm/Q wrap so the
        // content stream stays clean.
        $ops = $this->paintOpsOnly(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<defs><rect id="box" width="10" height="10"/></defs>'
            . '<use href="#box"/></svg>',
        );
        self::assertStringNotContainsString(' cm', $ops);
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }

    public function testUseReferencingMissingIdSilentlyDrops(): void
    {
        $ops = $this->paintOpsOnly(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<use href="#nope" x="50" y="20"/></svg>',
        );
        self::assertSame('', $ops);
    }

    public function testUseReferencingSymbolPaintsItsChildren(): void
    {
        // `<symbol>` doesn't paint at the document level, but a `<use>`
        // pointing at it expands the contents. The transform painter at
        // 3M handles the q/cm/Q wrap automatically.
        $ops = $this->paintOpsOnly(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<symbol id="s"><rect width="10" height="10"/></symbol>'
            . '<use href="#s" x="20" y="20"/></svg>',
        );
        self::assertStringContainsString('1 0 0 1 20 20 cm', $ops);
        self::assertStringContainsString('0 0 10 10 re', $ops);
    }

    public function testUseExternalRefStillDropsSilently(): void
    {
        // Use_::href() already rejects external refs per the 3G
        // security posture; the painter just falls through to a no-op.
        $ops = $this->paintOpsOnly(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<use href="other.svg#box"/></svg>',
        );
        self::assertSame('', $ops);
    }

    public function testImageWithDataUrlIsRejectedAt3Q(): void
    {
        // Until 1L's resource-loader gate lands, `data:` is rejected
        // along with `http(s)`. The element drops silently.
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image x="0" y="0" width="10" height="10" '
            . 'href="data:image/png;base64,AAAA"/></svg>',
        );
        self::assertStringNotContainsString(' Do', $result['ops']);
    }

    public function testImageWithHttpHrefIsRejected(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image x="0" y="0" width="10" height="10" '
            . 'href="https://example.com/img.png"/></svg>',
        );
        self::assertStringNotContainsString(' Do', $result['ops']);
    }

    public function testImageWithMissingFileIsRejected(): void
    {
        $result = $this->paintWithWriter(
            '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<image x="0" y="0" width="10" height="10" '
            . 'href="/nonexistent-image-file-that-cannot-exist.png"/></svg>',
        );
        self::assertStringNotContainsString(' Do', $result['ops']);
    }

    public function testImageEmbedsLocalFileAndEmitsDoOperator(): void
    {
        if (!function_exists('imagepng')) {
            self::markTestSkipped('GD extension not available for PNG fixture.');
        }
        $path = $this->createPngFixture();
        try {
            $result = $this->paintWithWriter(
                '<svg xmlns="http://www.w3.org/2000/svg">'
                . sprintf(
                    '<image x="10" y="20" width="40" height="30" href="%s"/></svg>',
                    htmlspecialchars($path, ENT_XML1),
                ),
            );
            // The painter scales to (w, h) with Y flipped so the image
            // top-left lands at (x, y) — cm w 0 0 -h x (y+h).
            self::assertStringContainsString('40 0 0 -30 10 50 cm', $result['ops']);
            self::assertMatchesRegularExpression('!/Im\d+ Do!', $result['ops']);
            // PDF body should carry the image XObject.
            self::assertStringContainsString('/Subtype /Image', $result['bytes']);
        } finally {
            @unlink($path);
        }
    }

    public function testImageWithoutWidthOrHeightUsesIntrinsicDimensions(): void
    {
        if (!function_exists('imagepng')) {
            self::markTestSkipped('GD extension not available for PNG fixture.');
        }
        $path = $this->createPngFixture();
        try {
            // The fixture PNG is 20×30; with no width / height in the
            // SVG, the painter falls back to those intrinsic dims.
            $result = $this->paintWithWriter(
                '<svg xmlns="http://www.w3.org/2000/svg">'
                . sprintf(
                    '<image x="5" y="7" href="%s"/></svg>',
                    htmlspecialchars($path, ENT_XML1),
                ),
            );
            // cm w 0 0 -h x (y+h) = 20 0 0 -30 5 37.
            self::assertStringContainsString('20 0 0 -30 5 37 cm', $result['ops']);
        } finally {
            @unlink($path);
        }
    }

    public function testImageWithOnlyWidthScalesHeightByIntrinsicAspect(): void
    {
        if (!function_exists('imagepng')) {
            self::markTestSkipped('GD extension not available for PNG fixture.');
        }
        $path = $this->createPngFixture();
        try {
            // Intrinsic 20×30, width 40 → scaled height = 40 × (30/20) = 60.
            $result = $this->paintWithWriter(
                '<svg xmlns="http://www.w3.org/2000/svg">'
                . sprintf(
                    '<image x="0" y="0" width="40" href="%s"/></svg>',
                    htmlspecialchars($path, ENT_XML1),
                ),
            );
            self::assertStringContainsString('40 0 0 -60 0 60 cm', $result['ops']);
        } finally {
            @unlink($path);
        }
    }

    public function testImageWithOnlyHeightScalesWidthByIntrinsicAspect(): void
    {
        if (!function_exists('imagepng')) {
            self::markTestSkipped('GD extension not available for PNG fixture.');
        }
        $path = $this->createPngFixture();
        try {
            // Intrinsic 20×30, height 60 → scaled width = 60 × (20/30) = 40.
            $result = $this->paintWithWriter(
                '<svg xmlns="http://www.w3.org/2000/svg">'
                . sprintf(
                    '<image x="0" y="0" height="60" href="%s"/></svg>',
                    htmlspecialchars($path, ENT_XML1),
                ),
            );
            self::assertStringContainsString('40 0 0 -60 0 60 cm', $result['ops']);
        } finally {
            @unlink($path);
        }
    }

    public function testImageExplicitDimensionsBypassIntrinsicFallback(): void
    {
        if (!function_exists('imagepng')) {
            self::markTestSkipped('GD extension not available for PNG fixture.');
        }
        $path = $this->createPngFixture();
        try {
            // Both dimensions explicit → no intrinsic fallback, no
            // aspect preservation. The painter stretches the image to
            // the given rect.
            $result = $this->paintWithWriter(
                '<svg xmlns="http://www.w3.org/2000/svg">'
                . sprintf(
                    '<image x="0" y="0" width="100" height="50" href="%s"/></svg>',
                    htmlspecialchars($path, ENT_XML1),
                ),
            );
            self::assertStringContainsString('100 0 0 -50 0 50 cm', $result['ops']);
        } finally {
            @unlink($path);
        }
    }

    private function createPngFixture(): string
    {
        $image = imagecreatetruecolor(20, 30);
        self::assertNotFalse($image);
        $path = tempnam(sys_get_temp_dir(), 'phpdftk_svg_img_') . '.png';
        imagepng($image, $path);
        imagedestroy($image);
        return $path;
    }
}
