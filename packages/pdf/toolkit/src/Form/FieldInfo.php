<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Form;

/**
 * Read-only snapshot of a form field's properties.
 */
final readonly class FieldInfo
{
    /**
     * @param string      $name    Fully qualified field name (dot-separated for hierarchical fields)
     * @param FieldType   $type    Field type (Text, Button, Choice, Signature)
     * @param string|null $value   Current value as string, or null if unset
     * @param int         $flags   Field flags (/Ff)
     * @param float[]|null $rect   Widget rectangle [x1, y1, x2, y2], or null if no widget
     * @param int|null    $maxLen  Maximum length for text fields
     * @param string[]|null $options Available options for choice fields
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public ?string $value = null,
        public int $flags = 0,
        public ?array $rect = null,
        public ?int $maxLen = null,
        public ?array $options = null,
    ) {}
}
