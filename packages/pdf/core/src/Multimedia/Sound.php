<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Core\Multimedia;

use Phpdftk\Pdf\Core\PdfDictionary;
use Phpdftk\Pdf\Core\PdfName;
use Phpdftk\Pdf\Core\PdfNumber;
use Phpdftk\Pdf\Core\PdfStream;
use Phpdftk\Pdf\Core\DeprecatedPdfFeature;
use Phpdftk\Pdf\Core\PdfVersion;
use Phpdftk\Pdf\Core\RequiresPdfVersion;

/**
 * Sound object (/Type /Sound) — ISO 32000-2 §13.3.
 *
 * A stream that holds sampled audio data. Referenced by the /Sound entry
 * of a SoundAction and SoundAnnotation.
 *
 * Required: R (sample rate). Common: C (channels), B (bits per sample),
 * E (encoding), CO (compression format), CP (compression params).
 */
#[RequiresPdfVersion(PdfVersion::V1_2)]
#[DeprecatedPdfFeature(since: '2.0', replacement: 'MediaRendition', removedIn: '2.0')]
class Sound extends PdfStream
{
    public const PDF_TYPE = 'Sound';

    public float $r;                       // /R  sample rate (Hz)
    public ?int $c = null;                 // /C  channels (1, 2)
    public ?int $b = null;                 // /B  bits per sample
    public ?PdfName $e = null;             // /E  encoding (Raw, Signed, muLaw, ALaw)
    public ?PdfName $co = null;            // /CO compression format
    public ?PdfDictionary $cp = null;      // /CP compression params

    public function __construct(float $sampleRate, string $samples = '')
    {
        parent::__construct(new PdfDictionary(), $samples);
        $this->r = $sampleRate;
    }

    public function toPdf(): string
    {
        $this->dictionary->set('Type', new PdfName(self::PDF_TYPE));
        $this->dictionary->set('R', new PdfNumber($this->r));
        if ($this->c !== null) {
            $this->dictionary->set('C', new PdfNumber($this->c));
        }
        if ($this->b !== null) {
            $this->dictionary->set('B', new PdfNumber($this->b));
        }
        if ($this->e !== null) {
            $this->dictionary->set('E', $this->e);
        }
        if ($this->co !== null) {
            $this->dictionary->set('CO', $this->co);
        }
        if ($this->cp !== null) {
            $this->dictionary->set('CP', $this->cp);
        }
        return parent::toPdf();
    }
}
