<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Document;

use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfObject;

/**
 * PDF Optional Content Properties Dictionary (ISO 32000-2 Table 100).
 *
 * The /OCProperties entry in the Catalog. Contains all OCGs and
 * the default viewing configuration.
 *
 * Example:
 *   $ocProps = new OCPropertiesDict($ocgsArray, $defaultConfig);
 *   $writer->register($ocProps);
 *   $catalog->ocProperties = new PdfReference($ocProps->objectNumber);
 */
class OCPropertiesDict extends PdfObject
{
    public PdfArray $ocgs;             // /OCGs - required, all OCG refs
    public PdfDictionary $d;           // /D - required, default viewing config
    public ?PdfArray $configs = null;  // /Configs - optional alternate configs

    public function __construct(PdfArray $ocgs, PdfDictionary $defaultConfig)
    {
        $this->ocgs = $ocgs;
        $this->d = $defaultConfig;
    }

    public function toPdf(): string
    {
        $dict = new PdfDictionary();
        $dict->set('OCGs', $this->ocgs);
        $dict->set('D', $this->d);

        if ($this->configs !== null) {
            $dict->set('Configs', $this->configs);
        }

        return $dict->toPdf();
    }
}
