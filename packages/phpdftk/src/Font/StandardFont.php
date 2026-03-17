<?php

declare(strict_types=1);

namespace Phpdftk\Font;

/**
 * Enum of the 14 standard PDF fonts guaranteed to be available in every viewer.
 */
enum StandardFont: string
{
    case Helvetica            = 'Helvetica';
    case HelveticaBold        = 'Helvetica-Bold';
    case HelveticaOblique     = 'Helvetica-Oblique';
    case HelveticaBoldOblique = 'Helvetica-BoldOblique';
    case TimesRoman           = 'Times-Roman';
    case TimesBold            = 'Times-Bold';
    case TimesItalic          = 'Times-Italic';
    case TimesBoldItalic      = 'Times-BoldItalic';
    case Courier              = 'Courier';
    case CourierBold          = 'Courier-Bold';
    case CourierOblique       = 'Courier-Oblique';
    case CourierBoldOblique   = 'Courier-BoldOblique';
    case Symbol               = 'Symbol';
    case ZapfDingbats         = 'ZapfDingbats';
}
