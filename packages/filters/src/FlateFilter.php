<?php declare(strict_types=1);
namespace Phpdftk\Filters;

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
