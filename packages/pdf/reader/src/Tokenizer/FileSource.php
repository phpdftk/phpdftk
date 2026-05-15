<?php

declare(strict_types=1);

namespace Phpdftk\Pdf\Reader\Tokenizer;

use Phpdftk\Filesystem\LocalFilesystem;

final class FileSource implements Source
{
    private const BUFFER_SIZE = 8192;

    /** @var resource */
    private $handle;
    private readonly int $fileSize;

    private string $buffer = '';
    private int $bufferStart = 0;
    private int $bufferLength = 0;
    private int $position = 0;

    public function __construct(string $path)
    {
        LocalFilesystem::assertReadableFile($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file: $path");
        }
        $this->handle = $handle;
        $this->fileSize = (int) filesize($path);
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $bufferEnd = $this->bufferStart + $this->bufferLength;
        $offsetInBuffer = $this->position - $this->bufferStart;

        // Fast path: entire read is within the current buffer
        if ($this->position >= $this->bufferStart && ($this->position + $length) <= $bufferEnd) {
            $result = substr($this->buffer, $offsetInBuffer, $length);
            $this->position += $length;
            return $result;
        }

        // Slow path: read directly from file
        fseek($this->handle, $this->position);
        $result = fread($this->handle, $length);
        if ($result === false) {
            return '';
        }
        $this->position += strlen($result);
        $this->invalidateBuffer();
        return $result;
    }

    public function readByte(): ?string
    {
        if ($this->position >= $this->fileSize) {
            return null;
        }

        $offsetInBuffer = $this->position - $this->bufferStart;
        if ($offsetInBuffer >= 0 && $offsetInBuffer < $this->bufferLength) {
            $byte = $this->buffer[$offsetInBuffer];
            $this->position++;
            return $byte;
        }

        $this->fillBuffer($this->position);
        if ($this->bufferLength === 0) {
            return null;
        }
        $byte = $this->buffer[0];
        $this->position++;
        return $byte;
    }

    public function peek(int $length = 1): string
    {
        if ($this->position >= $this->fileSize) {
            return '';
        }

        $offsetInBuffer = $this->position - $this->bufferStart;
        if ($offsetInBuffer >= 0 && ($offsetInBuffer + $length) <= $this->bufferLength) {
            return $length === 1
                ? $this->buffer[$offsetInBuffer]
                : substr($this->buffer, $offsetInBuffer, $length);
        }

        $this->fillBuffer($this->position);
        if ($this->bufferLength === 0) {
            return '';
        }
        return $length === 1
            ? $this->buffer[0]
            : substr($this->buffer, 0, min($length, $this->bufferLength));
    }

    public function seek(int $offset): void
    {
        $this->position = $offset;
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function size(): int
    {
        return $this->fileSize;
    }

    public function isEof(): bool
    {
        return $this->position >= $this->fileSize;
    }

    private function fillBuffer(int $offset): void
    {
        $this->bufferStart = $offset;
        fseek($this->handle, $offset);
        $data = fread($this->handle, self::BUFFER_SIZE);
        if ($data === false || $data === '') {
            $this->buffer = '';
            $this->bufferLength = 0;
            return;
        }
        $this->buffer = $data;
        $this->bufferLength = strlen($data);
    }

    private function invalidateBuffer(): void
    {
        $this->bufferLength = 0;
    }
}
