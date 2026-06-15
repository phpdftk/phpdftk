<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Writer;

use Phpdftk\Pdf\Core\Content\ContentStream;
use Phpdftk\Pdf\Core\Content\Resources;
use Phpdftk\Pdf\Core\Document\Catalog;
use Phpdftk\Pdf\Core\Document\Destination;
use Phpdftk\Pdf\Core\Document\Info;
use Phpdftk\Pdf\Core\Document\Outline;
use Phpdftk\Pdf\Core\Document\OutlineItem;
use Phpdftk\Pdf\Core\Document\Page as CorePage;
use Phpdftk\Pdf\Core\Document\PageLabel;
use Phpdftk\Pdf\Core\Document\PageTree;
use Phpdftk\Filesystem\LocalFilesystem;
use Phpdftk\Pdf\Core\File\PdfFileWriter;
use Phpdftk\Pdf\Core\Font\CIDFontType0Font;
use Phpdftk\Pdf\Core\Font\CIDSystemInfo;
use Phpdftk\Pdf\Core\Font\Font as CoreFont;
use Phpdftk\Pdf\Core\Font\FontDescriptor;
use Phpdftk\Pdf\Core\Font\FontFile\CFFFontFile;
use Phpdftk\Pdf\Core\Font\FontFile\Type1FontFile;
use Phpdftk\Pdf\Core\Font\TrueTypeFont;
use Phpdftk\Pdf\Core\Font\Type1Font;
use Phpdftk\FontParser\TrueTypeSubsetter;
use Phpdftk\Encoding\TextEncoder;
use Phpdftk\Encoding\WinAnsiEncoder;
use Phpdftk\Pdf\Core\Font\Type0Font;
use Phpdftk\Pdf\Core\Font\Type0FontFactory;
use Phpdftk\Pdf\Core\Interactive\Signature\Pkcs7Signer;
use Phpdftk\Pdf\Core\Interactive\Signature\SignatureValue;
use Phpdftk\Pdf\Core\Interactive\Signature\TsaClient;
use Phpdftk\Pdf\Conformance\ConformanceException;
use Phpdftk\Pdf\Conformance\ConformanceMode;
use Phpdftk\Pdf\Conformance\Inspection\WriterDocumentInspector;
use Phpdftk\Pdf\Conformance\Metadata\ConformanceXmpWriter;
use Phpdftk\Pdf\Conformance\Profile\ConformanceProfile;
use Phpdftk\Pdf\Conformance\Result\ConformanceResult;
use Phpdftk\Pdf\Conformance\Validator\ConformanceValidator;
use Phpdftk\Pdf\Core\Security\PdfEncryptor;
use Phpdftk\Pdf\Core\PdfArray;
use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfObject;
use Phpdftk\Pdf\Core\PdfReference;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Geometry\Rectangle;
use Phpdftk\ImageMetadata\ImageParser;
use Phpdftk\Pdf\Core\Graphics\ColorSpace\ICCBased;

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
 *
 * @api
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

    /**
     * @var array<string, TextEncoder> resource name → encoder.
     * Tracked alongside $fonts so getEncodingWarnings() can collect missing
     * codepoints across the whole document without holding onto Font handles.
     */
    private array $fontEncoders = [];

    /** @var array<int, ContentStream> */
    private array $contentStreams = [];

    /** Running counter for font resource names */
    private int $fontCounter = 0;

    /** Running counter for image resource names */
    private int $imageCounter = 0;

    /** Whether to produce linearized (web-optimized) output */
    private bool $linearized = false;

    /** Active conformance mode, if any. */
    private ?ConformanceMode $conformanceMode = null;

    /** @var list<ConformanceResult> */
    private array $conformanceResults = [];

    /**
     * Lazily-cached PdfDoc view of this writer, used by the deprecated
     * forwarding stubs below.
     */
    private ?PdfDoc $cachedDoc = null;

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

    /**
     * @deprecated Use {@see PdfDoc::setInfo()} instead. This forwarder is
     *             retained for one minor release and will be removed.
     */
    public function setInfo(Info $info): void
    {
        $this->doc()->setInfo($info);
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
        } elseif ($font instanceof Type1Font && $font->parsedFontData !== null) {
            $this->embedType1Font($font);
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

        $encoder = $this->buildEncoderFor($font);
        if ($encoder !== null) {
            $this->fontEncoders[$name] = $encoder;
        }

        return new Font($name, $family, $parsedData, $encoder);
    }

    /**
     * Pick the right text encoder for a font being registered. WinAnsi for
     * Latin-script Type1 standard fonts and any TrueType font (the writer
     * embeds those with /Encoding /WinAnsiEncoding); null for everything
     * else. Composite/CID fonts go through a separate registration path
     * (addCompositeFont), so they never reach this method.
     */
    private function buildEncoderFor(CoreFont $font): ?TextEncoder
    {
        if ($font instanceof TrueTypeFont) {
            return new WinAnsiEncoder();
        }
        if ($font instanceof Type1Font) {
            $base = $font->baseFont?->value;
            if ($base === 'Symbol' || $base === 'ZapfDingbats') {
                return null;
            }
            return new WinAnsiEncoder();
        }
        return null;
    }

    /**
     * Build and register a Type 0 composite font from TrueType font data.
     *
     * Creates the full CID font stack: Type0Font -> CIDFontType2 -> FontDescriptor -> FontFile2,
     * plus a ToUnicode CMap. The font is subset to include only the glyphs needed for the
     * given codepoints.
     *
     * @param \Phpdftk\FontParser\TrueTypeData $data      Parsed TrueType font data
     * @param int[]                              $usedCodepoints Unicode codepoints used in the document
     * @param CorePage|Page|null                 $page      If set, add font only to this page
     * @return Font Opaque font handle
     */
    public function addCompositeFont(\Phpdftk\FontParser\TrueTypeData $data, array $usedCodepoints, CorePage|Page|null $page = null): Font
    {
        $this->fontCounter++;
        $name = 'F' . $this->fontCounter;

        [$type0Font, $additionalObjects, $fontStream, $descriptor, $cidFont, $toUnicodeStream, $unicodeToGid, $oldToNewGid] =
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

        return new Font($name, $data->postScriptName, $data, unicodeToGid: $unicodeToGid, oldToNewGid: $oldToNewGid);
    }

    /**
     * Build and register an OpenType CFF composite font.
     *
     * Creates the Type 0 → CIDFontType0 → FontDescriptor → CFFFontFile
     * stack with a ToUnicode CMap for text extraction.
     *
     * @param \Phpdftk\FontParser\OpenTypeData $data Parsed OpenType font data
     * @param int[] $usedCodepoints Unicode codepoints used in the document
     * @param CorePage|Page|null $page If set, add font only to this page
     * @param int[] $extraGids Extra pre-subset GIDs to retain even when no
     *        codepoint maps to them. Math fonts use this to keep stretchy
     *        variants and assembly parts in the subset - those glyphs have
     *        no Unicode codepoint and would otherwise be dropped by the
     *        CFF subsetter.
     * @return Font Opaque font handle
     */
    public function addOpenTypeFont(
        \Phpdftk\FontParser\OpenTypeData $data,
        array $usedCodepoints,
        CorePage|Page|null $page = null,
        array $extraGids = [],
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

        // Subset CFF table to only include used glyphs.
        $usedGids = [];
        $codepointsByOldGid = [];
        foreach ($usedCodepoints as $cp) {
            $gid = $data->fullUnicodeToGid[$cp] ?? null;
            if ($gid !== null) {
                $usedGids[] = $gid;
                $codepointsByOldGid[$gid] = $cp;
            }
        }
        // Add codepoint-less glyphs (math variants, assembly parts).
        // Dedupe via array_values + array_unique so the subsetter sees
        // each GID once even when callers double-register.
        foreach ($extraGids as $gid) {
            $usedGids[] = $gid;
        }
        $usedGids = array_values(array_unique($usedGids));
        $cffSubsetter = new \Phpdftk\FontParser\CffSubsetter();
        $cffBytes = $cffSubsetter->subset($data->cffBytes, $usedGids);
        $cffGidMap = $cffSubsetter->getGidMap();

        // Post-subset Unicode → new GID map. Drives the /W array, the
        // ToUnicode CMap, and the Font handle accessor below.
        $unicodeToGidSubset = [];
        foreach ($codepointsByOldGid as $oldGid => $cp) {
            $newGid = $cffGidMap[$oldGid] ?? null;
            if ($newGid !== null) {
                $unicodeToGidSubset[$cp] = $newGid;
            }
        }

        // CFF font program stream (embed subsetted CFF table bytes)
        $cffStream = new CFFFontFile($cffBytes, 'CIDFontType0C');
        $this->file->register($cffStream);
        $descriptor->fontFile3 = new PdfReference($cffStream->objectNumber);
        $this->file->register($descriptor);

        // CID font
        $cidSystemInfo = new CIDSystemInfo('Adobe', 'Identity', 0);
        $cidFont = new CIDFontType0Font($data->postScriptName, $cidSystemInfo);
        $cidFont->fontDescriptor = new PdfReference($descriptor->objectNumber);

        // Build /W widths array, indexed by post-subset CID/GID.
        $scale = fn(int $v): int => (int) round($v * 1000 / $data->unitsPerEm);
        $wEntries = [];
        foreach ($codepointsByOldGid as $oldGid => $cp) {
            $newGid = $cffGidMap[$oldGid] ?? null;
            if ($newGid !== null && isset($data->glyphWidths[$oldGid])) {
                $wEntries[$newGid] = new PdfNumber($scale($data->glyphWidths[$oldGid]));
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

        // ToUnicode CMap — keyed by post-subset GID so text extraction
        // sees the same identifiers the viewer renders against.
        $gidToUnicode = [];
        foreach ($unicodeToGidSubset as $cp => $newGid) {
            $gidToUnicode[$newGid] = $cp;
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

        return new Font(
            $name,
            $data->postScriptName,
            $data,
            unicodeToGid: $unicodeToGidSubset,
            oldToNewGid: $cffGidMap,
        );
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
        // SVG is not a raster format. The image-metadata parser
        // recognises it (so callers can read intrinsic dimensions
        // for layout), but PDF can't reference SVG bytes as an
        // Image XObject. Reject here so the caller (typically the
        // HTML painter) catches and routes through the SVG painter
        // instead. Matches the pre-SVG-parser behaviour where
        // ImageParser threw on unknown bytes.
        if ($info->format === 'svg') {
            throw new \RuntimeException('SVG cannot be embedded as a raster image XObject; route through the SVG painter');
        }
        $data = LocalFilesystem::readFile($path);

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

        // PNG: extract the DEFLATE-compressed IDAT data and tell the
        // PDF reader to FlateDecode it with the PNG-style predictor
        // (15 = "PNG up", which PNG IDAT effectively uses). PDF
        // doesn't natively grok the PNG container, so we strip the
        // PNG wrapper but keep the compressed pixel data verbatim —
        // no intermediate raw-RGB buffer needed.
        //
        // Skipped for color type 3 (indexed — needs PLTE chunk)
        // and bit depths != 8 (rarely used in WPT). Those fall
        // back to the raw-embed path the historical writer used;
        // rendering is broken but the XObject still exists for
        // refcount / metadata.
        $smaskRef = null;
        if ($info->format === 'png' && $info->bitsPerComponent === 8) {
            $components = match ($info->colorSpace) {
                'DeviceGray' => 1,
                'DeviceCMYK' => 4,
                default => 3, // DeviceRGB
            };
            // Detect indexed (color type 3) — the parser maps its
            // colourspace to DeviceRGB for metadata purposes but the
            // IDAT data is palette indices, not RGB samples. Route
            // through the indexed decoder so the palette lookup
            // happens here instead of being misinterpreted as raw
            // pixel data.
            $pngColorType = strlen($data) >= 8 + 8 + 10
                && substr($data, 12, 4) === 'IHDR'
                ? ord($data[8 + 8 + 9])
                : null;
            if ($pngColorType === 3) {
                $decoded = \Phpdftk\ImageMetadata\PngParser::decodeIndexedPng($data);
                if ($decoded !== null) {
                    $colourCompressed = @gzcompress($decoded['colour']);
                    if ($colourCompressed !== false) {
                        $dict->set('Filter', new PdfName('FlateDecode'));
                        $dict->set('ColorSpace', new PdfName('DeviceRGB'));
                        $data = $colourCompressed;
                        if ($decoded['alpha'] !== null) {
                            $alphaCompressed = @gzcompress($decoded['alpha']);
                            if ($alphaCompressed !== false) {
                                $smaskDict = new PdfDictionary([
                                    'Type' => new PdfName('XObject'),
                                    'Subtype' => new PdfName('Image'),
                                    'Width' => new PdfNumber($decoded['width']),
                                    'Height' => new PdfNumber($decoded['height']),
                                    'ColorSpace' => new PdfName('DeviceGray'),
                                    'BitsPerComponent' => new PdfNumber(8),
                                    'Filter' => new PdfName('FlateDecode'),
                                ]);
                                $smaskStream = new PdfStream($smaskDict, $alphaCompressed);
                                $this->file->register($smaskStream);
                                $smaskRef = new PdfReference($smaskStream->objectNumber);
                            }
                        }
                    }
                }
            } elseif ($info->hasAlpha) {
                // Color types 4 / 6 — decode pixel data fully, split
                // into colour + alpha, emit alpha as SMask. Skips
                // gzcompress on the alpha stream when zlib refuses
                // (very-tiny images can produce empty streams) and
                // falls back to a raw embed in that case.
                $decoded = \Phpdftk\ImageMetadata\PngParser::decodeAlphaPng($data);
                if ($decoded !== null) {
                    $colourCompressed = @gzcompress($decoded['colour']);
                    $alphaCompressed = @gzcompress($decoded['alpha']);
                    if ($colourCompressed !== false && $alphaCompressed !== false) {
                        $smaskDict = new PdfDictionary([
                            'Type' => new PdfName('XObject'),
                            'Subtype' => new PdfName('Image'),
                            'Width' => new PdfNumber($decoded['width']),
                            'Height' => new PdfNumber($decoded['height']),
                            'ColorSpace' => new PdfName('DeviceGray'),
                            'BitsPerComponent' => new PdfNumber(8),
                            'Filter' => new PdfName('FlateDecode'),
                        ]);
                        $smaskStream = new PdfStream($smaskDict, $alphaCompressed);
                        $this->file->register($smaskStream);
                        $smaskRef = new PdfReference($smaskStream->objectNumber);
                        $dict->set('Filter', new PdfName('FlateDecode'));
                        $data = $colourCompressed;
                    }
                }
            } else {
                $idat = \Phpdftk\ImageMetadata\PngParser::extractIdatData($data);
                if ($idat !== null) {
                    $dict->set('Filter', new PdfName('FlateDecode'));
                    $dict->set(
                        'DecodeParms',
                        new PdfDictionary([
                            'Predictor' => new PdfNumber(15),
                            'Colors' => new PdfNumber($components),
                            'BitsPerComponent' => new PdfNumber(8),
                            'Columns' => new PdfNumber($info->width),
                        ]),
                    );
                    $data = $idat;
                }
            }
        }
        if ($smaskRef !== null) {
            $dict->set('SMask', $smaskRef);
        }

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
     * @deprecated Use {@see PdfDoc::setOutline()} instead. This forwarder
     *             is retained for one minor release and will be removed.
     */
    public function setOutline(Outline $outline): Outline
    {
        return $this->doc()->setOutline($outline);
    }

    /**
     * @deprecated Use {@see PdfDoc::addOutlineItem()} instead. This
     *             forwarder is retained for one minor release.
     */
    public function addOutlineItem(OutlineItem $item): PdfReference
    {
        return $this->doc()->addOutlineItem($item);
    }

    /**
     * @deprecated Use {@see PdfDoc::setPageLabels()} instead. This
     *             forwarder is retained for one minor release.
     *
     * @param array<int, PageLabel> $labels
     */
    public function setPageLabels(array $labels): void
    {
        $this->doc()->setPageLabels($labels);
    }

    /**
     * @deprecated Use {@see PdfDoc::setNamedDestinations()} instead. This
     *             forwarder is retained for one minor release.
     *
     * @param array<string, Destination> $destinations
     */
    public function setNamedDestinations(array $destinations): void
    {
        $this->doc()->setNamedDestinations($destinations);
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
     * Configure digital signing for this document.
     *
     * The signing lifecycle works in three phases:
     *   1. **Placeholder:** A SignatureValue dictionary is emitted with a
     *      zeroed /Contents hex string large enough to hold the final
     *      PKCS#7 DER blob (`$placeholderBytes` controls the size).
     *   2. **Byte-range:** After the full PDF is assembled, the /ByteRange
     *      array is patched to cover everything except the /Contents value
     *      itself, so the signature covers the entire file.
     *   3. **Patch:** The Pkcs7Signer signs the byte-range data and the
     *      resulting DER is written into the /Contents placeholder.
     *
     * @see PdfFileWriter::setSigner()
     */
    public function setSigner(
        SignatureValue $signatureValue,
        Pkcs7Signer $signer,
        int $placeholderBytes = 8192,
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
        int $placeholderBytes = 16384,
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

    public function setStrictDeprecation(bool $strict = true): void
    {
        $this->file->setStrictDeprecation($strict);
    }

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->file->getVersionWarnings();
    }

    /**
     * Diagnostics for codepoints that were substituted with `?` because the
     * font's encoding could not represent them. Empty when every glyph
     * landed cleanly. Each entry names the font resource, the codepoint,
     * and how many times it was requested.
     *
     * @return list<string>
     */
    public function getEncodingWarnings(): array
    {
        $warnings = [];
        foreach ($this->fontEncoders as $resourceName => $encoder) {
            $missing = $encoder->getMissingCodepoints();
            if ($missing === []) {
                continue;
            }
            $counts = array_count_values($missing);
            foreach ($counts as $cp => $count) {
                $warnings[] = sprintf(
                    'Font %s: codepoint U+%04X has no WinAnsi mapping (substituted ? %dx)',
                    $resourceName,
                    $cp,
                    $count,
                );
            }
        }
        return $warnings;
    }

    /**
     * Enable or disable linearized (web-optimized) PDF output.
     *
     * When enabled, the generated PDF places the first page's objects at
     * the front of the file, allowing a viewer to display it before
     * downloading the rest (ISO 32000-2 Annex F).
     */
    public function setLinearized(bool $linearized = true): void
    {
        $this->linearized = $linearized;
    }

    /**
     * Set one or more conformance profiles (e.g. PDF/A-1b, PDF/UA-1).
     *
     * When set, `generate()` will:
     *   1. Auto-inject XMP identification metadata (if not already present)
     *   2. Pin the PDF version to the profile minimum
     *   3. Run all applicable constraint checks
     *   4. In strict mode (default): throw ConformanceException on errors
     *   5. In lenient mode: collect results in getConformanceResults()
     *
     * @param bool $strict Throw on conformance errors (default true)
     */
    public function setConformance(ConformanceProfile $profile, bool $strict = true): void
    {
        $this->conformanceMode = new ConformanceMode([$profile], $strict);
    }

    /**
     * Set multiple conformance profiles at once (e.g. PDF/A-2a + PDF/UA-1).
     *
     * @param ConformanceProfile[] $profiles
     * @param bool $strict Throw on conformance errors (default true)
     */
    public function setConformanceProfiles(array $profiles, bool $strict = true): void
    {
        $this->conformanceMode = new ConformanceMode($profiles, $strict);
    }

    /**
     * Run conformance checks without generating the PDF.
     *
     * @return list<ConformanceResult>
     */
    public function checkConformance(): array
    {
        if ($this->conformanceMode === null) {
            return [];
        }

        $inspector = new WriterDocumentInspector(
            $this->catalog,
            $this->file,
            $this->fonts,
        );

        $validator = new ConformanceValidator();
        return $validator->validateAll($inspector, $this->conformanceMode->profiles);
    }

    /**
     * Get the conformance results from the last generate() call.
     *
     * @return list<ConformanceResult>
     */
    public function getConformanceResults(): array
    {
        return $this->conformanceResults;
    }

    /**
     * Generate the complete PDF as a binary string.
     */
    public function generate(): string
    {
        if ($this->conformanceMode !== null) {
            $this->applyConformance();
        }

        if ($this->linearized) {
            return $this->file->generateLinearized($this->collectFirstPageObjectNumbers());
        }
        return $this->file->generate();
    }

    /**
     * Alias for {@see generate()} — returns the raw PDF bytes as a string.
     */
    public function toBytes(): string
    {
        return $this->generate();
    }

    /**
     * Write the generated PDF to an open stream resource.
     *
     * @param resource $stream
     */
    public function writeTo($stream): int
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException(
                'PdfWriter::writeTo() expects an open stream resource',
            );
        }
        $pdf = $this->generate();
        $written = fwrite($stream, $pdf);
        if ($written === false) {
            throw new \RuntimeException('Failed to write PDF bytes to stream');
        }
        return $written;
    }

    /**
     * Write the PDF to a file, creating parent directories as needed.
     */
    public function save(string $path): void
    {
        $pdf = $this->generate();
        LocalFilesystem::writeFile($path, $pdf, createDirectories: true);
    }

    /**
     * Collect object numbers belonging to the first page for linearization.
     *
     * Includes the catalog, page tree, first page, its content streams,
     * and all fonts/images referenced by the first page's resources.
     *
     * @return list<int>
     */
    private function collectFirstPageObjectNumbers(): array
    {
        $nums = [];

        // Catalog and page tree are always first-page objects
        $nums[] = $this->catalog->objectNumber;
        $nums[] = $this->pageTree->objectNumber;

        // First page and its content streams
        if (!empty($this->pages)) {
            $firstPage = $this->pages[0];
            $nums[] = $firstPage->objectNumber;

            foreach ($firstPage->contents as $ref) {
                $nums[] = $ref->objectNumber;
            }

            // Fonts and images registered on the first page's resources
            if ($firstPage->resources !== null) {
                foreach ($firstPage->resources->font as $ref) {
                    $nums[] = $ref->objectNumber;
                }
                foreach ($firstPage->resources->xObject as $ref) {
                    $nums[] = $ref->objectNumber;
                }
            }
        }

        // Info dict if present
        if ($this->file->getInfo() !== null) {
            $nums[] = $this->file->getInfo()->objectNumber;
        }

        return $nums;
    }

    /**
     * @deprecated Use {@see PdfDoc::setMetadata()} instead. This
     *             forwarder is retained for one minor release.
     */
    public function setMetadata(string $xmpXml): void
    {
        $this->doc()->setMetadata($xmpXml);
    }

    /**
     * @deprecated Use {@see PdfDoc::syncInfoToMetadata()} instead. This
     *             forwarder is retained for one minor release.
     */
    public function syncInfoToMetadata(): void
    {
        $this->doc()->syncInfoToMetadata();
    }

    /**
     * Lazily-constructed PdfDoc view over this writer, used by the
     * deprecated forwarding stubs above. New code should use PdfDoc
     * directly.
     */
    private function doc(): PdfDoc
    {
        return $this->cachedDoc ??= PdfDoc::wrap($this);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Apply conformance: auto-inject XMP, pin version, validate.
     */
    private function applyConformance(): void
    {
        $mode = $this->conformanceMode;

        foreach ($mode->profiles as $profile) {
            // Pin PDF version to profile minimum
            $required = $profile->getPdfVersion();
            if ($required->isGreaterThan($this->file->getPdfVersion())) {
                $this->file->setVersion($required);
            }

            // Auto-inject XMP identification if not already present
            if (!$this->catalog->metadata) {
                $info = $this->file->getInfo();
                $xmpWriter = new ConformanceXmpWriter();
                $xmp = $xmpWriter->buildXmp(
                    $profile,
                    title: $info?->title->value ?? '',
                    creator: $info?->author->value ?? '',
                    producer: $info?->producer->value ?? 'phpdftk',
                );
                $this->setMetadata($xmp);
            }
        }

        // Run validation
        $this->conformanceResults = $this->checkConformance();

        // In strict mode, throw on any non-compliant result
        if ($mode->strict) {
            $failures = array_filter(
                $this->conformanceResults,
                static fn(ConformanceResult $r) => !$r->isCompliant,
            );
            if ($failures !== []) {
                throw new ConformanceException(array_values($failures));
            }
        }
    }

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
        $font->encoding       = new PdfName('WinAnsiEncoding');
    }

    /**
     * Embed a custom Type 1 font with its font program, descriptor, and ToUnicode CMap.
     */
    private function embedType1Font(Type1Font $font): void
    {
        $data = $font->parsedFontData;

        // 1. Font program stream (Type1FontFile with /Length1, /Length2, /Length3)
        $fontStream = new Type1FontFile(
            $data->fontBytes,
            $data->length1,
            $data->length2,
            $data->length3,
        );
        $this->file->register($fontStream);

        // 2. FontDescriptor
        $descriptor = new FontDescriptor(new PdfName($data->postScriptName));
        $descriptor->flags      = $data->flags;
        $descriptor->fontBBox   = new PdfArray([
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
        $descriptor->fontFile    = new PdfReference($fontStream->objectNumber);
        $this->file->register($descriptor);

        // 3. ToUnicode CMap stream
        if (!empty($data->unicodeMap)) {
            $cmapStream = new PdfStream(new PdfDictionary(), $this->buildToUnicodeCMap($data->unicodeMap));
            $this->file->register($cmapStream);
            $font->toUnicode = new PdfReference($cmapStream->objectNumber);
        }

        // 4. Wire back to font
        $font->fontDescriptor = new PdfReference($descriptor->objectNumber);
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
