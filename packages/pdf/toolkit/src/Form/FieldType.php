<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Toolkit\Form;

/**
 * PDF interactive form field types (ISO 32000-2 Table 226).
 */
enum FieldType: string
{
    case Text = 'Tx';
    case Button = 'Btn';  // covers checkbox, radio, push button
    case Choice = 'Ch';
    case Signature = 'Sig';
}
