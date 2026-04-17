<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Reader\Tokenizer;

final class StringSource implements Source
{
    private int $position = 0;
    private readonly int $length;

    public function __construct(private readonly string $data)
    {
        $this->length = strlen($data);
    }

    public function read(int $length): string
    {
        $result = substr($this->data, $this->position, $length);
        $this->position += strlen($result);
        return $result;
    }

    public function readByte(): ?string
    {
        if ($this->position >= $this->length) {
            return null;
        }
        return $this->data[$this->position++];
    }

    public function peek(int $length = 1): string
    {
        return substr($this->data, $this->position, $length);
    }

    public function seek(int $offset): void
    {
        $this->position = max(0, min($offset, $this->length));
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function size(): int
    {
        return $this->length;
    }

    public function isEof(): bool
    {
        return $this->position >= $this->length;
    }
}
