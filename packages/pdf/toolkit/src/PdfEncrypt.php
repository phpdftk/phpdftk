<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit;

use ApprLabs\Pdf\Core\Document\Catalog;
use ApprLabs\Pdf\Core\Document\Page;
use ApprLabs\Pdf\Core\Document\PageTree;
use ApprLabs\Pdf\Core\File\PdfFileWriter;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfDictionary;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfObject;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfStream;
use ApprLabs\Pdf\Core\PdfString;
use ApprLabs\Pdf\Core\Security\PdfEncryptor;
use ApprLabs\Pdf\Reader\PdfReader;
use ApprLabs\Pdf\Toolkit\Encryption\EncryptionMethod;
use ApprLabs\Pdf\Toolkit\Encryption\Permission;

/**
 * Apply, change, or remove encryption on existing PDFs.
 *
 * Usage:
 *   PdfEncrypt::open('doc.pdf')
 *       ->encrypt('user', 'owner', EncryptionMethod::Aes256)
 *       ->save('encrypted.pdf');
 *
 *   PdfEncrypt::open('encrypted.pdf', 'password')
 *       ->decrypt()
 *       ->save('decrypted.pdf');
 */
final class PdfEncrypt
{
    private string $originalBytes;

    /** @var list<string> */
    private array $lastVersionWarnings = [];

    private ?EncryptionMethod $newMethod = null;
    private string $newUserPassword = '';
    private string $newOwnerPassword = '';
    private int $newPermissions = Permission::ALL;
    private bool $shouldDecrypt = false;

    private function __construct(
        private readonly PdfReader $reader,
        string $originalBytes,
    ) {
        $this->originalBytes = $originalBytes;
    }

    public static function open(string $path, string $password = ''): self
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException("Cannot read file: $path");
        }
        return new self(PdfReader::fromString($bytes, $password), $bytes);
    }

    public static function openString(string $pdfBytes, string $password = ''): self
    {
        return new self(PdfReader::fromString($pdfBytes, $password), $pdfBytes);
    }

    // -----------------------------------------------------------------------
    // Operations
    // -----------------------------------------------------------------------

    public function encrypt(
        string $userPassword,
        string $ownerPassword,
        EncryptionMethod $method = EncryptionMethod::Aes256,
        int $permissions = Permission::ALL,
    ): self {
        $this->newMethod = $method;
        $this->newUserPassword = $userPassword;
        $this->newOwnerPassword = $ownerPassword;
        $this->newPermissions = $permissions;
        $this->shouldDecrypt = false;
        return $this;
    }

    public function decrypt(): self
    {
        $this->shouldDecrypt = true;
        $this->newMethod = null;
        return $this;
    }

    public function changePasswords(string $newUserPassword, string $newOwnerPassword): self
    {
        $this->newUserPassword = $newUserPassword;
        $this->newOwnerPassword = $newOwnerPassword;
        // Keep existing method if not explicitly set
        if ($this->newMethod === null && !$this->shouldDecrypt) {
            $this->newMethod = EncryptionMethod::Aes256;
        }
        return $this;
    }

    public function setPermissions(int $permissions): self
    {
        $this->newPermissions = $permissions;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Query
    // -----------------------------------------------------------------------

    public function isEncrypted(): bool
    {
        $trailer = $this->reader->getTrailer();
        return $trailer->get('Encrypt') !== null;
    }

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $this->toBytes());
    }

    public function toBytes(): string
    {
        if ($this->newMethod === null && !$this->shouldDecrypt) {
            return $this->originalBytes;
        }

        // Full rewrite: read all pages from reader, rebuild PDF
        $fw = new PdfFileWriter();

        // Generate file ID
        $fileId = md5(microtime() . random_bytes(16), true);

        // Set up encryption if not decrypting
        if ($this->newMethod !== null && !$this->shouldDecrypt) {
            $encryptor = match ($this->newMethod) {
                EncryptionMethod::Rc440 => PdfEncryptor::rc440(
                    $this->newUserPassword, $this->newOwnerPassword, $fileId, $this->newPermissions,
                ),
                EncryptionMethod::Rc4128 => PdfEncryptor::rc4128(
                    $this->newUserPassword, $this->newOwnerPassword, $fileId, $this->newPermissions,
                ),
                EncryptionMethod::Aes128 => PdfEncryptor::aes128(
                    $this->newUserPassword, $this->newOwnerPassword, $fileId, $this->newPermissions,
                ),
                EncryptionMethod::Aes256 => PdfEncryptor::aes256(
                    $this->newUserPassword, $this->newOwnerPassword, $fileId, $this->newPermissions,
                ),
                default => throw new \RuntimeException('Public-key encryption not supported for re-encryption'),
            };
            $fw->setEncryption($encryptor);
        }

        // Rebuild document structure
        $catalog = new Catalog();
        $fw->setCatalog($catalog);

        $pageTree = new PageTree();
        $fw->register($pageTree);
        $catalog->pages = new PdfReference($pageTree->objectNumber);

        // Copy Info if present
        $infoDict = $this->reader->getInfo();
        if ($infoDict !== null) {
            $info = new class ($infoDict) extends PdfObject {
                public function __construct(private readonly PdfDictionary $dict) {}
                public function toPdf(): string { return $this->dict->toPdf(); }
            };
            $fw->register($info);
            $fw->setInfo($info);
        }

        // Copy pages
        $pages = $this->reader->getPages();
        $pageRefs = [];
        foreach ($pages as $pageDict) {
            // Copy page content and resources
            $page = $this->copyPage($pageDict, $fw);
            $fw->register($page);
            $pageRefs[] = new PdfReference($page->objectNumber);
            $page->parent = new PdfReference($pageTree->objectNumber);
        }

        $pageTree->kids = $pageRefs;
        $pageTree->count = count($pageRefs);

        $result = $fw->generate();
        $this->lastVersionWarnings = $fw->getVersionWarnings();
        return $result;
    }

    // -----------------------------------------------------------------------
    // Escape hatches
    // -----------------------------------------------------------------------

    /** @return list<string> */
    public function getVersionWarnings(): array
    {
        return $this->lastVersionWarnings;
    }

    public function getReader(): PdfReader
    {
        return $this->reader;
    }

    public function getPageCount(): int
    {
        return $this->reader->getPageCount();
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    private function copyPage(PdfDictionary $sourceDict, PdfFileWriter $fw): Page
    {
        $page = new Page();

        // Copy MediaBox
        $mediaBox = $sourceDict->get('MediaBox');
        if ($mediaBox instanceof PdfArray) {
            $page->mediaBox = $mediaBox;
        }

        // Copy Rotate
        $rotate = $sourceDict->get('Rotate');
        if ($rotate instanceof PdfNumber) {
            $page->rotate = (int) $rotate->toPdf();
        }

        // Copy content streams
        $contents = $sourceDict->get('Contents');
        if ($contents instanceof PdfReference) {
            $stream = $this->reader->resolveReference($contents);
            if ($stream instanceof PdfStream) {
                $newCs = new class ($stream) extends PdfStream {
                    public function __construct(PdfStream $source)
                    {
                        parent::__construct(clone $source->dictionary, $source->data);
                    }
                };
                $fw->register($newCs);
                $page->contents = [new PdfReference($newCs->objectNumber)];
            }
        } elseif ($contents instanceof PdfArray) {
            $contentRefs = [];
            foreach ($contents->items as $ref) {
                if ($ref instanceof PdfReference) {
                    $stream = $this->reader->resolveReference($ref);
                    if ($stream instanceof PdfStream) {
                        $newCs = new class ($stream) extends PdfStream {
                            public function __construct(PdfStream $source)
                            {
                                parent::__construct(clone $source->dictionary, $source->data);
                            }
                        };
                        $fw->register($newCs);
                        $contentRefs[] = new PdfReference($newCs->objectNumber);
                    }
                }
            }
            $page->contents = $contentRefs;
        }

        // Copy resources using PageCopier's resource builder
        $resources = $sourceDict->get('Resources');
        $resDict = null;
        if ($resources instanceof PdfDictionary) {
            $resDict = $resources;
        } elseif ($resources instanceof PdfReference) {
            $resolved = $this->reader->resolveReference($resources);
            if ($resolved instanceof PdfDictionary) {
                $resDict = $resolved;
            }
        }
        if ($resDict !== null) {
            $copier = new \ApprLabs\Pdf\Toolkit\Internal\PageCopier($this->reader, $fw);
            // Use copyPages on a temp page to leverage the resource copy logic
            // Instead, build Resources directly
            $res = new \ApprLabs\Pdf\Core\Content\Resources();
            $fontDict = $resDict->get('Font');
            if ($fontDict instanceof PdfDictionary) {
                foreach (array_keys($fontDict->entries) as $name) {
                    $ref = $fontDict->entries[$name];
                    if ($ref instanceof PdfReference) {
                        $resolved = $this->reader->resolveReference($ref);
                        if ($resolved instanceof PdfObject) {
                            $clone = clone $resolved;
                            $clone->objectNumber = 0;
                            $fw->register($clone);
                            $res->font[$name] = new PdfReference($clone->objectNumber);
                        } elseif ($resolved instanceof PdfDictionary) {
                            $wrapper = new class ($resolved) extends PdfObject {
                                public function __construct(private readonly PdfDictionary $d) {}
                                public function toPdf(): string { return $this->d->toPdf(); }
                            };
                            $fw->register($wrapper);
                            $res->font[$name] = new PdfReference($wrapper->objectNumber);
                        }
                    }
                }
            }
            $page->resources = $res;
        }

        return $page;
    }

}
