<?php declare(strict_types=1);
namespace ApprLabs\Color;

interface ColorInterface {
    /** @return array<int, float> */
    public function toArray(): array;
    public function getColorSpace(): string;
}
