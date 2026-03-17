<?php

declare(strict_types=1);

namespace Phpdftk\Writer;

use Phpdftk\Content\ContentStream;
use Phpdftk\Content\Resources;
use Phpdftk\Core\PdfArray;
use Phpdftk\Core\PdfDictionary;
use Phpdftk\Core\PdfName;
use Phpdftk\Core\PdfNumber;
use Phpdftk\Core\PdfReference;
use Phpdftk\Core\PdfStream;
use Phpdftk\Core\PdfString;
use Phpdftk\Document\Catalog;
use Phpdftk\Document\Info;
use Phpdftk\Document\Page;
use Phpdftk\Document\PageTree;
use Phpdftk\Font\Font;
use Phpdftk\Geometry\Rectangle;
use Phpdftk\ImageMetadata\ImageParser;

/**
 * Assembles a complete, spec-compliant PDF file.
 *
 * Usage:
 *   $writer = new PdfWriter();
 *   $page   = $writer->addPage(612, 792);
 *   $font   = $writer->addFont(new Type1Font(StandardFont::Helvetica));
 *   $cs     = $writer->addContentStream($page);
 *   $cs->beginText()->setFont('F1', 12)->moveTextPosition(72, 720)->showText('Hi')->endText();
 *   $writer->save('/path/to/output.pdf');
 */
class PdfWriter
{
    public const VERSION = '1.7';

    private ObjectRegistry $registry;
    private Catalog $catalog;
    private PageTree $pageTree;
    private ?Info $info = null;

    /** @var Page[] */
    private array $pages = [];

    /** @var array<string, Font> keyed by resource name (F1, F2, …) */
    private array $fonts = [];

    /** @var array<int, ContentStream> */
    private array $contentStreams = [];

    /** Running counter for font resource names */
    private int $fontCounter = 0;

    /** Running counter for image resource names */
    private int $imageCounter = 0;

    public function __construct()
    {
        $this->registry = new ObjectRegistry();
        $this->catalog  = new Catalog();
        $this->pageTree = new PageTree();

        // Register catalog and page tree immediately so they get their object numbers
        $this->registry->register($this->catalog);
        $this->registry->register($this->pageTree);

        // Wire up catalog -> page tree
        $this->catalog->pages = new PdfReference($this->pageTree->objectNumber);
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    public function getCatalog(): Catalog
    {
        return $this->catalog;
    }

    public function getPageTree(): PageTree
    {
        return $this->pageTree;
    }

    /**
     * Return all registered fonts, keyed by resource name.
     *
     * @return array<string, Font>
     */
    public function getFonts(): array
    {
        return $this->fonts;
    }

    /**
     * Return all content streams added to the document.
     *
     * @return array<int, ContentStream>
     */
    public function getContentStreams(): array
    {
        return $this->contentStreams;
    }

    public function setInfo(Info $info): void
    {
        $this->info = $info;
        $this->registry->register($info);
    }

    /**
     * Add a new page. Accepts either a Rectangle (from phpdftk/geometry) or
     * explicit width/height floats. Default is US Letter (612×792 pt).
     */
    public function addPage(Rectangle|float $widthOrRect = 612, float $height = 792): Page
    {
        if ($widthOrRect instanceof Rectangle) {
            $width  = $widthOrRect->width;
            $height = $widthOrRect->height;
        } else {
            $width = $widthOrRect;
        }

        $page = new Page();
        $page->parent = new PdfReference($this->pageTree->objectNumber);
        $page->mediaBox = new PdfArray([
            new PdfNumber(0),
            new PdfNumber(0),
            new PdfNumber($width),
            new PdfNumber($height),
        ]);
        $page->resources = new Resources();

        $this->registry->register($page);
        $this->pages[] = $page;

        // Update page tree
        $this->pageTree->kids[] = new PdfReference($page->objectNumber);
        $this->pageTree->count  = count($this->pages);

        return $page;
    }

    /**
     * Register a font, auto-assign a resource name (F1, F2, …), and return the name.
     * The font is added to ALL existing pages' resources. For per-page fonts, add
     * directly to page->resources.
     */
    public function addFont(Font $font, ?Page $page = null): string
    {
        $this->fontCounter++;
        $name = 'F' . $this->fontCounter;

        $this->registry->register($font);
        $this->fonts[$name] = $font;
        $ref = new PdfReference($font->objectNumber);

        if ($page !== null) {
            $page->resources?->addFont($name, $ref);
        } else {
            // Add to all existing pages
            foreach ($this->pages as $p) {
                $p->resources?->addFont($name, $ref);
            }
        }

        return $name;
    }

    /**
     * Create a content stream, register it, and attach it to a page.
     */
    public function addContentStream(Page $page): ContentStream
    {
        $cs = new ContentStream();
        $this->registry->register($cs);
        $page->contents[] = new PdfReference($cs->objectNumber);
        $this->contentStreams[] = $cs;
        return $cs;
    }

    /**
     * Add an image to a page as an XObject, using ImageParser to detect format.
     * Returns the resource name (e.g. 'Im1') for use in content streams.
     */
    public function addImage(string $path, Page $page): string
    {
        $info = ImageParser::parse($path);
        $data = file_get_contents($path);

        $this->imageCounter++;
        $name = 'Im' . $this->imageCounter;

        $dict = new PdfDictionary([
            'Type'             => new PdfName('XObject'),
            'Subtype'          => new PdfName('Image'),
            'Width'            => new PdfNumber($info->width),
            'Height'           => new PdfNumber($info->height),
            'ColorSpace'       => new PdfName($info->colorSpace),
            'BitsPerComponent' => new PdfNumber($info->bitsPerComponent),
        ]);

        // For JPEG images, add the DCTDecode filter
        if ($info->format === 'jpeg') {
            $dict->set('Filter', new PdfName('DCTDecode'));
        }

        $xObject = new PdfStream($dict, $data);
        $this->registry->register($xObject);
        $ref = new PdfReference($xObject->objectNumber);

        // Add XObject resource to the page
        if ($page->resources !== null) {
            $page->resources->addXObject($name, $ref);
        }

        return $name;
    }

    /**
     * Register any arbitrary PdfObject (annotations, form fields, etc.).
     */
    public function register(\Phpdftk\Core\PdfObject $object): PdfReference
    {
        $this->registry->register($object);
        return new PdfReference($object->objectNumber);
    }

    /**
     * Generate the complete PDF as a binary string.
     */
    public function generate(): string
    {
        $xref   = new CrossReferenceTable();
        $output = '';

        // PDF header
        $output .= '%PDF-' . self::VERSION . "\n";
        // Binary comment — 4 bytes > 127 to signal binary file
        $output .= "%\xE2\xE3\xCF\xD3\n";

        // Write all objects in registration order
        foreach ($this->registry->getAll() as $objNum => $object) {
            $xref->add($objNum, strlen($output));
            $output .= $object->toIndirectObject() . "\n";
        }

        // Cross-reference table
        $xrefOffset = strlen($output);
        $output .= $xref->build($this->registry->getSize());

        // Trailer
        $size     = $this->registry->getSize();
        $rootRef  = new PdfReference($this->catalog->objectNumber);

        // Generate a deterministic /ID pair (two identical MD5 hashes for new files)
        $id = md5(microtime() . $output, true);

        $trailerDict = new PdfDictionary([
            'Size' => new PdfNumber($size),
            'Root' => $rootRef,
        ]);

        if ($this->info !== null) {
            $trailerDict->set('Info', new PdfReference($this->info->objectNumber));
        }

        $trailerDict->set('ID', new PdfArray([
            new PdfString($id, hex: false),
            new PdfString($id, hex: false),
        ]));

        $output .= "trailer\n" . $trailerDict->toPdf() . "\n";
        $output .= "startxref\n" . $xrefOffset . "\n";
        $output .= '%%EOF';

        return $output;
    }

    /**
     * Write the PDF to a file.
     */
    public function save(string $path): void
    {
        $pdf = $this->generate();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $pdf);
    }
}
