<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Writer;

use ApprLabs\Pdf\Core\Content\ContentStream;
use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Destination;
use ApprLabs\Pdf\Core\Document\Info;
use ApprLabs\Pdf\Core\Document\MetadataStream;
use ApprLabs\Pdf\Core\Document\NameTree;
use ApprLabs\Pdf\Core\Document\Outline;
use ApprLabs\Pdf\Core\Document\OutlineItem;
use ApprLabs\Pdf\Core\Document\Page as CorePage;
use ApprLabs\Pdf\Core\Document\PageLabel;
use ApprLabs\Pdf\Core\Document\PageTree;
use ApprLabs\Pdf\Core\File\PdfFileWriter;
use ApprLabs\Pdf\Core\Font\CIDFontType0Font;
use ApprLabs\Pdf\Core\Font\CIDSystemInfo;
use ApprLabs\Pdf\Core\Font\Font as CoreFont;
use ApprLabs\Pdf\Core\Font\FontDescriptor;
use ApprLabs\Pdf\Core\Font\FontFile\CFFFontFile;
use ApprLabs\Pdf\Core\Font\TrueTypeFont;
use ApprLabs\FontParser\TrueTypeSubsetter;
use ApprLabs\Pdf\Core\Font\Type0Font;
use ApprLabs\Pdf\Core\Font\Type0FontFactory;
use ApprLabs\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use ApprLabs\Pdf\Core\Interactive\Signature\SignatureValue;
use ApprLabs\Pdf\Core\Interactive\Signature\TsaClient;
use ApprLabs\Pdf\Core\Security\PdfEncryptor;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\PdfVersion;
use ApprLabs\Geometry\Rectangle;
use ApprLabs\ImageMetadata\ImageParser;
use ApprLabs\Pdf\Core\Graphics\ColorSpace\ICCBased;

/**
 * Ergonomic PDF document builder.
 *
 * `PdfWriter` is the friendly facade: it owns a {@see PdfFileWriter}
 * under the hood and provides one method per "thing a user wants to
 * put in a document" — pages, fonts, content streams, images,
 * bookmarks, page labels, named destinations, signatures.
 *
 * The byte-level file-assembly logic (header, xref, trailer,
 * signature patching) lives in `PdfFileWriter` in the core package.
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
    private PdfFileWriter $file;
    private Catalog $catalog;
    private PageTree $pageTree;

    /** @var CorePage[] */
    private array $pages = [];

    /** @var array<string, CoreFont|Type0Font> keyed by resource name (F1, F2, …) */
    private array $fonts = [];

    /** @var array<int, ContentStream> */
    private array $contentStreams = [];

    /** Running counter for font resource names */
    private int $fontCounter = 0;

    /** Running counter for image resource names */
    private int $imageCounter = 0;

    public function __construct(bool $compressStreams = true, PdfVersion|string $version = PdfFileWriter::DEFAULT_PDF_VERSION)
    {
        $this->file = new PdfFileWriter($compressStreams, version: $version);
        $this->catalog = new Catalog();
        $this->file->setCatalog($this->catalog);

        $this->pageTree = new PageTree();
        $this->file->register($this->pageTree);

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
     * @return array<string, CoreFont|Type0Font>
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
        $this->file->setInfo($info);
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

        $corePage = new CorePage();
        $corePage->parent = new PdfReference($this->pageTree->objectNumber);
        $corePage->mediaBox = new PdfArray([
            new PdfNumber(0),
            new PdfNumber(0),
            new PdfNumber($width),
            new PdfNumber($height),
        ]);
        $corePage->resources = new Resources();

        $this->file->register($corePage);
        $this->pages[] = $corePage;

        // Update page tree
        $this->pageTree->kids[] = new PdfReference($corePage->objectNumber);
        $this->pageTree->count  = count($this->pages);

        return new Page($corePage, $this);
    }

    /**
     * Register a font, auto-assign a resource name (F1, F2, …), and return the name.
     * The font is added to ALL existing pages' resources. For per-page fonts, add
     * directly to page->resources.
     */
    public function addFont(CoreFont $font, CorePage|Page|null $page = null): Font
    {
        $this->fontCounter++;
        $name = 'F' . $this->fontCounter;

        $parsedData = null;
        if ($font instanceof TrueTypeFont && $font->parsedFontData !== null) {
            $this->embedTrueTypeFont($font);
            $parsedData = $font->parsedFontData;
        }

        $this->file->register($font);
        $this->fonts[$name] = $font;
        $ref = new PdfReference($font->objectNumber);

        $corePage = $page instanceof Page ? $page->corePage() : $page;
        if ($corePage !== null) {
            $corePage->resources?->addFont($name, $ref);
        } else {
            // Add to all existing pages
            foreach ($this->pages as $p) {
                $p->resources?->addFont($name, $ref);
            }
        }

        $family = $font->baseFont !== null ? $font->baseFont->value : 'Unknown';

        return new Font($name, $family, $parsedData);
    }

    /**
     * Build and register a Type 0 composite font from TrueType font data.
     *
     * Creates the full CID font stack: Type0Font -> CIDFontType2 -> FontDescriptor -> FontFile2,
     * plus a ToUnicode CMap. The font is subset to include only the glyphs needed for the
     * given codepoints.
     *
     * @param \ApprLabs\FontParser\TrueTypeData $data      Parsed TrueType font data
     * @param int[]                              $usedCodepoints Unicode codepoints used in the document
     * @param CorePage|Page|null                 $page      If set, add font only to this page
     * @return Font Opaque font handle
     */
    public function addCompositeFont(\ApprLabs\FontParser\TrueTypeData $data, array $usedCodepoints, CorePage|Page|null $page = null): Font
    {
        $this->fontCounter++;
        $name = 'F' . $this->fontCounter;

        [$type0Font, $additionalObjects, $fontStream, $descriptor, $cidFont, $toUnicodeStream] =
            Type0FontFactory::fromTrueTypeData($data, $usedCodepoints);

        // Register all objects
        $this->file->register($fontStream);
        $descriptor->fontFile2 = new PdfReference($fontStream->objectNumber);

        $this->file->register($descriptor);
        $cidFont->fontDescriptor = new PdfReference($descriptor->objectNumber);

        $this->file->register($cidFont);
        $type0Font->descendantFonts = new PdfArray([new PdfReference($cidFont->objectNumber)]);

        $this->file->register($toUnicodeStream);
        $type0Font->toUnicode = new PdfReference($toUnicodeStream->objectNumber);

        $this->file->register($type0Font);
        $this->fonts[$name] = $type0Font;
        $ref = new PdfReference($type0Font->objectNumber);

        $corePage = $page instanceof Page ? $page->corePage() : $page;
        if ($corePage !== null) {
            $corePage->resources?->addFont($name, $ref);
        } else {
            foreach ($this->pages as $p) {
                $p->resources?->addFont($name, $ref);
            }
        }

        return new Font($name, $data->postScriptName, $data);
    }

    /**
     * Build and register an OpenType CFF composite font.
     *
     * Creates the Type 0 → CIDFontType0 → FontDescriptor → CFFFontFile
     * stack with a ToUnicode CMap for text extraction.
     *
     * @param \ApprLabs\FontParser\OpenTypeData $data Parsed OpenType font data
     * @param int[] $usedCodepoints Unicode codepoints used in the document
     * @param CorePage|Page|null $page If set, add font only to this page
     * @return Font Opaque font handle
     */
    public function addOpenTypeFont(
        \ApprLabs\FontParser\OpenTypeData $data,
        array $usedCodepoints,
        CorePage|Page|null $page = null,
    ): Font {
        $this->fontCounter++;
        $name = 'F' . $this->fontCounter;

        // Font descriptor
        $descriptor = new FontDescriptor(new PdfName($data->postScriptName));
        $descriptor->flags = $data->flags;
        $descriptor->fontBBox = new PdfArray([
            new PdfNumber($data->fontBBox[0]),
            new PdfNumber($data->fontBBox[1]),
            new PdfNumber($data->fontBBox[2]),
            new PdfNumber($data->fontBBox[3]),
        ]);
        $descriptor->italicAngle = $data->italicAngle;
        $descriptor->ascent = $data->ascent;
        $descriptor->descent = $data->descent;
        $descriptor->capHeight = $data->capHeight;
        $descriptor->xHeight = $data->xHeight;
        $descriptor->stemV = $data->stemV;

        // Subset CFF table to only include used glyphs
        $usedGids = [];
        foreach ($usedCodepoints as $cp) {
            $gid = $data->fullUnicodeToGid[$cp] ?? null;
            if ($gid !== null) {
                $usedGids[] = $gid;
            }
        }
        $cffBytes = (new \ApprLabs\FontParser\CffSubsetter())->subset($data->cffBytes, $usedGids);

        // CFF font program stream (embed subsetted CFF table bytes)
        $cffStream = new CFFFontFile($cffBytes, 'CIDFontType0C');
        $this->file->register($cffStream);
        $descriptor->fontFile3 = new PdfReference($cffStream->objectNumber);
        $this->file->register($descriptor);

        // CID font
        $cidSystemInfo = new CIDSystemInfo('Adobe', 'Identity', 0);
        $cidFont = new CIDFontType0Font($data->postScriptName, $cidSystemInfo);
        $cidFont->fontDescriptor = new PdfReference($descriptor->objectNumber);

        // Build /W widths array
        $scale = fn(int $v): int => (int) round($v * 1000 / $data->unitsPerEm);
        $wEntries = [];
        foreach ($usedCodepoints as $cp) {
            $gid = $data->fullUnicodeToGid[$cp] ?? null;
            if ($gid !== null && isset($data->glyphWidths[$gid])) {
                $wEntries[$gid] = new PdfNumber($scale($data->glyphWidths[$gid]));
            }
        }
        if (!empty($wEntries)) {
            ksort($wEntries);
            $wArray = [];
            $currentRun = [];
            $currentStart = -1;
            $lastGid = -2;
            foreach ($wEntries as $gid => $width) {
                if ($gid !== $lastGid + 1) {
                    if (!empty($currentRun)) {
                        $wArray[] = new PdfNumber($currentStart);
                        $wArray[] = new PdfArray($currentRun);
                    }
                    $currentStart = $gid;
                    $currentRun = [$width];
                } else {
                    $currentRun[] = $width;
                }
                $lastGid = $gid;
            }
            $wArray[] = new PdfNumber($currentStart);
            $wArray[] = new PdfArray($currentRun);
            $cidFont->w = new PdfArray($wArray);
        }

        $this->file->register($cidFont);

        // ToUnicode CMap
        $gidToUnicode = [];
        foreach ($usedCodepoints as $cp) {
            $gid = $data->fullUnicodeToGid[$cp] ?? null;
            if ($gid !== null) {
                $gidToUnicode[$gid] = $cp;
            }
        }
        ksort($gidToUnicode);
        $cmapEntries = [];
        foreach ($gidToUnicode as $gid => $unicode) {
            $cmapEntries[] = sprintf('<%04X> <%04X>', $gid, $unicode);
        }
        $cmapChunks = array_chunk($cmapEntries, 100);
        $cmapBlocks = '';
        foreach ($cmapChunks as $chunk) {
            $cmapBlocks .= count($chunk) . " beginbfchar\n"
                . implode("\n", $chunk) . "\n"
                . "endbfchar\n";
        }
        $cmapProgram = "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\n"
            . "begincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /Adobe-Identity-UCS def\n"
            . "/CMapType 2 def\n"
            . "1 begincodespacerange\n"
            . "<0000> <FFFF>\n"
            . "endcodespacerange\n"
            . $cmapBlocks
            . "endcmap\n"
            . "CMap end\n"
            . "end";
        $toUnicodeStream = new PdfStream(new PdfDictionary(), $cmapProgram);
        $this->file->register($toUnicodeStream);

        // Type 0 font
        $type0Font = new Type0Font(
            $data->postScriptName,
            new PdfArray([new PdfReference($cidFont->objectNumber)]),
            'Identity-H',
        );
        $type0Font->toUnicode = new PdfReference($toUnicodeStream->objectNumber);
        $this->file->register($type0Font);

        $this->fonts[$name] = $type0Font;
        $ref = new PdfReference($type0Font->objectNumber);

        $corePage = $page instanceof Page ? $page->corePage() : $page;
        if ($corePage !== null) {
            $corePage->resources?->addFont($name, $ref);
        } else {
            foreach ($this->pages as $p) {
                $p->resources?->addFont($name, $ref);
            }
        }

        return new Font($name, $data->postScriptName, $data);
    }

    /**
     * Create a content stream, register it, and attach it to a page.
     */
    public function addContentStream(CorePage|Page $page): ContentStream
    {
        $corePage = $page instanceof Page ? $page->corePage() : $page;
        $cs = new ContentStream();
        $this->file->register($cs);
        $corePage->contents[] = new PdfReference($cs->objectNumber);
        $this->contentStreams[] = $cs;
        return $cs;
    }

    /**
     * Add an image to a page as an XObject, using ImageParser to detect format.
     * Returns the resource name (e.g. 'Im1') for use in content streams.
     */
    public function addImage(string $path, CorePage|Page $page): string
    {
        $corePage = $page instanceof Page ? $page->corePage() : $page;
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

        // Set the appropriate decode filter for pass-through image formats
        match ($info->format) {
            'jpeg' => $dict->set('Filter', new PdfName('DCTDecode')),
            'jpeg2000' => $dict->set('Filter', new PdfName('JPXDecode')),
            'jbig2' => $dict->set('Filter', new PdfName('JBIG2Decode')),
            default => null,
        };

        // If the image has an embedded ICC profile, replace the color space
        // with an ICCBased color space reference
        if ($info->iccProfile !== null) {
            $nComponents = match ($info->colorSpace) {
                'DeviceGray' => 1,
                'DeviceCMYK' => 4,
                default => 3, // DeviceRGB
            };
            $profileDict = new PdfDictionary([
                'N' => new PdfNumber($nComponents),
            ]);
            $profileStream = new PdfStream($profileDict, $info->iccProfile);
            $this->file->register($profileStream);
            $profileRef = new PdfReference($profileStream->objectNumber);
            $iccColorSpace = new ICCBased($profileRef);
            $dict->set('ColorSpace', $iccColorSpace);
        }

        $xObject = new PdfStream($dict, $data);
        $this->file->register($xObject);
        $ref = new PdfReference($xObject->objectNumber);

        // Add XObject resource to the page
        if ($corePage->resources !== null) {
            $corePage->resources->addXObject($name, $ref);
        }

        return $name;
    }

    /**
     * Register an Outline root and wire it to the Catalog.
     * Returns the Outline for further configuration (setting First/Last/Count).
     */
    public function setOutline(Outline $outline): Outline
    {
        $this->file->register($outline);
        $this->catalog->outlines = new PdfReference($outline->objectNumber);
        return $outline;
    }

    /**
     * Register an OutlineItem and return a reference to it.
     * Callers are responsible for linking Prev/Next/First/Last/Parent.
     */
    public function addOutlineItem(OutlineItem $item): PdfReference
    {
        $this->file->register($item);
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
            $this->file->register($label);
            $nums[] = new PdfNumber($pageIndex);
            $nums[] = new PdfReference($label->objectNumber);
        }

        // Inline number tree (leaf node only — sufficient for most documents)
        $tree = new PdfDictionary(['Nums' => new PdfArray($nums)]);
        $treeStream = new PdfStream($tree, '');
        $this->file->register($treeStream);
        $this->catalog->pageLabels = new PdfReference($treeStream->objectNumber);
    }

    /**
     * Set named destinations on the document.
     * Pass an associative array of name => Destination.
     *
     * @param array<string, Destination> $destinations
     */
    public function setNamedDestinations(array $destinations): void
    {
        ksort($destinations);
        $namesArray = [];
        foreach ($destinations as $name => $dest) {
            $namesArray[] = new PdfString($name);
            $namesArray[] = $dest;
        }

        $nameTree = new NameTree();
        $nameTree->names = new PdfArray($namesArray);
        $this->file->register($nameTree);

        // Build a names dictionary with /Dests pointing to the name tree
        $namesDict = new PdfDictionary(['Dests' => new PdfReference($nameTree->objectNumber)]);
        $namesDictObj = new PdfStream($namesDict, '');
        $this->file->register($namesDictObj);
        $this->catalog->names = new PdfReference($namesDictObj->objectNumber);
    }

    /**
     * Escape hatch to Level 0 — returns the underlying PdfFileWriter
     * for direct object-model control.
     */
    public function fileWriter(): PdfFileWriter
    {
        return $this->file;
    }

    /**
     * Register any arbitrary PdfObject (annotations, form fields, etc.).
     */
    public function register(PdfObject $object): PdfReference
    {
        return $this->file->register($object);
    }

    /**
     * Add an image to a page as an XObject (internal — used by Writer\Page).
     *
     * @internal
     * @return string Resource name (e.g. 'Im1')
     */
    public function addImageInternal(string $path, CorePage $page): string
    {
        return $this->addImage($path, $page);
    }

    /**
     * Configure a signer for this document — see
     * {@see PdfFileWriter::setSigner()} for the full pipeline description.
     */
    public function setSigner(
        SignatureValue $signatureValue,
        Pkcs7Signer $signer,
        int $placeholderBytes = 8192
    ): void {
        $this->file->setSigner($signatureValue, $signer, $placeholderBytes);
    }

    /**
     * Configure a TSA client for RFC 3161 timestamping.
     *
     * @see PdfFileWriter::setTsaClient()
     */
    public function setTsaClient(TsaClient $tsaClient): void
    {
        $this->file->setTsaClient($tsaClient);
    }

    /**
     * Configure a document-level timestamp using a TSA client.
     *
     * @see PdfFileWriter::setTimestamper()
     */
    public function setTimestamper(
        SignatureValue $docTimeStamp,
        TsaClient $tsaClient,
        int $placeholderBytes = 16384
    ): void {
        $this->file->setTimestamper($docTimeStamp, $tsaClient, $placeholderBytes);
    }

    /**
     * Configure encryption for the generated PDF.
     *
     * Registers the encrypt dictionary, and during generation all
     * strings and streams are encrypted per-object with the correct
     * key derivation. The /Encrypt reference is added to the trailer
     * automatically.
     *
     * @see PdfEncryptor::aes128()
     * @see PdfEncryptor::aes256()
     * @see PdfEncryptor::rc4128()
     */
    public function setEncryption(PdfEncryptor $encryptor): void
    {
        $this->file->setEncryption($encryptor);
    }

    public function getPdfVersion(): PdfVersion
    {
        return $this->file->getPdfVersion();
    }

    public function setStrictVersionMode(bool $strict = true): void
    {
        $this->file->setStrictVersionMode($strict);
    }

    public function setCeilingVersion(?PdfVersion $ceiling): void
    {
        $this->file->setCeilingVersion($ceiling);
    }

    public function setDeprecationHandler(\Closure $handler): void
    {
        $this->file->setDeprecationHandler($handler);
    }

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->file->getVersionWarnings();
    }

    /**
     * Generate the complete PDF as a binary string.
     */
    public function generate(): string
    {
        return $this->file->generate();
    }

    /**
     * Alias for {@see generate()} — returns the raw PDF bytes as a string.
     */
    public function toBytes(): string
    {
        return $this->file->toBytes();
    }

    /**
     * Write the generated PDF to an open stream resource.
     *
     * @param resource $stream
     */
    public function writeTo($stream): int
    {
        return $this->file->writeTo($stream);
    }

    /**
     * Write the PDF to a file, creating parent directories as needed.
     */
    public function save(string $path): void
    {
        $this->file->save($path);
    }

    /**
     * Attach an XMP metadata stream to the document catalog.
     *
     * @param string $xmpXml The raw XMP XML bytes (typically from XmpWriter::serialize())
     */
    public function setMetadata(string $xmpXml): void
    {
        $metadataStream = new MetadataStream($xmpXml);
        $this->file->register($metadataStream);
        $this->catalog->metadata = new PdfReference($metadataStream->objectNumber);
    }

    /**
     * Build and attach XMP metadata from the document's Info dictionary.
     *
     * Syncs Title, Author, Subject, Creator, Producer from the Info dict
     * into XMP properties (dc:title, dc:creator, dc:description,
     * xmp:CreatorTool, pdf:Producer) and attaches the result as a
     * MetadataStream on the Catalog.
     *
     * Requires the Info dict to be set via {@see setInfo()} first.
     */
    public function syncInfoToMetadata(): void
    {
        $info = $this->file->getInfo();
        if ($info === null) {
            return;
        }

        $packet = \ApprLabs\Xmp\XmpPacket::create();
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

        $xmpXml = (new \ApprLabs\Xmp\XmpWriter())->serialize($packet);
        $this->setMetadata($xmpXml);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function embedTrueTypeFont(TrueTypeFont $font): void
    {
        $data = $font->parsedFontData;

        // 1. Font program stream — subset if possible
        $fontBytes = $data->fontBytes;
        if (!empty($data->fullUnicodeToGid)) {
            // Subset to only WinAnsi-mapped glyphs
            $glyphIds = [];
            foreach ($data->unicodeMap as $unicode) {
                $gid = $data->fullUnicodeToGid[$unicode] ?? null;
                if ($gid !== null) {
                    $glyphIds[] = $gid;
                }
            }
            if (!empty($glyphIds)) {
                $fontBytes = (new TrueTypeSubsetter())->subset($fontBytes, $glyphIds, $data->fullUnicodeToGid);
            }
        }
        $streamDict = new PdfDictionary(['Length1' => new PdfNumber(strlen($fontBytes))]);
        $fontStream = new PdfStream($streamDict, $fontBytes);
        $this->file->register($fontStream);

        // 2. FontDescriptor
        $descriptor = new FontDescriptor(new PdfName($data->postScriptName));
        $descriptor->flags = $data->flags;
        $descriptor->fontBBox = new PdfArray([
            new PdfNumber($data->fontBBox[0]),
            new PdfNumber($data->fontBBox[1]),
            new PdfNumber($data->fontBBox[2]),
            new PdfNumber($data->fontBBox[3]),
        ]);
        $descriptor->italicAngle = $data->italicAngle;
        $descriptor->ascent      = $data->ascent;
        $descriptor->descent     = $data->descent;
        $descriptor->capHeight   = $data->capHeight;
        $descriptor->xHeight     = $data->xHeight;
        $descriptor->stemV       = $data->stemV;
        $descriptor->fontFile2   = new PdfReference($fontStream->objectNumber);
        $this->file->register($descriptor);

        // 3. ToUnicode CMap stream
        $cmapStream = new PdfStream(new PdfDictionary(), $this->buildToUnicodeCMap($data->unicodeMap));
        $this->file->register($cmapStream);

        // 4. Wire back to font
        $font->fontDescriptor = new PdfReference($descriptor->objectNumber);
        $font->toUnicode      = new PdfReference($cmapStream->objectNumber);
    }

    /** @param array<int, int> $unicodeMap */
    private function buildToUnicodeCMap(array $unicodeMap): string
    {
        ksort($unicodeMap);
        $entries = [];
        foreach ($unicodeMap as $byte => $unicode) {
            $entries[] = sprintf('<%02X> <%04X>', $byte, $unicode);
        }

        // PDF spec: max 100 entries per beginbfchar block
        $chunks = array_chunk($entries, 100);
        $blocks = '';
        foreach ($chunks as $chunk) {
            $blocks .= count($chunk) . " beginbfchar\n"
                     . implode("\n", $chunk) . "\n"
                     . "endbfchar\n";
        }

        return "/CIDInit /ProcSet findresource begin\n"
             . "12 dict begin\n"
             . "begincmap\n"
             . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
             . "/CMapName /Adobe-Identity-UCS def\n"
             . "/CMapType 2 def\n"
             . "1 begincodespacerange\n"
             . "<20> <FF>\n"
             . "endcodespacerange\n"
             . $blocks
             . "endcmap\n"
             . "CMap end\n"
             . "end";
    }
}
