<?php declare(strict_types=1);
namespace Phpdftk\Filters;

/**
 * FlateDecode — zlib/deflate compression (ISO 32000-2 §7.4.4).
 *
 * The default filter for PDF streams. Compression level 6 (the default)
 * gives a good speed/size balance for typical PDF content. Higher levels
 * yield marginal gains at significantly more CPU cost.
 */
final class FlateFilter implements FilterInterface {
    public function __construct(private int $level = 6) {}

    public function encode(string $data): string {
        $result = gzcompress($data, $this->level);
        if ($result === false) {
            throw new \RuntimeException('FlateFilter encode failed');
        }
        return $result;
    }

    public function decode(string $data): string {
        $result = @gzuncompress($data);
        if ($result === false) {
            throw new \RuntimeException('FlateFilter decode failed');
        }
        return $result;
    }
}
