<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Color\ColorInterface;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Document\Page as CorePage;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\ImageMetadata\ImageParser;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\ICCBased;

/**
 * Level 1 Page — spatial drawing surface with explicit coordinates.
 *
 * Collects drawing operations and emits them to a ContentStream.
 * Each draw method wraps its operators in a graphics state save/restore
 * so drawings are isolated from each other.
 *
 * Escape hatches:
 *   $page->contentStream()  — raw ContentStream (Level 0 operators)
 *   $page->corePage()       — raw Core\Document\Page (Level 0 dict)
 */
final class Page
{
    private ?ContentStream $cs = null;

    /** @var \Closure(PdfStream): PdfReference */
    private \Closure $registerFn;

    /** @var \Closure(string, CorePage): string */
    private \Closure $addImageFn;

    public function __construct(
        private readonly CorePage $corePage,
        private readonly PdfWriter $writer,
    ) {
        // Registration closures to avoid exposing PdfWriter internals
        $this->registerFn = fn(PdfStream $obj): PdfReference => $this->writer->register($obj);
        $this->addImageFn = fn(string $path, CorePage $page): string => $this->writer->addImageInternal($path, $page);
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

    /**
     * Access the raw ContentStream for Level 0 operator control.
     */
    public function contentStream(): ContentStream
    {
        return $this->ensureContentStream();
    }

    /**
     * Access the raw Core\Document\Page for Level 0 dict manipulation.
     */
    public function corePage(): CorePage
    {
        return $this->corePage;
    }

    // -----------------------------------------------------------------------
    // Text
    // -----------------------------------------------------------------------

    /**
     * Draw text at a specific position.
     */
    public function drawText(
        string $text,
        float $x,
        float $y,
        Font $font,
        float $size = 12,
        ?ColorInterface $color = null,
    ): self {
        if ($text === '') {
            return $this;
        }

        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($color !== null) {
            $this->applyFillColor($cs, $color);
        }

        $cs->beginText()
            ->setFont($font->getResourceName(), $size)
            ->moveTextPosition($x, $y);

        $parsedData = $font->getParsedData();
        // For composite fonts registered via addCompositeFont, the Font
        // handle carries a post-subset Unicode → GID map; the pre-subset
        // map on the parsed font data points at glyphs that no longer
        // exist in the embedded subset.
        $unicodeToGid = $font->getUnicodeToGidMap();
        if ($unicodeToGid === [] && $parsedData !== null) {
            $unicodeToGid = $parsedData->fullUnicodeToGid;
        }
        if ($parsedData !== null && !empty($unicodeToGid)) {
            // Unicode font — use hex encoding with optional shaping
            if ($parsedData->ligatures !== null && $parsedData->ligatures !== []) {
                $cs->showUnicodeTextShaped(
                    $text,
                    $unicodeToGid,
                    $parsedData->ligatures,
                    $parsedData->kernPairs ?? [],
                    $parsedData->unitsPerEm,
                );
            } elseif ($parsedData->kernPairs !== null && $parsedData->kernPairs !== []) {
                $cs->showUnicodeTextKerned(
                    $text,
                    $unicodeToGid,
                    $parsedData->kernPairs,
                    $parsedData->unitsPerEm,
                );
            } else {
                $cs->showUnicodeText($text, $unicodeToGid);
            }
        } else {
            // Standard font — WinAnsi encoding
            $cs->showText($text);
        }

        $cs->endText();
        $cs->restoreGraphicsState();

        return $this;
    }

    // -----------------------------------------------------------------------
    // Basic shapes
    // -----------------------------------------------------------------------

    /**
     * Draw a straight line.
     */
    public function drawLine(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        ?ColorInterface $color = null,
        float $width = 1.0,
        ?DashPattern $dash = null,
    ): self {
        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($color !== null) {
            $this->applyStrokeColor($cs, $color);
        }
        $cs->setLineWidth($width);
        if ($dash !== null && $dash->pattern !== []) {
            $cs->setDashPattern($dash->pattern, (int) $dash->phase);
        }

        $cs->moveTo($x1, $y1)
            ->lineTo($x2, $y2)
            ->stroke();

        $cs->restoreGraphicsState();
        return $this;
    }

    /**
     * Draw a rectangle.
     */
    public function drawRectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        ?ColorInterface $fill = null,
        ?ColorInterface $stroke = null,
        float $strokeWidth = 1.0,
    ): self {
        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($fill !== null) {
            $this->applyFillColor($cs, $fill);
        }
        if ($stroke !== null) {
            $this->applyStrokeColor($cs, $stroke);
            $cs->setLineWidth($strokeWidth);
        }

        $cs->rectangle($x, $y, $width, $height);
        $this->paintPath($cs, $fill, $stroke);

        $cs->restoreGraphicsState();
        return $this;
    }

    /**
     * Draw a circle.
     */
    public function drawCircle(
        float $cx,
        float $cy,
        float $radius,
        ?ColorInterface $fill = null,
        ?ColorInterface $stroke = null,
        float $strokeWidth = 1.0,
    ): self {
        return $this->drawEllipse($cx, $cy, $radius, $radius, $fill, $stroke, $strokeWidth);
    }

    /**
     * Draw an ellipse.
     */
    public function drawEllipse(
        float $cx,
        float $cy,
        float $rx,
        float $ry,
        ?ColorInterface $fill = null,
        ?ColorInterface $stroke = null,
        float $strokeWidth = 1.0,
    ): self {
        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($fill !== null) {
            $this->applyFillColor($cs, $fill);
        }
        if ($stroke !== null) {
            $this->applyStrokeColor($cs, $stroke);
            $cs->setLineWidth($strokeWidth);
        }

        // Bézier approximation of ellipse (4 curves)
        $k = 0.5523; // magic constant for circle approximation
        $this->emitEllipseOps($cs, $cx, $cy, $rx, $ry, $k);
        $this->paintPath($cs, $fill, $stroke);

        $cs->restoreGraphicsState();
        return $this;
    }

    // -----------------------------------------------------------------------
    // Higher-level shapes
    // -----------------------------------------------------------------------

    /**
     * Draw a rectangle with rounded corners.
     */
    public function drawRoundedRectangle(
        float $x,
        float $y,
        float $width,
        float $height,
        float $radius,
        ?ColorInterface $fill = null,
        ?ColorInterface $stroke = null,
        float $strokeWidth = 1.0,
    ): self {
        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($fill !== null) {
            $this->applyFillColor($cs, $fill);
        }
        if ($stroke !== null) {
            $this->applyStrokeColor($cs, $stroke);
            $cs->setLineWidth($strokeWidth);
        }

        $r = min($radius, $width / 2, $height / 2);
        $k = 0.5523 * $r;

        // Start at top-left + radius, go clockwise
        $cs->moveTo($x + $r, $y + $height);
        // Top edge → top-right corner
        $cs->lineTo($x + $width - $r, $y + $height);
        $cs->curveTo($x + $width - $r + $k, $y + $height, $x + $width, $y + $height - $r + $k, $x + $width, $y + $height - $r);
        // Right edge → bottom-right corner
        $cs->lineTo($x + $width, $y + $r);
        $cs->curveTo($x + $width, $y + $r - $k, $x + $width - $r + $k, $y, $x + $width - $r, $y);
        // Bottom edge → bottom-left corner
        $cs->lineTo($x + $r, $y);
        $cs->curveTo($x + $r - $k, $y, $x, $y + $r - $k, $x, $y + $r);
        // Left edge → top-left corner
        $cs->lineTo($x, $y + $height - $r);
        $cs->curveTo($x, $y + $height - $r + $k, $x + $r - $k, $y + $height, $x + $r, $y + $height);

        $this->paintPath($cs, $fill, $stroke);
        $cs->restoreGraphicsState();
        return $this;
    }

    /**
     * Draw a polygon from a list of points.
     *
     * @param array<array{0: float, 1: float}> $points [[x,y], [x,y], ...]
     */
    public function drawPolygon(
        array $points,
        ?ColorInterface $fill = null,
        ?ColorInterface $stroke = null,
        float $strokeWidth = 1.0,
    ): self {
        if (count($points) < 2) {
            return $this;
        }

        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($fill !== null) {
            $this->applyFillColor($cs, $fill);
        }
        if ($stroke !== null) {
            $this->applyStrokeColor($cs, $stroke);
            $cs->setLineWidth($strokeWidth);
        }

        $cs->moveTo($points[0][0], $points[0][1]);
        for ($i = 1; $i < count($points); $i++) {
            $cs->lineTo($points[$i][0], $points[$i][1]);
        }
        $cs->closePath();
        $this->paintPath($cs, $fill, $stroke);

        $cs->restoreGraphicsState();
        return $this;
    }

    /**
     * Draw an arrow from (x1,y1) to (x2,y2) with a triangular arrowhead.
     */
    public function drawArrow(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $headSize = 8,
        ?ColorInterface $color = null,
        float $width = 1.0,
    ): self {
        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($color !== null) {
            $this->applyStrokeColor($cs, $color);
            $this->applyFillColor($cs, $color);
        }
        $cs->setLineWidth($width);

        // Draw the line
        $cs->moveTo($x1, $y1)->lineTo($x2, $y2)->stroke();

        // Draw the arrowhead
        $angle = atan2($y2 - $y1, $x2 - $x1);
        $a1 = $angle + M_PI - M_PI / 6; // 150 degrees from line direction
        $a2 = $angle + M_PI + M_PI / 6; // 210 degrees

        $cs->moveTo($x2, $y2);
        $cs->lineTo($x2 + $headSize * cos($a1), $y2 + $headSize * sin($a1));
        $cs->lineTo($x2 + $headSize * cos($a2), $y2 + $headSize * sin($a2));
        $cs->closePath();
        $cs->fill();

        $cs->restoreGraphicsState();
        return $this;
    }

    /**
     * Draw a star shape.
     *
     * @param int $points Number of points (5 = classic star)
     */
    public function drawStar(
        float $cx,
        float $cy,
        float $outerRadius,
        float $innerRadius,
        int $points = 5,
        ?ColorInterface $fill = null,
        ?ColorInterface $stroke = null,
        float $strokeWidth = 1.0,
    ): self {
        if ($points < 3) {
            return $this;
        }

        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($fill !== null) {
            $this->applyFillColor($cs, $fill);
        }
        if ($stroke !== null) {
            $this->applyStrokeColor($cs, $stroke);
            $cs->setLineWidth($strokeWidth);
        }

        $totalVertices = $points * 2;
        $angleStep = M_PI / $points;
        $startAngle = M_PI / 2; // start at top

        for ($i = 0; $i < $totalVertices; $i++) {
            $r = $i % 2 === 0 ? $outerRadius : $innerRadius;
            $angle = $startAngle + $i * $angleStep;
            $vx = $cx + $r * cos($angle);
            $vy = $cy + $r * sin($angle);

            if ($i === 0) {
                $cs->moveTo($vx, $vy);
            } else {
                $cs->lineTo($vx, $vy);
            }
        }
        $cs->closePath();
        $this->paintPath($cs, $fill, $stroke);

        $cs->restoreGraphicsState();
        return $this;
    }

    // -----------------------------------------------------------------------
    // Path builder
    // -----------------------------------------------------------------------

    /**
     * Draw a custom path using a PathBuilder closure.
     *
     * @param \Closure(PathBuilder): void $builder
     */
    public function drawPath(
        \Closure $builder,
        ?ColorInterface $fill = null,
        ?ColorInterface $stroke = null,
        float $strokeWidth = 1.0,
    ): self {
        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($fill !== null) {
            $this->applyFillColor($cs, $fill);
        }
        if ($stroke !== null) {
            $this->applyStrokeColor($cs, $stroke);
            $cs->setLineWidth($strokeWidth);
        }

        $path = new PathBuilder();
        $builder($path);
        $path->replayTo($cs);
        $this->paintPath($cs, $fill, $stroke);

        $cs->restoreGraphicsState();
        return $this;
    }

    // -----------------------------------------------------------------------
    // Images
    // -----------------------------------------------------------------------

    /**
     * Draw an image at a specific position.
     *
     * @param string $path File path to the image
     * @param float $x Left edge x coordinate
     * @param float $y Bottom edge y coordinate
     * @param float|null $width Display width (null = natural size in points at 72 DPI)
     * @param float|null $height Display height (null = proportional to width)
     */
    public function drawImage(
        string $path,
        float $x,
        float $y,
        ?float $width = null,
        ?float $height = null,
    ): self {
        $info = ImageParser::parse($path);
        $name = ($this->addImageFn)($path, $this->corePage);

        // Compute display dimensions
        $natWidth = (float) $info->width;
        $natHeight = (float) $info->height;

        if ($width === null && $height === null) {
            $width = $natWidth;
            $height = $natHeight;
        } elseif ($width !== null && $height === null) {
            $height = $natHeight * ($width / $natWidth);
        } elseif ($width === null && $height !== null) {
            $width = $natWidth * ($height / $natHeight);
        }

        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();
        $cs->concatMatrix($width, 0, 0, $height, $x, $y);
        $cs->doXObject($name);
        $cs->restoreGraphicsState();

        return $this;
    }

    // -----------------------------------------------------------------------
    // Raw escape
    // -----------------------------------------------------------------------

    /**
     * Execute raw ContentStream operations via a closure.
     *
     * @param \Closure(ContentStream): void $fn
     */
    public function raw(\Closure $fn): self
    {
        $fn($this->ensureContentStream());
        return $this;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private function ensureContentStream(): ContentStream
    {
        if ($this->cs === null) {
            $this->cs = new ContentStream();
            ($this->registerFn)($this->cs);
            $this->corePage->contents[] = new PdfReference($this->cs->objectNumber);
        }
        return $this->cs;
    }

    private function applyFillColor(ContentStream $cs, ColorInterface $color): void
    {
        $vals = $color->toArray();
        match ($color->getColorSpace()) {
            'DeviceRGB' => $cs->setFillColorRGB($vals[0], $vals[1], $vals[2]),
            'DeviceCMYK' => $cs->setFillColorCMYK($vals[0], $vals[1], $vals[2], $vals[3]),
            'DeviceGray' => $cs->setFillColorGray($vals[0]),
            default => $cs->setFillColorRGB($vals[0] ?? 0, $vals[1] ?? 0, $vals[2] ?? 0),
        };
    }

    private function applyStrokeColor(ContentStream $cs, ColorInterface $color): void
    {
        $vals = $color->toArray();
        match ($color->getColorSpace()) {
            'DeviceRGB' => $cs->setStrokeColorRGB($vals[0], $vals[1], $vals[2]),
            'DeviceCMYK' => $cs->setStrokeColorCMYK($vals[0], $vals[1], $vals[2], $vals[3]),
            'DeviceGray' => $cs->setStrokeColorGray($vals[0]),
            default => $cs->setStrokeColorRGB($vals[0] ?? 0, $vals[1] ?? 0, $vals[2] ?? 0),
        };
    }

    private function paintPath(ContentStream $cs, ?ColorInterface $fill, ?ColorInterface $stroke): void
    {
        if ($fill !== null && $stroke !== null) {
            $cs->fillAndStroke();
        } elseif ($fill !== null) {
            $cs->fill();
        } elseif ($stroke !== null) {
            $cs->stroke();
        } else {
            $cs->stroke(); // default to stroke if nothing specified
        }
    }

    private function emitEllipseOps(ContentStream $cs, float $cx, float $cy, float $rx, float $ry, float $k): void
    {
        $kx = $k * $rx;
        $ky = $k * $ry;

        $cs->moveTo($cx + $rx, $cy);
        $cs->curveTo($cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry);
        $cs->curveTo($cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy);
        $cs->curveTo($cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry);
        $cs->curveTo($cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy);
    }
}
