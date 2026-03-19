<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer;

use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Document\Outline;
use ApprLabs\Pdf\Core\Document\OutlineItem;
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Core\Document\PageLabel;
use ApprLabs\Pdf\Core\Document\PageTree;
use ApprLabs\Pdf\Core\Font\Font;
use ApprLabs\Geometry\Rectangle;
use ApprLabs\ImageMetadata\ImageParser;

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
     * Register an Outline root and wire it to the Catalog.
     * Returns the Outline for further configuration (setting First/Last/Count).
     */
    public function setOutline(Outline $outline): Outline
    {
        $this->registry->register($outline);
        $this->catalog->outlines = new PdfReference($outline->objectNumber);
        return $outline;
    }

    /**
     * Register an OutlineItem and return a reference to it.
     * Callers are responsible for linking Prev/Next/First/Last/Parent.
     */
    public function addOutlineItem(OutlineItem $item): PdfReference
    {
        $this->registry->register($item);
        return new PdfReference($item->objectNumber);
    }

    /**
     * Set a simple flat page-labels number tree on the Catalog.
     * Pass an associative array of zero-based page index => PageLabel.
     *
     * Example: [0 => $frontMatter, 4 => $mainContent]
     *
     * @param array<int, PageLabel> $labels
     */
    public function setPageLabels(array $labels): void
    {
        // Build an inline Nums array: [pageIdx1 labelDict1 pageIdx2 labelDict2 ...]
        $nums = [];
        ksort($labels);
        foreach ($labels as $pageIndex => $label) {
            $this->registry->register($label);
            $nums[] = new PdfNumber($pageIndex);
            $nums[] = new PdfReference($label->objectNumber);
        }

        // Inline number tree (leaf node only — sufficient for most documents)
        $tree = new PdfDictionary(['Nums' => new PdfArray($nums)]);
        $treeStream = new PdfStream($tree, '');
        $this->registry->register($treeStream);
        $this->catalog->pageLabels = new PdfReference($treeStream->objectNumber);
    }

    /**
     * Register any arbitrary PdfObject (annotations, form fields, etc.).
     */
    public function register(\ApprLabs\Pdf\Core\PdfObject $object): PdfReference
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
        $chunks = [];

        // PDF header
        $chunks[] = '%PDF-' . self::VERSION . "\n";
        // Binary comment — 4 bytes > 127 to signal binary file
        $chunks[] = "%\xE2\xE3\xCF\xD3\n";

        // Track byte offset without concatenating the full string each iteration
        $offset = strlen($chunks[0]) + strlen($chunks[1]);

        // Write all objects in registration order
        foreach ($this->registry->getAll() as $objNum => $object) {
            $xref->add($objNum, $offset);
            $chunk = $object->toIndirectObject() . "\n";
            $chunks[] = $chunk;
            $offset += strlen($chunk);
        }

        // Cross-reference table
        $xrefOffset = $offset;
        $xrefChunk  = $xref->build($this->registry->getSize());
        $chunks[]   = $xrefChunk;

        // Trailer
        $size    = $this->registry->getSize();
        $rootRef = new PdfReference($this->catalog->objectNumber);

        // Generate file ID from header + xref offset (avoids hashing entire output)
        $id = md5(microtime() . $xrefOffset, true);

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

        $chunks[] = "trailer\n" . $trailerDict->toPdf() . "\n";
        $chunks[] = "startxref\n" . $xrefOffset . "\n";
        $chunks[] = '%%EOF';

        return implode('', $chunks);
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
