<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Encryption;

enum EncryptionMethod
{
    case Rc440;
    case Rc4128;
    case Aes128;
    case Aes256;
    case PublicKeyAes128;
    case PublicKeyAes256;
}
