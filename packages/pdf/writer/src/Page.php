<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Color\ColorInterface;
use Phpdftk\FontMetrics\StandardFontMetrics;
use Phpdftk\Geometry\Rectangle;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Document\Page as CorePage;
use Phpdftk\Pdf\Core\PdfArray;
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
        bool $underline = false,
        bool $strikethrough = false,
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

        if ($underline || $strikethrough) {
            $textWidth = TextLayout::measure(
                $text,
                StandardFontMetrics::get($font->getFamily()),
                $size,
            );
            $strokeW = max(0.5, $size * 0.05);
            $cs->setLineWidth($strokeW);
            if ($color !== null) {
                $vals = $color->toArray();
                $cs->setStrokeColorRGB($vals[0] ?? 0, $vals[1] ?? 0, $vals[2] ?? 0);
            } else {
                $cs->setStrokeColorRGB(0.0, 0.0, 0.0);
            }
            if ($underline) {
                $uy = $y - $size * 0.12;
                $cs->moveTo($x, $uy)->lineTo($x + $textWidth, $uy)->stroke();
            }
            if ($strikethrough) {
                $sy = $y + $size * 0.28;
                $cs->moveTo($x, $sy)->lineTo($x + $textWidth, $sy)->stroke();
            }
        }

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
        } elseif ($width === null) {
            // Reached only when $height is non-null (the both-null and
            // width-only branches above are exhausted).
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
    // Tables
    // -----------------------------------------------------------------------

    /**
     * Draw a {@see Table} at `(x, y)`. The top of the table sits at
     * `y`; rows render downward.
     *
     * `$table->columnWidths` must be set — `Writer\Page` is the
     * positioned API and does not know the surrounding content column.
     * For the auto-equal-columns convenience, use `Pdf::addTable()`.
     *
     * Only the standard 14 fonts are supported by this signature; for
     * custom fonts, construct a {@see TableRenderContext} manually and
     * call {@see TableRenderer} directly.
     */
    public function drawTable(
        Table $table,
        float $x,
        float $y,
        Font $bodyFont,
        ?Font $headerFont = null,
        float $fontSize = 11.0,
        float $lineHeight = 1.2,
        ?TableStyle $style = null,
    ): self {
        if ($table->columnWidths === null) {
            throw new \InvalidArgumentException(
                'Writer\\Page::drawTable() requires Table::$columnWidths to be set; '
                . 'use Pdf::addTable() for auto-equal columns.',
            );
        }

        $style ??= new TableStyle();
        $headerFont ??= $bodyFont;

        $bodyMetrics = StandardFontMetrics::get($bodyFont->getFamily());
        $headerMetrics = $headerFont === $bodyFont
            ? $bodyMetrics
            : StandardFontMetrics::get($headerFont->getFamily());

        $ctx = new TableRenderContext(
            bodyFont: $bodyFont,
            bodyMetrics: $bodyMetrics,
            headerFont: $headerFont,
            headerMetrics: $headerMetrics,
            fontSize: $fontSize,
            lineHeight: $lineHeight,
            style: $style,
        );

        $cs = $this->ensureContentStream();
        $renderer = new TableRenderer();
        $cursorY = $y;

        if ($table->headerRow !== null) {
            $hh = $renderer->drawRow(
                $cs,
                $x,
                $cursorY,
                $table->headerRow,
                $table->columnWidths,
                $ctx,
                isHeader: true,
            );
            $cursorY -= $hh;
        }

        foreach ($table->rows as $row) {
            $rh = $renderer->drawRow(
                $cs,
                $x,
                $cursorY,
                $row,
                $table->columnWidths,
                $ctx,
                isHeader: false,
            );
            $cursorY -= $rh;
        }

        return $this;
    }

    // -----------------------------------------------------------------------
    // Gradients (shading patterns)
    // -----------------------------------------------------------------------

    /**
     * Register a {@see ShadingPattern} as a pattern resource on this
     * page and return the resource name to use with
     * {@see ContentStream::setFillColorSpace}() / `setFillColor()`.
     *
     * Typical use:
     *   $g = $doc->addLinearGradient(new Point(0,0), new Point(200,0), [1,0,0], [0,0,1]);
     *   $name = $page->useGradient($g);
     *   $page->contentStream()
     *       ->setFillColorSpace('Pattern')
     *       ->setFillColor("/{$name}")  // tinted patterns: setFillColor('1.0 /Name scn')
     *       ->rectangle(72, 600, 200, 80)
     *       ->fill();
     */
    public function useGradient(\Phpdftk\Pdf\Core\Graphics\Pattern\ShadingPattern $pattern): string
    {
        $key = 'P' . $pattern->objectNumber;
        $resources = $this->corePage->resources;
        if ($resources === null) {
            return $key;
        }
        if (!isset($resources->pattern[$key])) {
            $resources->pattern[$key] = new PdfReference($pattern->objectNumber);
        }
        return $key;
    }

    // -----------------------------------------------------------------------
    // Spot colors
    // -----------------------------------------------------------------------

    /**
     * Attach a registered spot color to this page's resources and
     * return the resource name to use in content-stream `cs` / `CS`
     * operators (via {@see ContentStream::setFillColorSpace()} /
     * {@see ContentStream::setStrokeColorSpace()}).
     */
    public function useSpotColor(SpotColor $spot): string
    {
        $key = 'CS_' . preg_replace('/[^A-Za-z0-9]+/', '_', $spot->name);
        $resources = $this->corePage->resources;
        if ($resources === null) {
            return $key;
        }
        if (!isset($resources->colorSpace[$key])) {
            $resources->colorSpace[$key] = $spot->separation;
        }
        return $key;
    }

    // -----------------------------------------------------------------------
    // Barcodes
    // -----------------------------------------------------------------------

    /**
     * Render a barcode at `(x, y)` (lower-left of the quiet zone).
     * The bitmap is produced by
     * {@see \Phpdftk\Barcode\BarcodeRenderer::render()} and drawn
     * inline — for documents that emit the same barcode many times,
     * prefer {@see PdfDoc::createBarcode()} + {@see drawTemplate()}.
     */
    public function drawBarcode(
        \Phpdftk\Barcode\Symbology $symbology,
        string $data,
        float $x,
        float $y,
        ?\Phpdftk\Barcode\BarcodeOptions $options = null,
    ): self {
        $options ??= new \Phpdftk\Barcode\BarcodeOptions();
        $bitmap = \Phpdftk\Barcode\BarcodeRenderer::render($symbology, $data, $options);

        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();
        $cs->concatMatrix(1.0, 0.0, 0.0, 1.0, $x, $y);
        BarcodeRendering::renderInto($cs, $bitmap);
        $cs->restoreGraphicsState();
        return $this;
    }

    // -----------------------------------------------------------------------
    // Form XObject templates
    // -----------------------------------------------------------------------

    /**
     * Place a Form XObject template on this page at `(x, y)`. The
     * template's intrinsic dimensions come from its BBox; pass `$w`
     * and / or `$h` to scale it (`null` keeps the BBox dimension).
     *
     * The template's XObject reference is added to the page's
     * resource dict under a stable name (`Tpl<objNum>`) so repeated
     * draws of the same template reuse the same entry.
     */
    public function drawTemplate(
        \Phpdftk\Pdf\Core\Graphics\XObject\FormXObject $template,
        float $x,
        float $y,
        ?float $w = null,
        ?float $h = null,
    ): self {
        $bboxItems = $template->bBox->items;
        if (count($bboxItems) < 4) {
            throw new \InvalidArgumentException('Template has an invalid /BBox.');
        }
        $llx = $bboxItems[0] instanceof \Phpdftk\Pdf\Core\PdfNumber ? (float) $bboxItems[0]->value : 0.0;
        $lly = $bboxItems[1] instanceof \Phpdftk\Pdf\Core\PdfNumber ? (float) $bboxItems[1]->value : 0.0;
        $urx = $bboxItems[2] instanceof \Phpdftk\Pdf\Core\PdfNumber ? (float) $bboxItems[2]->value : 0.0;
        $ury = $bboxItems[3] instanceof \Phpdftk\Pdf\Core\PdfNumber ? (float) $bboxItems[3]->value : 0.0;
        $tplW = $urx - $llx;
        $tplH = $ury - $lly;
        $sx = $w === null ? 1.0 : ($tplW > 0 ? $w / $tplW : 1.0);
        $sy = $h === null ? 1.0 : ($tplH > 0 ? $h / $tplH : 1.0);

        $name = $this->ensureTemplateResource($template);
        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState()
            ->concatMatrix($sx, 0.0, 0.0, $sy, $x - $llx * $sx, $y - $lly * $sy)
            ->doXObject($name)
            ->restoreGraphicsState();
        return $this;
    }

    private function ensureTemplateResource(\Phpdftk\Pdf\Core\Graphics\XObject\FormXObject $template): string
    {
        $name = 'Tpl' . $template->objectNumber;
        $resources = $this->corePage->resources;
        if ($resources === null) {
            return $name;
        }
        if (!isset($resources->xObject[$name])) {
            $resources->addXObject($name, new PdfReference($template->objectNumber));
        }
        return $name;
    }

    // -----------------------------------------------------------------------
    // Optional content (layers)
    // -----------------------------------------------------------------------

    /**
     * Wrap a closure's drawing operations as marked content belonging
     * to the given optional-content group (layer). The closure runs
     * between `/OC /<name> BDC` and `EMC`, and the OCG reference is
     * added to this page's `/Properties` resource under a unique name.
     *
     * Viewers that support optional content (Acrobat, Foxit, etc.)
     * will toggle the wrapped drawing on / off when the layer is
     * shown / hidden.
     *
     * @param \Closure(self): void $body
     */
    public function inLayer(\Phpdftk\Pdf\Core\Document\OCG $layer, \Closure $body): self
    {
        $propName = $this->ensureLayerProperty($layer);
        $cs = $this->ensureContentStream();
        $cs->beginMarkedContentWithProperties('OC', '/' . $propName);
        $body($this);
        $cs->endMarkedContent();
        return $this;
    }

    /**
     * Register the OCG with this page's /Properties resource, keyed by
     * a stable name (`MC<objNum>`) so repeated calls reuse the entry.
     */
    private function ensureLayerProperty(\Phpdftk\Pdf\Core\Document\OCG $layer): string
    {
        $key = 'MC' . $layer->objectNumber;
        $resources = $this->corePage->resources;
        if ($resources === null) {
            return $key;
        }
        if (isset($resources->properties[$key])) {
            return $key;
        }
        $resources->properties[$key] = new PdfReference($layer->objectNumber);
        return $key;
    }

    // -----------------------------------------------------------------------
    // Graphics state transforms + opacity
    // -----------------------------------------------------------------------

    /**
     * Concatenate a rotation onto the current transformation matrix.
     * If `$cx` / `$cy` are given, rotation is around that point;
     * otherwise around the origin (0, 0).
     *
     * Subsequent drawing inherits this rotation until the next graphics
     * state restore. Wrap calls in `withTransform()` for scoped effects.
     */
    public function rotate(float $degrees, ?float $cx = null, ?float $cy = null): self
    {
        $rad = deg2rad($degrees);
        $cos = cos($rad);
        $sin = sin($rad);
        $cs = $this->ensureContentStream();
        if ($cx === null && $cy === null) {
            $cs->concatMatrix($cos, $sin, -$sin, $cos, 0.0, 0.0);
        } else {
            $cx ??= 0.0;
            $cy ??= 0.0;
            $e = $cx - $cx * $cos + $cy * $sin;
            $f = $cy - $cx * $sin - $cy * $cos;
            $cs->concatMatrix($cos, $sin, -$sin, $cos, $e, $f);
        }
        return $this;
    }

    /** Concatenate a non-uniform scale onto the CTM. */
    public function scale(float $sx, float $sy): self
    {
        $this->ensureContentStream()->concatMatrix($sx, 0.0, 0.0, $sy, 0.0, 0.0);
        return $this;
    }

    /** Concatenate a translation onto the CTM. */
    public function translate(float $tx, float $ty): self
    {
        $this->ensureContentStream()->concatMatrix(1.0, 0.0, 0.0, 1.0, $tx, $ty);
        return $this;
    }

    /**
     * Concatenate a skew transform onto the CTM. `$alphaDeg` shears
     * along the X axis, `$betaDeg` along the Y axis.
     */
    public function skew(float $alphaDeg, float $betaDeg): self
    {
        $this->ensureContentStream()->concatMatrix(
            1.0,
            tan(deg2rad($betaDeg)),
            tan(deg2rad($alphaDeg)),
            1.0,
            0.0,
            0.0,
        );
        return $this;
    }

    /**
     * Scope a closure's drawing within a `q ... Q` (save/restore)
     * pair. Any transforms or graphics-state changes made inside the
     * closure are reverted on exit.
     *
     * @param \Closure(self): void $body
     */
    public function withTransform(\Closure $body): self
    {
        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();
        $body($this);
        $cs->restoreGraphicsState();
        return $this;
    }

    /**
     * Set the stroke / fill opacity for subsequent drawing. Registers
     * a fresh ExtGState resource keyed by the alpha values so opacity
     * can vary across the page without re-registering on every call.
     *
     * Stroke and fill default to the same value when only one
     * argument is provided.
     */
    public function setOpacity(float $stroke, ?float $fill = null): self
    {
        $fill ??= $stroke;
        $name = $this->ensureOpacityState($stroke, $fill);
        $this->ensureContentStream()->setGraphicsState($name);
        return $this;
    }

    /**
     * Lazily build (or reuse) an ExtGState resource for this page that
     * sets CA + ca and returns its resource name. The cache key is
     * derived from the alpha values so identical opacity calls reuse
     * the same registered ExtGState. Public so consumers writing to
     * additional content streams (e.g. the html-to-pdf painter) can
     * grab the resource name and emit `gs` themselves without going
     * through `setOpacity()`'s stream side effect.
     */
    public function ensureOpacityState(float $stroke, float $fill): string
    {
        $stroke = max(0.0, min(1.0, $stroke));
        $fill = max(0.0, min(1.0, $fill));
        $key = sprintf('GS_op_%.3f_%.3f', $stroke, $fill);

        if ($this->corePage->resources !== null && isset($this->corePage->resources->extGState[$key])) {
            return $key;
        }

        $extGState = new \Phpdftk\Pdf\Core\Graphics\ExtGState();
        $extGState->ca = $stroke;
        $extGState->caLower = $fill;
        $ref = $this->writer->register($extGState);
        $this->corePage->resources?->addExtGState($key, $ref);
        return $key;
    }

    // -----------------------------------------------------------------------
    // Page geometry (rotation + box rectangles)
    // -----------------------------------------------------------------------

    /**
     * Set the page rotation, in degrees clockwise. Only multiples of
     * 90 are valid per ISO 32000-2 § 7.7.3.3 — anything else throws.
     */
    public function setRotation(int $degrees): self
    {
        if ($degrees % 90 !== 0) {
            throw new \InvalidArgumentException(
                "Page rotation must be a multiple of 90 (got {$degrees}).",
            );
        }
        // Normalise to [0, 360): PDF readers accept negatives but
        // the canonical form is non-negative.
        $this->corePage->rotate = (($degrees % 360) + 360) % 360;
        return $this;
    }

    /**
     * Set /CropBox — the visible region when the page is displayed.
     * Defaults to MediaBox if unset.
     */
    public function setCropBox(Rectangle $rect): self
    {
        $this->corePage->cropBox = $this->rectToBoxArray($rect);
        return $this;
    }

    /**
     * Set /BleedBox — the area to be clipped when output is produced
     * for production presses.
     */
    public function setBleedBox(Rectangle $rect): self
    {
        $this->corePage->bleedBox = $this->rectToBoxArray($rect);
        return $this;
    }

    /**
     * Set /TrimBox — the intended dimensions of the finished page.
     */
    public function setTrimBox(Rectangle $rect): self
    {
        $this->corePage->trimBox = $this->rectToBoxArray($rect);
        return $this;
    }

    /**
     * Set /ArtBox — the page's meaningful content extent.
     */
    public function setArtBox(Rectangle $rect): self
    {
        $this->corePage->artBox = $this->rectToBoxArray($rect);
        return $this;
    }

    private function rectToBoxArray(Rectangle $rect): PdfArray
    {
        [$llx, $lly, $urx, $ury] = $rect->toArray();
        return new PdfArray([
            new \Phpdftk\Pdf\Core\PdfNumber($llx),
            new \Phpdftk\Pdf\Core\PdfNumber($lly),
            new \Phpdftk\Pdf\Core\PdfNumber($urx),
            new \Phpdftk\Pdf\Core\PdfNumber($ury),
        ]);
    }

    // -----------------------------------------------------------------------
    // Callout
    // -----------------------------------------------------------------------

    /**
     * Draw a callout panel at `(x, y)` with the given total `$width`.
     * The top of the panel sits at `$y`; body grows downward and the
     * returned float is the height consumed.
     *
     * The caller supplies the body and (optionally) title font handles.
     * Standard 14 fonts only — wrap-aware widths come from
     * {@see StandardFontMetrics}.
     */
    public function drawCallout(
        string $text,
        float $x,
        float $y,
        float $width,
        CalloutType $type,
        Font $bodyFont,
        ?Font $titleFont = null,
        float $size = 11.0,
        float $lineHeight = 1.2,
        ?CalloutStyle $style = null,
    ): float {
        $style ??= new CalloutStyle();
        $titleFont ??= $bodyFont;

        $bodyMetrics = StandardFontMetrics::get($bodyFont->getFamily());
        $padding = $style->padding;
        $barWidth = $style->barWidth;

        $textX = $x + $barWidth + $padding;
        $textWidth = max(0.0, $width - $barWidth - 2.0 * $padding);

        $encoded = $bodyFont->getTextEncoder()?->encode($text) ?? $text;
        $bodyLines = TextLayout::wrap($encoded, $bodyMetrics, $size, $textWidth);
        $lineH = $size * $lineHeight;
        $bodyHeight = count($bodyLines) * $lineH;

        $titleHeight = 0.0;
        $titleLabel = null;
        if ($style->showLabel) {
            $titleLabel = $style->resolveLabel($type);
            $titleHeight = $lineH;
        }

        $totalHeight = 2.0 * $padding + $titleHeight + $bodyHeight;
        $bottomY = $y - $totalHeight;

        [$br, $bg, $bb] = $style->resolveBarColor($type);
        [$bgR, $bgG, $bgB] = $style->resolveBgColor($type);

        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        $cs->setFillColorRGB($bgR, $bgG, $bgB)
            ->rectangle($x, $bottomY, $width, $totalHeight)
            ->fill();

        $cs->setFillColorRGB($br, $bg, $bb)
            ->rectangle($x, $bottomY, $barWidth, $totalHeight)
            ->fill();

        $textColor = $style->textColor ?? [0.0, 0.0, 0.0];
        $cs->setFillColorRGB($textColor[0], $textColor[1], $textColor[2]);
        $cursorY = $y - $padding;

        if ($titleLabel !== null) {
            $encodedTitle = $titleFont->getTextEncoder()?->encode($titleLabel) ?? $titleLabel;
            $baseline = $cursorY - $size;
            $cs->beginText()
                ->setFont($titleFont->getResourceName(), $size)
                ->moveTextPosition($textX, $baseline)
                ->showText($encodedTitle)
                ->endText();
            $cursorY -= $lineH;
        }

        foreach ($bodyLines as $line) {
            if ($line === '') {
                $cursorY -= $lineH;
                continue;
            }
            $baseline = $cursorY - $size;
            $cs->beginText()
                ->setFont($bodyFont->getResourceName(), $size)
                ->moveTextPosition($textX, $baseline)
                ->showText($line)
                ->endText();
            $cursorY -= $lineH;
        }

        $cs->restoreGraphicsState();
        return $totalHeight;
    }

    // -----------------------------------------------------------------------
    // Blockquote
    // -----------------------------------------------------------------------

    /**
     * Draw a blockquote at `(x, y)`: indented body text with a
     * coloured vertical bar down the left edge. The top of the quote
     * sits at `$y`; text and bar grow downward.
     *
     * The caller selects the font (typically the italic variant) and
     * the desired bar colour. Returns the height consumed.
     */
    public function drawQuote(
        string $text,
        float $x,
        float $y,
        Font $font,
        float $size = 11.0,
        float $maxWidth = 468.0,
        float $lineHeight = 1.2,
        float $indent = 18.0,
        float $barWidth = 2.0,
        ?ColorInterface $barColor = null,
        ?ColorInterface $textColor = null,
    ): self {
        $metrics = StandardFontMetrics::get($font->getFamily());
        $encoded = $font->getTextEncoder()?->encode($text) ?? $text;
        $textWidth = max(0.0, $maxWidth - $indent);
        $lines = TextLayout::wrap($encoded, $metrics, $size, $textWidth);

        $cs = $this->ensureContentStream();
        $cs->saveGraphicsState();

        if ($textColor !== null) {
            $this->applyFillColor($cs, $textColor);
        }

        $lineH = $size * $lineHeight;
        $textX = $x + $indent;
        $textTopY = $y;
        $cursorY = $y;
        foreach ($lines as $line) {
            if ($line === '') {
                $cursorY -= $lineH;
                continue;
            }
            $baselineY = $cursorY - $size;
            $cs->beginText()
                ->setFont($font->getResourceName(), $size)
                ->moveTextPosition($textX, $baselineY)
                ->showText($line)
                ->endText();
            $cursorY -= $lineH;
        }

        // Left bar
        if ($barColor !== null) {
            $vals = $barColor->toArray();
            $cs->setStrokeColorRGB($vals[0] ?? 0, $vals[1] ?? 0, $vals[2] ?? 0);
        } else {
            $cs->setStrokeColorRGB(0.7, 0.7, 0.7);
        }
        $cs->setLineWidth($barWidth);
        $barX = $x + $barWidth / 2.0;
        $cs->moveTo($barX, $textTopY - 2.0)
            ->lineTo($barX, $cursorY + 2.0)
            ->stroke();

        $cs->restoreGraphicsState();
        return $this;
    }

    // -----------------------------------------------------------------------
    // Lists
    // -----------------------------------------------------------------------

    /**
     * Draw a {@see ListBlock} at `(x, y)`. The marker for the first
     * item sits at `$x`; wrapped text starts one indent further right.
     *
     * Standard 14 fonts only — pass a custom font's metrics by going
     * through {@see ListRenderer::drawBlock()} directly.
     */
    public function drawList(
        ListBlock $list,
        float $x,
        float $y,
        Font $font,
        float $fontSize = 11.0,
        float $maxWidth = 468.0,
        float $lineHeight = 1.2,
        ?ListStyle $style = null,
    ): self {
        $style ??= new ListStyle();
        $metrics = StandardFontMetrics::get($font->getFamily());

        $renderer = new ListRenderer();
        $renderer->drawBlock(
            $this->ensureContentStream(),
            $x,
            $y,
            $list,
            $maxWidth,
            $font,
            $metrics,
            $fontSize,
            $lineHeight,
            $style,
        );
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
