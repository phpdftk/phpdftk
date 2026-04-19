<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit;

/**
 * Read-only snapshot of a PDF's Info dictionary fields.
 */
final readonly class MetadataInfo
{
    public function __construct(
        public ?string $title = null,
        public ?string $author = null,
        public ?string $subject = null,
        public ?string $keywords = null,
        public ?string $creator = null,
        public ?string $producer = null,
        public ?\DateTimeImmutable $creationDate = null,
        public ?\DateTimeImmutable $modDate = null,
        public ?string $trapped = null,
    ) {}
}
