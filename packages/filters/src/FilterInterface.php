<?php declare(strict_types=1);
namespace ApprLabs\Filters;

interface FilterInterface {
    public function encode(string $data): string;
    public function decode(string $data): string;
}
