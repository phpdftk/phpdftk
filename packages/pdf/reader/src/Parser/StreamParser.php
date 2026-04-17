<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Parser;

use ApprLabs\Filters\Ascii85Filter;
use ApprLabs\Filters\AsciiHexFilter;
use ApprLabs\Filters\FlateFilter;
use ApprLabs\Filters\LzwFilter;
use ApprLabs\Filters\PredictorFilter;
use ApprLabs\Filters\RunLengthFilter;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Reader\Exception\UnsupportedFilterException;
use ApprLabs\Pdf\Reader\ObjectResolver;

/**
 * Applies the filter pipeline from a stream dictionary's `/Filter`
 * entry to decompress raw stream data.
 */
final class StreamParser
{
    private ?ObjectResolver $resolver = null;

    /**
     * Set the object resolver for resolving indirect /DecodeParms references.
     */
    public function setResolver(ObjectResolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * Decode stream data using the filter(s) declared in $dict.
     */
    public function decode(string $data, PdfDictionary $dict): string
    {
        $filter = $dict->get('Filter');
        if ($filter === null) {
            return $data;
        }

        $filterNames = $this->resolveFilterNames($filter);
        $decodeParms = $this->resolveDecodeParms($dict->get('DecodeParms'), count($filterNames));

        foreach ($filterNames as $index => $name) {
            $params = $decodeParms[$index] ?? null;

            $data = match ($name) {
                'FlateDecode', 'Fl'      => $this->decodeFlate($data, $params),
                'LZWDecode', 'LZW'       => $this->decodeLzw($data, $params),
                'ASCII85Decode', 'A85'   => (new Ascii85Filter())->decode($data),
                'ASCIIHexDecode', 'AHx'  => (new AsciiHexFilter())->decode($data),
                'RunLengthDecode', 'RL'  => (new RunLengthFilter())->decode($data),
                // Image-specific filters: return data as-is (the raw bytes ARE the image)
                'DCTDecode', 'DCT',
                'JPXDecode',
                'CCITTFaxDecode', 'CCF',
                'JBIG2Decode'            => $data,
                default                  => throw new UnsupportedFilterException(
                    "Unsupported stream filter: $name"
                ),
            };
        }

        return $data;
    }

    /**
     * Decode FlateDecode with optional predictor post-processing.
     */
    private function decodeFlate(string $data, ?PdfDictionary $params): string
    {
        $data = (new FlateFilter())->decode($data);
        return $this->applyPredictor($data, $params);
    }

    /**
     * Decode LZWDecode with optional predictor post-processing.
     */
    private function decodeLzw(string $data, ?PdfDictionary $params): string
    {
        $earlyChange = $params !== null ? $this->intParam($params, 'EarlyChange', 1) : 1;
        $data = (new LzwFilter($earlyChange))->decode($data);
        return $this->applyPredictor($data, $params);
    }

    /**
     * Apply predictor un-filtering if the DecodeParms specify one.
     */
    private function applyPredictor(string $data, ?PdfDictionary $params): string
    {
        if ($params === null) {
            return $data;
        }

        $predictor = $this->intParam($params, 'Predictor', 1);
        if ($predictor <= 1) {
            return $data;
        }

        $columns = $this->intParam($params, 'Columns', 1);
        $colors = $this->intParam($params, 'Colors', 1);
        $bpc = $this->intParam($params, 'BitsPerComponent', 8);

        $filter = new PredictorFilter($predictor, $columns, $colors, $bpc);
        return $filter->decode($data);
    }

    /**
     * Extract an integer parameter from a DecodeParms dictionary.
     */
    private function intParam(PdfDictionary $dict, string $key, int $default): int
    {
        $val = $dict->get($key);
        if ($val instanceof PdfNumber) {
            return (int) $val->toPdf();
        }
        if (is_int($val)) {
            return $val;
        }
        return $default;
    }

    /**
     * @return list<string>
     */
    private function resolveFilterNames(mixed $filter): array
    {
        if ($filter instanceof PdfName) {
            return [$filter->value];
        }
        if ($filter instanceof PdfArray) {
            $names = [];
            foreach ($filter->items as $item) {
                if ($item instanceof PdfName) {
                    $names[] = $item->value;
                }
            }
            return $names;
        }
        if (is_string($filter)) {
            return [ltrim($filter, '/')];
        }
        return [];
    }

    /**
     * Resolve /DecodeParms into a per-filter array of PdfDictionary|null.
     *
     * @return list<PdfDictionary|null>
     */
    private function resolveDecodeParms(mixed $parms, int $filterCount): array
    {
        // Resolve indirect reference if resolver is available
        if ($parms instanceof PdfReference && $this->resolver !== null) {
            $parms = $this->resolver->resolveReference($parms);
        }

        if ($parms === null) {
            return array_fill(0, $filterCount, null);
        }
        if ($parms instanceof PdfDictionary) {
            return [$parms];
        }
        if ($parms instanceof PdfArray) {
            $result = [];
            foreach ($parms->items as $item) {
                if ($item instanceof PdfReference && $this->resolver !== null) {
                    $item = $this->resolver->resolveReference($item);
                }
                $result[] = $item instanceof PdfDictionary ? $item : null;
            }
            return $result;
        }
        return array_fill(0, $filterCount, null);
    }
}
