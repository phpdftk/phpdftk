<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Barcode\BarcodeOptions;
use Phpdftk\Barcode\BarcodeRenderer;
use Phpdftk\Barcode\Symbology;
use Phpdftk\Filesystem\LocalFilesystem;
use Phpdftk\Geometry\Point;
use Phpdftk\Geometry\Rectangle;
use Phpdftk\Pdf\Core\Annotation\Annotation as CoreAnnotation;
use Phpdftk\Pdf\Core\Annotation\BorderStyle;
use Phpdftk\Pdf\Core\Annotation\CaretAnnotation;
use Phpdftk\Pdf\Core\Annotation\CircleAnnotation;
use Phpdftk\Pdf\Core\Annotation\FreeTextAnnotation;
use Phpdftk\Pdf\Core\Annotation\HighlightAnnotation;
use Phpdftk\Pdf\Core\Annotation\InkAnnotation;
use Phpdftk\Pdf\Core\Annotation\LineAnnotation;
use Phpdftk\Pdf\Core\Annotation\LinkAnnotation;
use Phpdftk\Pdf\Core\Annotation\PolygonAnnotation;
use Phpdftk\Pdf\Core\Annotation\PolyLineAnnotation;
use Phpdftk\Pdf\Core\Annotation\SquareAnnotation;
use Phpdftk\Pdf\Core\Annotation\SquigglyAnnotation;
use Phpdftk\Pdf\Core\Annotation\StampAnnotation;
use Phpdftk\Pdf\Core\Annotation\StrikeOutAnnotation;
use Phpdftk\Pdf\Core\Annotation\TextAnnotation;
use Phpdftk\Pdf\Core\Annotation\UnderlineAnnotation;
use Phpdftk\Pdf\Core\Annotation\MovieAnnotation;
use Phpdftk\Pdf\Core\Annotation\SoundAnnotation;
use Phpdftk\Pdf\Core\Annotation\ThreeDAnnotation;
use Phpdftk\Pdf\Core\Annotation\WatermarkAnnotation;
use Phpdftk\Pdf\Core\Document\Destination;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\Page as CorePage;
use Phpdftk\Pdf\Core\Document\MetadataStream;
use Phpdftk\Pdf\Core\Document\NameTree;
use Phpdftk\Pdf\Core\Document\Outline;
use Phpdftk\Pdf\Core\Document\OutlineItem;
use Phpdftk\Pdf\Core\Document\PageLabel;
use Phpdftk\Pdf\Core\Document\OCG;
use Phpdftk\Pdf\Core\Document\OCPropertiesDict;
use Phpdftk\Pdf\Core\Document\ViewerPreferences;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\FileSpec\EmbeddedFile;
use Phpdftk\Pdf\Core\FileSpec\FileSpec;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\Separation;
use Phpdftk\Pdf\Core\Graphics\Function\FunctionType2;
use Phpdftk\Pdf\Core\Graphics\Function\FunctionType3;
use Phpdftk\Pdf\Core\Graphics\Pattern\ShadingPattern;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType2;
use Phpdftk\Pdf\Core\Graphics\Shading\ShadingType3;
use Phpdftk\Pdf\Core\Annotation\WidgetAnnotation;
use Phpdftk\Pdf\Core\Graphics\XObject\FormXObject;
use Phpdftk\Pdf\Core\Interactive\Form\AcroForm;
use Phpdftk\Pdf\Core\Interactive\Form\ButtonField;
use Phpdftk\Pdf\Core\Interactive\Form\ChoiceField;
use Phpdftk\Pdf\Core\Interactive\Form\SignatureField;
use Phpdftk\Pdf\Core\Interactive\Form\TextField;
use Phpdftk\Pdf\Writer\Form\CheckboxOptions;
use Phpdftk\Pdf\Writer\Form\ChoiceFieldOptions;
use Phpdftk\Pdf\Writer\Form\TextFieldOptions;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfString;
use Phpdftk\Pdf\Core\PdfVersion;

/**
 * Level 2 — friendly API over the PDF document object model.
 *
 * `PdfDoc` wraps a {@see PdfWriter} and exposes one method per "thing
 * a user wants to put in a document": pages, outlines, page labels,
 * named destinations, info/metadata. Later phases extend this with
 * annotation builders, form field builders, file attachments, viewer
 * preferences, action factories, layers, gradients, and more.
 *
 * The split between `PdfDoc` and `PdfWriter` is:
 *   - `PdfDoc` is about *what is in the document* (Catalog conveniences)
 *   - `PdfWriter` is about *how bytes get written* (fonts, images,
 *     content streams, signing, encryption, conformance, save)
 *
 * Drop down to the underlying {@see PdfWriter} via {@see writer()}
 * when you need direct byte/resource control (custom fonts,
 * encryption, etc.).
 *
 * @api
 */
class PdfDoc
{
    private PdfWriter $writer;

    /** Lazily-created OCPropertiesDict, shared across {@see addLayer()} calls. */
    private ?OCPropertiesDict $ocPropertiesDict = null;

    /** Lazily-created AcroForm, shared across all form-field builders. */
    private ?AcroForm $acroForm = null;

    public function __construct(
        bool $compressStreams = true,
        PdfVersion|string $version = PdfFileWriter::DEFAULT_PDF_VERSION,
    ) {
        $this->writer = new PdfWriter($compressStreams, $version);
    }

    /**
     * Wrap an existing PdfWriter — typically one already configured
     * with conformance, signing, or encryption — and expose the
     * friendly API on top of it.
     */
    public static function wrap(PdfWriter $writer): self
    {
        $instance = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->writer = $writer;
        return $instance;
    }

    /**
     * Escape hatch: the underlying PdfWriter for byte/resource control.
     */
    public function writer(): PdfWriter
    {
        return $this->writer;
    }

    /**
     * Add a new page. Friendly wrapper that returns the same
     * {@see Page} handle as PdfWriter::addPage().
     */
    public function addPage(Rectangle|float $widthOrRect = 612, float $height = 792): Page
    {
        return $this->writer->addPage($widthOrRect, $height);
    }

    // -----------------------------------------------------------------------
    // Document metadata (Info dict + XMP)
    // -----------------------------------------------------------------------

    public function setInfo(Info $info): self
    {
        $this->writer->fileWriter()->setInfo($info);
        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->ensureInfo()->title = new PdfString($title);
        return $this;
    }

    public function setAuthor(string $author): self
    {
        $this->ensureInfo()->author = new PdfString($author);
        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->ensureInfo()->subject = new PdfString($subject);
        return $this;
    }

    public function setKeywords(string $keywords): self
    {
        $this->ensureInfo()->keywords = new PdfString($keywords);
        return $this;
    }

    public function setCreator(string $creator): self
    {
        $this->ensureInfo()->creator = new PdfString($creator);
        return $this;
    }

    private function ensureInfo(): Info
    {
        $info = $this->writer->fileWriter()->getInfo();
        if ($info === null) {
            $info = new Info();
            $this->writer->fileWriter()->setInfo($info);
        }
        return $info;
    }

    /**
     * Attach an XMP metadata stream to the document catalog.
     */
    public function setMetadata(string $xmpXml): self
    {
        $metadataStream = new MetadataStream($xmpXml);
        $this->writer->register($metadataStream);
        $this->writer->getCatalog()->metadata = new PdfReference($metadataStream->objectNumber);
        return $this;
    }

    /**
     * Build and attach XMP metadata from the document's Info dictionary.
     *
     * Syncs Title, Author, Subject, Creator, Producer from the Info
     * dict into XMP properties (dc:title, dc:creator, dc:description,
     * xmp:CreatorTool, pdf:Producer) and attaches the result as a
     * MetadataStream on the Catalog.
     */
    public function syncInfoToMetadata(): self
    {
        $info = $this->writer->fileWriter()->getInfo();
        if ($info === null) {
            return $this;
        }

        $packet = \Phpdftk\Xmp\XmpPacket::create();
        if ($info->title !== null) {
            $packet = $packet->set('dc:title', $info->title->value);
        }
        if ($info->author !== null) {
            $packet = $packet->set('dc:creator', $info->author->value);
        }
        if ($info->subject !== null) {
            $packet = $packet->set('dc:description', $info->subject->value);
        }
        if ($info->creator !== null) {
            $packet = $packet->set('xmp:CreatorTool', $info->creator->value);
        }
        if ($info->producer !== null) {
            $packet = $packet->set('pdf:Producer', $info->producer->value);
        }

        $xmpXml = (new \Phpdftk\Xmp\XmpWriter())->serialize($packet);
        return $this->setMetadata($xmpXml);
    }

    // -----------------------------------------------------------------------
    // Form fields
    // -----------------------------------------------------------------------

    /**
     * Add a single-line (or multi-line) text input to the page. The
     * field is registered in the document's AcroForm and a Widget
     * annotation is attached to the page's `/Annots` array.
     *
     * Field flags (`/Ff`) are derived from {@see TextFieldOptions}:
     *   - required → bit 2 (0x0002)
     *   - readOnly → bit 1 (0x0001)
     *   - multiline → bit 13 (0x1000)
     *   - password  → bit 14 (0x2000)
     */
    public function addTextField(
        string $name,
        Page|CorePage $page,
        Rectangle $rect,
        ?TextFieldOptions $options = null,
    ): TextField {
        $options ??= new TextFieldOptions();
        $field = new TextField();
        $field->t = new PdfString($name);
        $field->da = new PdfString($options->defaultAppearance);
        $field->ff = $this->computeFieldFlags($options->required, $options->readOnly)
            | ($options->multiline ? 1 << 12 : 0)
            | ($options->password ? 1 << 13 : 0);
        if ($options->maxLength !== null) {
            $field->maxLen = $options->maxLength;
        }
        if ($options->defaultValue !== null) {
            $field->v = new PdfString($options->defaultValue);
            $field->dv = new PdfString($options->defaultValue);
        }
        $this->attachFieldWidget($page, $rect, $field);
        return $field;
    }

    /**
     * Add a checkbox to the page. The export value (the string
     * recorded as the field's `/V` when checked) defaults to `Yes`.
     */
    public function addCheckbox(
        string $name,
        Page|CorePage $page,
        Rectangle $rect,
        ?CheckboxOptions $options = null,
    ): ButtonField {
        $options ??= new CheckboxOptions();
        $field = new ButtonField();
        $field->t = new PdfString($name);
        $field->ff = $this->computeFieldFlags($options->required, $options->readOnly);
        if ($options->defaultChecked) {
            $field->v = new PdfName($options->onValue);
            $field->dv = new PdfName($options->onValue);
        } else {
            $field->v = new PdfName('Off');
            $field->dv = new PdfName('Off');
        }
        $this->attachFieldWidget($page, $rect, $field);
        return $field;
    }

    /**
     * Add a drop-down (combo) or list-box choice field. `$options`
     * carries the list of allowed `[value, label]` choices and the
     * usual required / read-only flags.
     */
    public function addChoiceField(
        string $name,
        Page|CorePage $page,
        Rectangle $rect,
        ChoiceFieldOptions $options,
    ): ChoiceField {
        $field = new ChoiceField();
        $field->t = new PdfString($name);
        $field->ff = $this->computeFieldFlags($options->required, $options->readOnly)
            | ($options->combo ? 1 << 17 : 0)
            | ($options->editable ? 1 << 18 : 0)
            | ($options->sort ? 1 << 19 : 0)
            | ($options->multiSelect ? 1 << 21 : 0);

        $optItems = [];
        foreach ($options->choices as $choice) {
            if (is_array($choice)) {
                $optItems[] = new PdfArray([
                    new PdfString($choice[0]),
                    new PdfString($choice[1]),
                ]);
            } else {
                $optItems[] = new PdfString($choice);
            }
        }
        $field->opt = new PdfArray($optItems);
        if ($options->defaultValue !== null) {
            $field->v = new PdfString($options->defaultValue);
            $field->dv = new PdfString($options->defaultValue);
        }
        $this->attachFieldWidget($page, $rect, $field);
        return $field;
    }

    /**
     * Add a signature field placeholder. Pair with
     * {@see PdfWriter::setSigner()} to actually sign the document at
     * generate time.
     */
    public function addSignatureField(
        string $name,
        Page|CorePage $page,
        Rectangle $rect,
    ): SignatureField {
        $field = new SignatureField();
        $field->t = new PdfString($name);
        $this->attachFieldWidget($page, $rect, $field);

        // Ensure the AcroForm declares /SigFlags = 3 (SignaturesExist
        // + AppendOnly) so viewers handle the file as signed-ready.
        $acroForm = $this->ensureAcroForm();
        $acroForm->sigFlags = ($acroForm->sigFlags ?? 0) | 3;
        return $field;
    }

    private function computeFieldFlags(bool $required, bool $readOnly): int
    {
        return ($readOnly ? 1 : 0) | ($required ? 2 : 0);
    }

    /**
     * Wire a field into both the AcroForm fields list and a Widget
     * annotation on the page. The Widget references the field as its
     * /Parent; the field's /Kids list points back at the widget.
     */
    private function attachFieldWidget(
        Page|CorePage $page,
        Rectangle $rect,
        \Phpdftk\Pdf\Core\Interactive\Form\Field $field,
    ): void {
        $widget = new WidgetAnnotation($this->rectToPdfArray($rect));
        $this->writer->register($widget);
        $this->writer->register($field);

        $field->kids[] = new PdfReference($widget->objectNumber);
        $widget->parent = new PdfReference($field->objectNumber);

        $corePage = $page instanceof Page ? $page->corePage() : $page;
        $corePage->annots[] = new PdfReference($widget->objectNumber);

        $acroForm = $this->ensureAcroForm();
        $acroForm->fields[] = new PdfReference($field->objectNumber);
    }

    private function ensureAcroForm(): AcroForm
    {
        if ($this->acroForm !== null) {
            return $this->acroForm;
        }
        $form = new AcroForm();
        // NeedAppearances asks viewers to generate widget appearances
        // on open — appropriate when the writer doesn't pre-build /AP.
        $form->needAppearances = true;
        $this->writer->register($form);
        $this->writer->getCatalog()->acroForm = new PdfReference($form->objectNumber);
        $this->acroForm = $form;
        return $form;
    }

    // -----------------------------------------------------------------------
    // Gradients
    // -----------------------------------------------------------------------

    /**
     * Register a two-stop axial (linear) gradient. The returned
     * {@see ShadingPattern} can be used as a fill via
     * {@see Writer\Page::useGradient()}.
     *
     * @param array{float,float,float} $startRgb RGB at gradient origin.
     * @param array{float,float,float} $endRgb   RGB at gradient end.
     */
    public function addLinearGradient(
        Point $from,
        Point $to,
        array $startRgb,
        array $endRgb,
        bool $extend = false,
    ): ShadingPattern {
        $fn = $this->buildRgbFunction($startRgb, $endRgb);
        $shading = new ShadingType2(
            new PdfName('DeviceRGB'),
            new PdfArray([
                new PdfNumber($from->x),
                new PdfNumber($from->y),
                new PdfNumber($to->x),
                new PdfNumber($to->y),
            ]),
            new PdfReference($fn->objectNumber),
        );
        if ($extend) {
            $shading->extend = self::extendBothEnds();
        }
        $this->writer->register($shading);

        $pattern = new ShadingPattern(new PdfReference($shading->objectNumber));
        $this->writer->register($pattern);
        return $pattern;
    }

    /**
     * Register a two-stop radial gradient. `$inner` / `$outer` are
     * concentric (or non-concentric) circles defining the gradient
     * boundary.
     *
     * @param array{float,float,float} $startRgb RGB at inner radius.
     * @param array{float,float,float} $endRgb   RGB at outer radius.
     */
    public function addRadialGradient(
        Point $innerCenter,
        float $innerRadius,
        Point $outerCenter,
        float $outerRadius,
        array $startRgb,
        array $endRgb,
        bool $extend = false,
    ): ShadingPattern {
        $fn = $this->buildRgbFunction($startRgb, $endRgb);
        $shading = new ShadingType3(
            new PdfName('DeviceRGB'),
            new PdfArray([
                new PdfNumber($innerCenter->x),
                new PdfNumber($innerCenter->y),
                new PdfNumber($innerRadius),
                new PdfNumber($outerCenter->x),
                new PdfNumber($outerCenter->y),
                new PdfNumber($outerRadius),
            ]),
            new PdfReference($fn->objectNumber),
        );
        if ($extend) {
            $shading->extend = self::extendBothEnds();
        }
        $this->writer->register($shading);

        $pattern = new ShadingPattern(new PdfReference($shading->objectNumber));
        $this->writer->register($pattern);
        return $pattern;
    }

    /**
     * Register an N-stop axial (linear) gradient. Each stop is a
     * `{offset, rgb}` pair with `offset` in [0, 1]. Stops must be
     * sorted ascending and start at 0 / end at 1 (the caller is
     * responsible for normalising — typical CSS gradient resolution
     * already does this). Two-stop input falls through to the same
     * Type-2 function as `addLinearGradient`; three-or-more produces
     * a Type-3 stitching function with N-1 Type-2 sub-functions.
     *
     * @param list<array{offset: float, rgb: array{float, float, float}}> $stops
     */
    public function addLinearGradientStops(
        Point $from,
        Point $to,
        array $stops,
        bool $extend = false,
    ): ShadingPattern {
        $fn = $this->buildRgbStopFunction($stops);
        $shading = new ShadingType2(
            new PdfName('DeviceRGB'),
            new PdfArray([
                new PdfNumber($from->x),
                new PdfNumber($from->y),
                new PdfNumber($to->x),
                new PdfNumber($to->y),
            ]),
            new PdfReference($fn->objectNumber),
        );
        if ($extend) {
            $shading->extend = self::extendBothEnds();
        }
        $this->writer->register($shading);

        $pattern = new ShadingPattern(new PdfReference($shading->objectNumber));
        $this->writer->register($pattern);
        return $pattern;
    }

    /**
     * Register an N-stop radial gradient. Same stop semantics as
     * {@see addLinearGradientStops()}.
     *
     * @param list<array{offset: float, rgb: array{float, float, float}}> $stops
     */
    public function addRadialGradientStops(
        Point $innerCenter,
        float $innerRadius,
        Point $outerCenter,
        float $outerRadius,
        array $stops,
        bool $extend = false,
    ): ShadingPattern {
        $fn = $this->buildRgbStopFunction($stops);
        $shading = new ShadingType3(
            new PdfName('DeviceRGB'),
            new PdfArray([
                new PdfNumber($innerCenter->x),
                new PdfNumber($innerCenter->y),
                new PdfNumber($innerRadius),
                new PdfNumber($outerCenter->x),
                new PdfNumber($outerCenter->y),
                new PdfNumber($outerRadius),
            ]),
            new PdfReference($fn->objectNumber),
        );
        if ($extend) {
            $shading->extend = self::extendBothEnds();
        }
        $this->writer->register($shading);

        $pattern = new ShadingPattern(new PdfReference($shading->objectNumber));
        $this->writer->register($pattern);
        return $pattern;
    }

    /**
     * Build a Function object that maps [0,1] → RGB through the given
     * stop list. Two stops produce a Type-2 (exponential, n=1, linear
     * interpolation). Three or more stops produce a Type-3 stitching
     * function: bounds at the intermediate stop offsets, N-1 child
     * Type-2 functions each linearly interpolating one segment.
     *
     * @param list<array{offset: float, rgb: array{float, float, float}}> $stops
     */
    private function buildRgbStopFunction(array $stops): FunctionType2|FunctionType3
    {
        if (count($stops) < 2) {
            throw new \InvalidArgumentException('Gradient requires at least 2 stops.');
        }
        if (count($stops) === 2) {
            return $this->buildRgbFunction($stops[0]['rgb'], $stops[1]['rgb']);
        }
        // Build N-1 segment functions linearly interpolating between
        // adjacent stops.
        $subFunctions = [];
        $bounds = [];
        $encode = [];
        $count = count($stops);
        for ($i = 0; $i < $count - 1; $i++) {
            $segment = $this->buildRgbFunction($stops[$i]['rgb'], $stops[$i + 1]['rgb']);
            $subFunctions[] = new PdfReference($segment->objectNumber);
            if ($i > 0) {
                $bounds[] = new PdfNumber($stops[$i]['offset']);
            }
            // Each segment consumes its full [0, 1] domain.
            $encode[] = new PdfNumber(0.0);
            $encode[] = new PdfNumber(1.0);
        }
        $fn = new FunctionType3(
            domain: new PdfArray([new PdfNumber(0.0), new PdfNumber(1.0)]),
            functions: new PdfArray($subFunctions),
            bounds: new PdfArray($bounds),
            encode: new PdfArray($encode),
        );
        $fn->range = new PdfArray([
            new PdfNumber(0.0), new PdfNumber(1.0),
            new PdfNumber(0.0), new PdfNumber(1.0),
            new PdfNumber(0.0), new PdfNumber(1.0),
        ]);
        $this->writer->register($fn);
        return $fn;
    }

    /**
     * @param array{float,float,float} $startRgb
     * @param array{float,float,float} $endRgb
     */
    private function buildRgbFunction(array $startRgb, array $endRgb): FunctionType2
    {
        $fn = new FunctionType2(
            domain: new PdfArray([new PdfNumber(0.0), new PdfNumber(1.0)]),
            c0: new PdfArray([
                new PdfNumber($startRgb[0]),
                new PdfNumber($startRgb[1]),
                new PdfNumber($startRgb[2]),
            ]),
            c1: new PdfArray([
                new PdfNumber($endRgb[0]),
                new PdfNumber($endRgb[1]),
                new PdfNumber($endRgb[2]),
            ]),
            n: 1.0,
        );
        $fn->range = new PdfArray([
            new PdfNumber(0.0), new PdfNumber(1.0),
            new PdfNumber(0.0), new PdfNumber(1.0),
            new PdfNumber(0.0), new PdfNumber(1.0),
        ]);
        $this->writer->register($fn);
        return $fn;
    }

    /**
     * Build `/Extend [true true]` — used by the `addLinearGradient*` /
     * `addRadialGradient*` registration helpers when the caller opts
     * into endpoint-pad semantics on a Type 2 / Type 3 shading.
     */
    private static function extendBothEnds(): PdfArray
    {
        return new PdfArray([true, true]);
    }

    // -----------------------------------------------------------------------
    // Spot colors
    // -----------------------------------------------------------------------

    /**
     * Register a spot color (a {@see Separation} color space). The
     * `$cmykTint` parameter specifies the device-CMYK approximation
     * used by viewers that don't have the spot ink — values are 0–1.
     *
     * Use the returned `Separation` with
     * {@see Writer\Page::useSpotColor()} to attach it to a page's
     * resources and obtain the resource name for content-stream ops:
     *
     *   $sep = $doc->registerSpotColor('Pantone 185 C', [0, 0.85, 0.6, 0]);
     *   $name = $page->useSpotColor($sep);
     *   $page->contentStream()
     *       ->setFillColorSpace($name)
     *       ->setFillColor(1.0)   // full tint
     *       ->rectangle(72, 600, 200, 80)
     *       ->fill();
     *
     * @param array{float,float,float,float} $cmykTint
     */
    public function registerSpotColor(string $name, array $cmykTint): SpotColor
    {
        $tintFn = new FunctionType2(
            domain: new PdfArray([new PdfNumber(0.0), new PdfNumber(1.0)]),
            c0: new PdfArray([
                new PdfNumber(0.0),
                new PdfNumber(0.0),
                new PdfNumber(0.0),
                new PdfNumber(0.0),
            ]),
            c1: new PdfArray([
                new PdfNumber($cmykTint[0]),
                new PdfNumber($cmykTint[1]),
                new PdfNumber($cmykTint[2]),
                new PdfNumber($cmykTint[3]),
            ]),
            n: 1.0,
        );
        $tintFn->range = new PdfArray([
            new PdfNumber(0.0),
            new PdfNumber(1.0),
            new PdfNumber(0.0),
            new PdfNumber(1.0),
            new PdfNumber(0.0),
            new PdfNumber(1.0),
            new PdfNumber(0.0),
            new PdfNumber(1.0),
        ]);
        $this->writer->register($tintFn);

        $separation = new Separation(
            new PdfName($name),
            new PdfName('DeviceCMYK'),
            new PdfReference($tintFn->objectNumber),
        );
        // `Separation` is a value type (implements Serializable) — it's
        // inlined into the page's /Resources /ColorSpace entry rather
        // than registered as an indirect object.
        return new SpotColor($name, $separation);
    }

    // -----------------------------------------------------------------------
    // Barcodes
    // -----------------------------------------------------------------------

    /**
     * Build a reusable {@see FormXObject} containing a barcode
     * rendering. The resulting template can be placed on multiple
     * pages via `Writer\Page::drawTemplate()`.
     *
     * Only `Symbology::Code128` is implemented in v1; other cases
     * throw at render time.
     */
    public function createBarcode(
        Symbology $symbology,
        string $data,
        ?BarcodeOptions $options = null,
    ): FormXObject {
        $options ??= new BarcodeOptions();
        $bitmap = BarcodeRenderer::render($symbology, $data, $options);

        return $this->createTemplate(
            new Rectangle(0.0, 0.0, $bitmap->totalWidth(), $bitmap->totalHeight()),
            function (ContentStream $cs) use ($bitmap): void {
                BarcodeRendering::renderInto($cs, $bitmap);
            },
        );
    }


    /**
     * Build a reusable Form XObject — a self-contained content stream
     * that can be placed on multiple pages without re-emitting the
     * underlying operators.
     *
     * The closure receives a fresh {@see ContentStream} sized to
     * `$bbox` (origin at `bbox->x, bbox->y`). Any drawing operators
     * the closure adds are captured into the FormXObject's stream;
     * resources (fonts, images) used inside the template must be
     * registered on the FormXObject's own resource dict — for v1, the
     * caller passes pre-registered Font handles into the closure if
     * needed and accepts that the template inherits resources from
     * the placing page.
     *
     * @param \Closure(ContentStream): void $draw
     */
    public function createTemplate(Rectangle $bbox, \Closure $draw): FormXObject
    {
        [$llx, $lly, $urx, $ury] = $bbox->toArray();
        $bboxArr = new PdfArray([
            new PdfNumber($llx),
            new PdfNumber($lly),
            new PdfNumber($urx),
            new PdfNumber($ury),
        ]);

        $cs = new ContentStream();
        $draw($cs);

        $template = new FormXObject($bboxArr, implode("\n", $cs->getOperators()));
        // Empty Resources so the placing page contributes shared
        // fonts / images via its own resource dict.
        $template->resources = new Resources();
        $this->writer->register($template);
        return $template;
    }

    // -----------------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------------

    /**
     * Set the document's open action — executed by the viewer when
     * the document is loaded. Typically used to jump to a specific
     * page or run JavaScript on open. The action is registered as an
     * indirect object; pass an instance from {@see Action}'s static
     * factories.
     */
    public function setOpenAction(\Phpdftk\Pdf\Core\Action\Action $action): self
    {
        $this->writer->register($action);
        $this->writer->getCatalog()->openAction = new PdfReference($action->objectNumber);
        return $this;
    }

    // -----------------------------------------------------------------------
    // Optional content (layers)
    // -----------------------------------------------------------------------

    /**
     * Register a new optional-content group (layer). The returned
     * `OCG` is referenced from the catalog's `/OCProperties` /OCGs
     * array; pass it to {@see Writer\Page::inLayer()} to tag drawing
     * operations as belonging to this layer.
     *
     * `$visible` controls the default state: visible layers go into
     * the default config's `/ON` list, hidden ones into `/OFF`.
     */
    public function addLayer(string $name, bool $visible = true): OCG
    {
        $ocg = new OCG($name);
        $this->writer->register($ocg);
        $ref = new PdfReference($ocg->objectNumber);

        $props = $this->ensureOCPropertiesDict();
        $props->ocgs = new PdfArray([...$props->ocgs->items, $ref]);

        $key = $visible ? 'ON' : 'OFF';
        $list = $props->d->get($key);
        $items = $list instanceof PdfArray ? $list->items : [];
        $items[] = $ref;
        $props->d->set($key, new PdfArray($items));

        return $ocg;
    }

    private function ensureOCPropertiesDict(): OCPropertiesDict
    {
        if ($this->ocPropertiesDict !== null) {
            return $this->ocPropertiesDict;
        }
        $defaultConfig = new PdfDictionary([
            'Name' => new PdfString('Default'),
            'BaseState' => new PdfName('ON'),
            'ON' => new PdfArray([]),
            'OFF' => new PdfArray([]),
        ]);
        $props = new OCPropertiesDict(new PdfArray([]), $defaultConfig);
        $this->writer->register($props);
        $this->writer->getCatalog()->ocProperties = new PdfReference($props->objectNumber);
        $this->ocPropertiesDict = $props;
        return $props;
    }

    // -----------------------------------------------------------------------
    // File attachments
    // -----------------------------------------------------------------------

    /**
     * Attach a file from disk. The file's bytes are read via
     * {@see LocalFilesystem::readFile()} and embedded as an
     * `EmbeddedFile`, wrapped in a `FileSpec`, and appended to the
     * catalog's `/AF` (Associated Files) array.
     *
     * `$relationship` populates `/AFRelationship` — the PDF 2.0 hint
     * to viewers about the file's role. ZUGFeRD invoices use
     * `Alternative` for the embedded XML; common values are `Source`,
     * `Data`, `Alternative`, `Supplement`, `EncryptedPayload`, and
     * `FormData`.
     */
    public function attachFile(
        string $path,
        ?string $description = null,
        ?string $mimeType = null,
        ?string $relationship = null,
    ): FileSpec {
        $bytes = LocalFilesystem::readFile($path);
        $name = basename($path);
        return $this->attachBytes($name, $bytes, $description, $mimeType, $relationship);
    }

    /**
     * Attach a file from in-memory bytes — useful when the source
     * isn't on disk (generated XML for ZUGFeRD, downloaded content,
     * etc.).
     */
    public function attachFileBytes(
        string $name,
        string $bytes,
        ?string $description = null,
        ?string $mimeType = null,
        ?string $relationship = null,
    ): FileSpec {
        return $this->attachBytes($name, $bytes, $description, $mimeType, $relationship);
    }

    private function attachBytes(
        string $name,
        string $bytes,
        ?string $description,
        ?string $mimeType,
        ?string $relationship,
    ): FileSpec {
        $embedded = new EmbeddedFile($bytes, $mimeType);
        $this->writer->register($embedded);

        $fileSpec = new FileSpec($name);
        $fileSpec->attachEmbeddedFile(new PdfReference($embedded->objectNumber));
        if ($description !== null) {
            $fileSpec->desc = new PdfString($description);
        }
        if ($relationship !== null) {
            $fileSpec->afRelationship = new PdfName($relationship);
        }
        $this->writer->register($fileSpec);

        $catalog = $this->writer->getCatalog();
        $existing = $catalog->af !== null ? $catalog->af->items : [];
        $existing[] = new PdfReference($fileSpec->objectNumber);
        $catalog->af = new PdfArray($existing);

        return $fileSpec;
    }

    // -----------------------------------------------------------------------
    // Viewer preferences
    // -----------------------------------------------------------------------

    /**
     * Set the document's viewer preferences. Accepts either a
     * pre-constructed {@see ViewerPreferences} object or a closure
     * that receives a fresh instance and mutates it.
     *
     * Closure form:
     *   $doc->setViewerPreferences(function (ViewerPreferences $vp): void {
     *       $vp->displayDocTitle = true;
     *       $vp->fitWindow = true;
     *   });
     */
    public function setViewerPreferences(ViewerPreferences|\Closure $prefs): self
    {
        if ($prefs instanceof \Closure) {
            $vp = new ViewerPreferences();
            $prefs($vp);
        } else {
            $vp = $prefs;
        }
        $this->writer->register($vp);
        $this->writer->getCatalog()->viewerPreferences = new PdfReference($vp->objectNumber);
        return $this;
    }

    // -----------------------------------------------------------------------
    // Annotations
    // -----------------------------------------------------------------------

    /**
     * Add a link annotation to a page.
     *
     * `$target` accepts:
     *   - **string** — treated as a URI; an inline /A action dict is built.
     *   - **Destination** — an explicit destination (use the named
     *     constructors `Destination::fit($pageRef)`,
     *     `Destination::xyz(...)`, etc.).
     *   - **PdfReference** — points to a named destination that has been
     *     registered via {@see setNamedDestinations()}.
     */
    public function addLink(
        Page|CorePage $page,
        Rectangle $rect,
        string|Destination|PdfReference $target,
        ?BorderStyle $border = null,
    ): LinkAnnotation {
        $corePage = $page instanceof Page ? $page->corePage() : $page;

        [$llx, $lly, $urx, $ury] = $rect->toArray();
        $rectArray = new PdfArray([
            new PdfNumber($llx),
            new PdfNumber($lly),
            new PdfNumber($urx),
            new PdfNumber($ury),
        ]);

        $annotation = new LinkAnnotation($rectArray);

        if (is_string($target)) {
            $actionDict = new PdfDictionary();
            $actionDict->set('Type', new PdfName('Action'));
            $actionDict->set('S', new PdfName('URI'));
            $actionDict->set('URI', new PdfString($target));
            $annotation->a = $actionDict;
        } else {
            $annotation->dest = $target;
        }

        if ($border !== null) {
            $annotation->bs = $border;
        }

        $this->writer->register($annotation);
        $corePage->annots[] = new PdfReference($annotation->objectNumber);

        return $annotation;
    }

    /**
     * Add a sticky-note ("text") annotation — a small icon that opens
     * a popup with `$content` text when clicked. `$point` is the
     * lower-left corner; the rect defaults to a 16×16 box around it.
     */
    public function addStickyNote(
        Page|CorePage $page,
        float $x,
        float $y,
        string $content,
        ?string $iconName = null,
    ): TextAnnotation {
        $rect = new Rectangle($x, $y, 16.0, 16.0);
        $annotation = new TextAnnotation($this->rectToPdfArray($rect));
        $annotation->contents = new PdfString($content);
        if ($iconName !== null) {
            $annotation->name = new PdfName($iconName);
        }
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a free-text annotation — text drawn directly on the page
     * (rather than in a popup like a sticky note). `$defaultAppearance`
     * is the PDF "default appearance" string controlling font + colour
     * (e.g. `/Helv 10 Tf 0 0 0 rg`).
     */
    public function addFreeText(
        Page|CorePage $page,
        Rectangle $rect,
        string $content,
        string $defaultAppearance = '/Helv 10 Tf 0 0 0 rg',
    ): FreeTextAnnotation {
        $annotation = new FreeTextAnnotation(
            $this->rectToPdfArray($rect),
            new PdfString($defaultAppearance),
        );
        $annotation->contents = new PdfString($content);
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a text-highlight annotation. `$quads` is a list of
     * `Rectangle`s — one per highlighted span (typically each text
     * line). The annotation's bounding rect is the union of all quads.
     *
     * @param list<Rectangle> $quads
     */
    public function addHighlight(Page|CorePage $page, array $quads): HighlightAnnotation
    {
        [$rectArr, $quadArr] = $this->quadsToArrays($quads);
        $annotation = new HighlightAnnotation($rectArr, $quadArr);
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add an underline annotation — visually similar to highlight but
     * draws a line under each text span. `$quads` is one rect per span.
     *
     * @param list<Rectangle> $quads
     */
    public function addUnderlineAnnotation(Page|CorePage $page, array $quads): UnderlineAnnotation
    {
        [$rectArr, $quadArr] = $this->quadsToArrays($quads);
        $annotation = new UnderlineAnnotation($rectArr);
        $annotation->quadPoints = $quadArr;
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a squiggly-underline annotation — wavy line below each text span.
     *
     * @param list<Rectangle> $quads
     */
    public function addSquiggly(Page|CorePage $page, array $quads): SquigglyAnnotation
    {
        [$rectArr, $quadArr] = $this->quadsToArrays($quads);
        $annotation = new SquigglyAnnotation($rectArr);
        $annotation->quadPoints = $quadArr;
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a strikeout annotation — line through each text span.
     *
     * @param list<Rectangle> $quads
     */
    public function addStrikeout(Page|CorePage $page, array $quads): StrikeOutAnnotation
    {
        [$rectArr, $quadArr] = $this->quadsToArrays($quads);
        $annotation = new StrikeOutAnnotation($rectArr);
        $annotation->quadPoints = $quadArr;
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a caret annotation — small upward-pointing wedge typically
     * used to mark an insertion point.
     */
    public function addCaret(Page|CorePage $page, Rectangle $rect): CaretAnnotation
    {
        $annotation = new CaretAnnotation($this->rectToPdfArray($rect));
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a free-form ink annotation. `$paths` is a list of strokes,
     * each stroke a flat list of `[x0, y0, x1, y1, ...]` points.
     *
     * @param list<list<float>> $paths
     */
    public function addInk(Page|CorePage $page, array $paths): InkAnnotation
    {
        $minX = PHP_FLOAT_MAX;
        $minY = PHP_FLOAT_MAX;
        $maxX = -PHP_FLOAT_MAX;
        $maxY = -PHP_FLOAT_MAX;
        $inkPaths = [];
        foreach ($paths as $path) {
            $pdfPath = [];
            $count = count($path);
            for ($i = 0; $i + 1 < $count; $i += 2) {
                $x = (float) $path[$i];
                $y = (float) $path[$i + 1];
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
                $pdfPath[] = new PdfNumber($x);
                $pdfPath[] = new PdfNumber($y);
            }
            $inkPaths[] = new PdfArray($pdfPath);
        }
        if ($minX === PHP_FLOAT_MAX) {
            $minX = $minY = $maxX = $maxY = 0.0;
        }
        $rectArr = new PdfArray([
            new PdfNumber($minX),
            new PdfNumber($minY),
            new PdfNumber($maxX),
            new PdfNumber($maxY),
        ]);
        $annotation = new InkAnnotation($rectArr, new PdfArray($inkPaths));
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a line annotation between two points.
     */
    public function addLineAnnotation(
        Page|CorePage $page,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
    ): LineAnnotation {
        $rectArr = new PdfArray([
            new PdfNumber(min($x1, $x2)),
            new PdfNumber(min($y1, $y2)),
            new PdfNumber(max($x1, $x2)),
            new PdfNumber(max($y1, $y2)),
        ]);
        $annotation = new LineAnnotation($rectArr);
        $annotation->l = new PdfArray([
            new PdfNumber($x1),
            new PdfNumber($y1),
            new PdfNumber($x2),
            new PdfNumber($y2),
        ]);
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a polygon annotation. `$points` is a list of `[x, y]` pairs;
     * the polygon is implicitly closed back to the first vertex.
     *
     * @param list<array{float,float}> $points
     */
    public function addPolygon(Page|CorePage $page, array $points): PolygonAnnotation
    {
        [$rectArr, $vertices] = $this->pointsToRectAndArray($points);
        $annotation = new PolygonAnnotation($rectArr);
        $annotation->vertices = $vertices;
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a polyline annotation — like a polygon but open (last point
     * does not connect back to the first).
     *
     * @param list<array{float,float}> $points
     */
    public function addPolyline(Page|CorePage $page, array $points): PolyLineAnnotation
    {
        [$rectArr, $vertices] = $this->pointsToRectAndArray($points);
        $annotation = new PolyLineAnnotation($rectArr);
        $annotation->vertices = $vertices;
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a rectangular shape annotation — visible as a stroked
     * rectangle on the page.
     */
    public function addSquare(Page|CorePage $page, Rectangle $rect): SquareAnnotation
    {
        $annotation = new SquareAnnotation($this->rectToPdfArray($rect));
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a circular / elliptical shape annotation — visible as a
     * stroked ellipse inscribed in the rectangle.
     */
    public function addCircleAnnotation(Page|CorePage $page, Rectangle $rect): CircleAnnotation
    {
        $annotation = new CircleAnnotation($this->rectToPdfArray($rect));
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a rubber-stamp annotation. `$stampName` is the standard
     * stamp identifier (`Approved`, `Confidential`, `Draft`, etc.).
     */
    public function addStamp(
        Page|CorePage $page,
        Rectangle $rect,
        string $stampName = 'Draft',
    ): StampAnnotation {
        $annotation = new StampAnnotation($this->rectToPdfArray($rect));
        $annotation->name = new PdfName($stampName);
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a watermark annotation — fixed page-level overlay that
     * doesn't print by default (PDF 1.7).
     */
    public function addWatermarkAnnotation(Page|CorePage $page, Rectangle $rect): WatermarkAnnotation
    {
        $annotation = new WatermarkAnnotation($this->rectToPdfArray($rect));
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a sound annotation. Caller supplies a pre-constructed
     * {@see \Phpdftk\Pdf\Core\Multimedia\Sound} stream (with sample
     * rate + bytes). Deprecated in PDF 2.0 — prefer Rich Media for
     * new documents.
     */
    public function addSoundAnnotation(
        Page|CorePage $page,
        Rectangle $rect,
        \Phpdftk\Pdf\Core\Multimedia\Sound $sound,
    ): SoundAnnotation {
        $this->writer->register($sound);
        $annotation = new SoundAnnotation($this->rectToPdfArray($rect));
        $annotation->sound = new PdfReference($sound->objectNumber);
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a movie annotation. Deprecated in PDF 2.0 in favour of
     * Rich Media / Screen annotations; provided for legacy workflows.
     */
    public function addMovieAnnotation(
        Page|CorePage $page,
        Rectangle $rect,
        \Phpdftk\Pdf\Core\Multimedia\Movie $movie,
    ): MovieAnnotation {
        $this->writer->register($movie);
        $annotation = new MovieAnnotation($this->rectToPdfArray($rect));
        $annotation->movie = new PdfReference($movie->objectNumber);
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * Add a 3D annotation. Caller supplies a pre-constructed
     * {@see \Phpdftk\Pdf\Core\ThreeD\ThreeDStream} containing the U3D
     * or PRC payload.
     */
    public function add3DAnnotation(
        Page|CorePage $page,
        Rectangle $rect,
        \Phpdftk\Pdf\Core\ThreeD\ThreeDStream $stream,
    ): ThreeDAnnotation {
        $this->writer->register($stream);
        $annotation = new ThreeDAnnotation($this->rectToPdfArray($rect));
        $annotation->dd = new PdfReference($stream->objectNumber);
        return $this->attachAnnotation($page, $annotation);
    }

    /**
     * @template T of CoreAnnotation
     * @param T $annotation
     * @return T
     */
    private function attachAnnotation(Page|CorePage $page, CoreAnnotation $annotation): CoreAnnotation
    {
        $corePage = $page instanceof Page ? $page->corePage() : $page;
        $this->writer->register($annotation);
        $corePage->annots[] = new PdfReference($annotation->objectNumber);
        return $annotation;
    }

    private function rectToPdfArray(Rectangle $rect): PdfArray
    {
        [$llx, $lly, $urx, $ury] = $rect->toArray();
        return new PdfArray([
            new PdfNumber($llx),
            new PdfNumber($lly),
            new PdfNumber($urx),
            new PdfNumber($ury),
        ]);
    }

    /**
     * Convert a list of Rectangles representing text-markup spans
     * into the bounding rect + quad-points array required by
     * highlight / underline / squiggly / strikeout annotations.
     *
     * @param list<Rectangle> $quads
     * @return array{0: PdfArray, 1: PdfArray}
     */
    private function quadsToArrays(array $quads): array
    {
        if ($quads === []) {
            throw new \InvalidArgumentException('At least one quad rectangle is required.');
        }
        $minX = PHP_FLOAT_MAX;
        $minY = PHP_FLOAT_MAX;
        $maxX = -PHP_FLOAT_MAX;
        $maxY = -PHP_FLOAT_MAX;
        $quadPoints = [];
        foreach ($quads as $q) {
            [$llx, $lly, $urx, $ury] = $q->toArray();
            $minX = min($minX, $llx);
            $minY = min($minY, $lly);
            $maxX = max($maxX, $urx);
            $maxY = max($maxY, $ury);
            // PDF QuadPoints order: ULx ULy URx URy LLx LLy LRx LRy.
            array_push(
                $quadPoints,
                new PdfNumber($llx),
                new PdfNumber($ury),
                new PdfNumber($urx),
                new PdfNumber($ury),
                new PdfNumber($llx),
                new PdfNumber($lly),
                new PdfNumber($urx),
                new PdfNumber($lly),
            );
        }
        $rectArr = new PdfArray([
            new PdfNumber($minX),
            new PdfNumber($minY),
            new PdfNumber($maxX),
            new PdfNumber($maxY),
        ]);
        return [$rectArr, new PdfArray($quadPoints)];
    }

    /**
     * @param list<array{float,float}> $points
     * @return array{0: PdfArray, 1: PdfArray}
     */
    private function pointsToRectAndArray(array $points): array
    {
        if ($points === []) {
            throw new \InvalidArgumentException('At least one point is required.');
        }
        $minX = PHP_FLOAT_MAX;
        $minY = PHP_FLOAT_MAX;
        $maxX = -PHP_FLOAT_MAX;
        $maxY = -PHP_FLOAT_MAX;
        $flat = [];
        foreach ($points as [$x, $y]) {
            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
            $flat[] = new PdfNumber($x);
            $flat[] = new PdfNumber($y);
        }
        $rectArr = new PdfArray([
            new PdfNumber($minX),
            new PdfNumber($minY),
            new PdfNumber($maxX),
            new PdfNumber($maxY),
        ]);
        return [$rectArr, new PdfArray($flat)];
    }

    // -----------------------------------------------------------------------
    // Navigation: outlines, page labels, named destinations
    // -----------------------------------------------------------------------

    /**
     * Register an Outline root and wire it to the Catalog. Returns the
     * Outline for further configuration (setting First/Last/Count).
     */
    public function setOutline(Outline $outline): Outline
    {
        $this->writer->register($outline);
        $this->writer->getCatalog()->outlines = new PdfReference($outline->objectNumber);
        return $outline;
    }

    /**
     * Register an OutlineItem and return a reference to it. Callers
     * are responsible for linking Prev/Next/First/Last/Parent.
     */
    public function addOutlineItem(OutlineItem $item): PdfReference
    {
        return $this->writer->register($item);
    }

    /**
     * Set a flat page-labels number tree on the Catalog. Pass an
     * associative array of zero-based page index => PageLabel.
     *
     * Example: [0 => $frontMatter, 4 => $mainContent]
     *
     * @param array<int, PageLabel> $labels
     */
    public function setPageLabels(array $labels): self
    {
        $nums = [];
        ksort($labels);
        foreach ($labels as $pageIndex => $label) {
            $this->writer->register($label);
            $nums[] = new PdfNumber($pageIndex);
            $nums[] = new PdfReference($label->objectNumber);
        }

        $tree = new PdfDictionary(['Nums' => new PdfArray($nums)]);
        $treeStream = new PdfStream($tree, '');
        $this->writer->register($treeStream);
        $this->writer->getCatalog()->pageLabels = new PdfReference($treeStream->objectNumber);
        return $this;
    }

    /**
     * Set named destinations on the document. Pass an associative
     * array of name => Destination.
     *
     * @param array<string, Destination> $destinations
     */
    public function setNamedDestinations(array $destinations): self
    {
        ksort($destinations);
        $namesArray = [];
        foreach ($destinations as $name => $dest) {
            $namesArray[] = new PdfString($name);
            $namesArray[] = $dest;
        }

        $nameTree = new NameTree();
        $nameTree->names = new PdfArray($namesArray);
        $this->writer->register($nameTree);

        $namesDict = new PdfDictionary(['Dests' => new PdfReference($nameTree->objectNumber)]);
        $namesDictObj = new PdfStream($namesDict, '');
        $this->writer->register($namesDictObj);
        $this->writer->getCatalog()->names = new PdfReference($namesDictObj->objectNumber);
        return $this;
    }
}
