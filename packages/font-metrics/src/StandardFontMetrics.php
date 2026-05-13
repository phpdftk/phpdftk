<?php

declare(strict_types=1);

namespace Phpdftk\FontMetrics;

final class StandardFontMetrics
{
    /** @var array<string, AfmData>|null */
    private static ?array $registry = null;

    public static function get(string $postScriptName): AfmData
    {
        self::$registry ??= self::buildRegistry();
        if (!isset(self::$registry[$postScriptName])) {
            throw new \InvalidArgumentException("Unknown standard font: $postScriptName");
        }
        return self::$registry[$postScriptName];
    }

    /** @return array<string, AfmData> */
    private static function buildRegistry(): array
    {
        $reg = [];

        // -----------------------------------------------------------------------
        // Helvetica
        // -----------------------------------------------------------------------
        $reg['Helvetica'] = new AfmData(
            ascender: 718,
            descender: -207,
            capHeight: 718,
            xHeight: 523,
            italicAngle: 0,
            stemV: 88,
            missingWidth: 278,
            fontBBox: [-166, -225, 1000, 931],
            widths: self::helveticaWidths(),
        );

        // -----------------------------------------------------------------------
        // Helvetica-Bold
        // -----------------------------------------------------------------------
        $reg['Helvetica-Bold'] = new AfmData(
            ascender: 718,
            descender: -207,
            capHeight: 718,
            xHeight: 532,
            italicAngle: 0,
            stemV: 140,
            missingWidth: 278,
            fontBBox: [-170, -228, 1003, 962],
            widths: self::helveticaBoldWidths(),
        );

        // -----------------------------------------------------------------------
        // Helvetica-Oblique (same widths as Helvetica)
        // -----------------------------------------------------------------------
        $reg['Helvetica-Oblique'] = new AfmData(
            ascender: 718,
            descender: -207,
            capHeight: 718,
            xHeight: 523,
            italicAngle: -12,
            stemV: 88,
            missingWidth: 278,
            fontBBox: [-166, -225, 1000, 931],
            widths: self::helveticaWidths(),
        );

        // -----------------------------------------------------------------------
        // Helvetica-BoldOblique (same widths as Helvetica-Bold)
        // -----------------------------------------------------------------------
        $reg['Helvetica-BoldOblique'] = new AfmData(
            ascender: 718,
            descender: -207,
            capHeight: 718,
            xHeight: 532,
            italicAngle: -12,
            stemV: 140,
            missingWidth: 278,
            fontBBox: [-170, -228, 1003, 962],
            widths: self::helveticaBoldWidths(),
        );

        // -----------------------------------------------------------------------
        // Times-Roman
        // -----------------------------------------------------------------------
        $reg['Times-Roman'] = new AfmData(
            ascender: 683,
            descender: -217,
            capHeight: 662,
            xHeight: 450,
            italicAngle: 0,
            stemV: 84,
            missingWidth: 250,
            fontBBox: [-168, -218, 1000, 898],
            widths: self::timesRomanWidths(),
        );

        // -----------------------------------------------------------------------
        // Times-Bold
        // -----------------------------------------------------------------------
        $reg['Times-Bold'] = new AfmData(
            ascender: 683,
            descender: -217,
            capHeight: 676,
            xHeight: 461,
            italicAngle: 0,
            stemV: 139,
            missingWidth: 250,
            fontBBox: [-168, -218, 1000, 935],
            widths: self::timesBoldWidths(),
        );

        // -----------------------------------------------------------------------
        // Times-Italic
        // -----------------------------------------------------------------------
        $reg['Times-Italic'] = new AfmData(
            ascender: 683,
            descender: -217,
            capHeight: 653,
            xHeight: 441,
            italicAngle: -15.5,
            stemV: 76,
            missingWidth: 250,
            fontBBox: [-169, -217, 1010, 883],
            widths: self::timesItalicWidths(),
        );

        // -----------------------------------------------------------------------
        // Times-BoldItalic
        // -----------------------------------------------------------------------
        $reg['Times-BoldItalic'] = new AfmData(
            ascender: 683,
            descender: -217,
            capHeight: 669,
            xHeight: 462,
            italicAngle: -15,
            stemV: 121,
            missingWidth: 250,
            fontBBox: [-200, -218, 996, 921],
            widths: self::timesBoldItalicWidths(),
        );

        // -----------------------------------------------------------------------
        // Courier (all glyphs = 600)
        // -----------------------------------------------------------------------
        $courierWidths = self::courierWidths();
        $reg['Courier'] = new AfmData(
            ascender: 629,
            descender: -157,
            capHeight: 562,
            xHeight: 426,
            italicAngle: 0,
            stemV: 51,
            missingWidth: 600,
            fontBBox: [-23, -250, 715, 805],
            widths: $courierWidths,
        );

        // -----------------------------------------------------------------------
        // Courier-Bold
        // -----------------------------------------------------------------------
        $reg['Courier-Bold'] = new AfmData(
            ascender: 629,
            descender: -157,
            capHeight: 562,
            xHeight: 426,
            italicAngle: 0,
            stemV: 106,
            missingWidth: 600,
            fontBBox: [-23, -250, 715, 805],
            widths: $courierWidths,
        );

        // -----------------------------------------------------------------------
        // Courier-Oblique
        // -----------------------------------------------------------------------
        $reg['Courier-Oblique'] = new AfmData(
            ascender: 629,
            descender: -157,
            capHeight: 562,
            xHeight: 426,
            italicAngle: -12,
            stemV: 51,
            missingWidth: 600,
            fontBBox: [-23, -250, 715, 805],
            widths: $courierWidths,
        );

        // -----------------------------------------------------------------------
        // Courier-BoldOblique
        // -----------------------------------------------------------------------
        $reg['Courier-BoldOblique'] = new AfmData(
            ascender: 629,
            descender: -157,
            capHeight: 562,
            xHeight: 426,
            italicAngle: -12,
            stemV: 106,
            missingWidth: 600,
            fontBBox: [-23, -250, 715, 805],
            widths: $courierWidths,
        );

        // -----------------------------------------------------------------------
        // Symbol
        // -----------------------------------------------------------------------
        $reg['Symbol'] = new AfmData(
            ascender: 0,
            descender: 0,
            capHeight: 0,
            xHeight: 0,
            italicAngle: 0,
            stemV: 85,
            missingWidth: 250,
            fontBBox: [-180, -293, 1090, 1010],
            widths: self::symbolWidths(),
        );

        // -----------------------------------------------------------------------
        // ZapfDingbats
        // -----------------------------------------------------------------------
        $reg['ZapfDingbats'] = new AfmData(
            ascender: 0,
            descender: 0,
            capHeight: 0,
            xHeight: 0,
            italicAngle: 0,
            stemV: 90,
            missingWidth: 278,
            fontBBox: [-1, -143, 981, 820],
            widths: self::zapfDingbatsWidths(),
        );

        return $reg;
    }

    /** @return array<string, int> */
    private static function helveticaWidths(): array
    {
        return [
            'A' => 667, 'AE' => 1000, 'Aacute' => 667, 'Acircumflex' => 667,
            'Adieresis' => 667, 'Agrave' => 667, 'Aring' => 667, 'Atilde' => 667,
            'B' => 667, 'C' => 722, 'Ccedilla' => 722, 'D' => 722,
            'E' => 667, 'Eacute' => 667, 'Ecircumflex' => 667, 'Edieresis' => 667,
            'Egrave' => 667, 'Eth' => 722, 'Euro' => 556, 'F' => 611,
            'G' => 778, 'H' => 722, 'I' => 278, 'Iacute' => 278,
            'Icircumflex' => 278, 'Idieresis' => 278, 'Igrave' => 278, 'J' => 500,
            'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722,
            'Ntilde' => 722, 'O' => 778, 'OE' => 1000, 'Oacute' => 778,
            'Ocircumflex' => 778, 'Odieresis' => 778, 'Ograve' => 778, 'Oslash' => 778,
            'Otilde' => 778, 'P' => 667, 'Q' => 778, 'R' => 722,
            'S' => 667, 'Scaron' => 667, 'T' => 611, 'Thorn' => 667,
            'U' => 722, 'Uacute' => 722, 'Ucircumflex' => 722, 'Udieresis' => 722,
            'Ugrave' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
            'Y' => 667, 'Yacute' => 667, 'Ydieresis' => 667, 'Z' => 611,
            'Zcaron' => 611, 'a' => 556, 'aacute' => 556, 'acircumflex' => 556,
            'acute' => 333, 'adieresis' => 556, 'ae' => 889, 'agrave' => 556,
            'ampersand' => 667, 'aring' => 556, 'asciicircum' => 469, 'asciitilde' => 584,
            'asterisk' => 389, 'at' => 1015, 'atilde' => 556, 'b' => 556,
            'backslash' => 278, 'bar' => 260, 'braceleft' => 334, 'braceright' => 334,
            'bracketleft' => 278, 'bracketright' => 278, 'brokenbar' => 260, 'bullet' => 350,
            'c' => 500, 'ccedilla' => 500, 'cedilla' => 333, 'cent' => 556,
            'circumflex' => 333, 'colon' => 278, 'comma' => 278, 'copyright' => 737,
            'currency' => 556, 'd' => 556, 'dagger' => 556, 'daggerdbl' => 556,
            'degree' => 400, 'dieresis' => 333, 'divide' => 584, 'dollar' => 556,
            'e' => 556, 'eacute' => 556, 'ecircumflex' => 556, 'edieresis' => 556,
            'egrave' => 556, 'eight' => 556, 'ellipsis' => 1000, 'emdash' => 1000,
            'endash' => 556, 'equal' => 584, 'eth' => 556, 'exclam' => 278,
            'exclamdown' => 333, 'f' => 278, 'five' => 556, 'florin' => 556,
            'four' => 556, 'g' => 556, 'germandbls' => 611, 'grave' => 333,
            'greater' => 584, 'guillemotleft' => 556, 'guillemotright' => 556, 'guilsinglleft' => 333,
            'guilsinglright' => 333, 'h' => 556, 'hyphen' => 333, 'i' => 222,
            'iacute' => 278, 'icircumflex' => 278, 'idieresis' => 278, 'igrave' => 278,
            'j' => 222, 'k' => 500, 'l' => 222, 'less' => 584,
            'logicalnot' => 584, 'm' => 833, 'macron' => 333, 'mu' => 556,
            'multiply' => 584, 'n' => 556, 'nine' => 556, 'ntilde' => 556,
            'numbersign' => 556, 'o' => 556, 'oacute' => 556, 'ocircumflex' => 556,
            'odieresis' => 556, 'oe' => 944, 'ograve' => 556, 'one' => 556,
            'onehalf' => 834, 'onequarter' => 834, 'onesuperior' => 333, 'ordfeminine' => 370,
            'ordmasculine' => 365, 'oslash' => 611, 'otilde' => 556, 'p' => 556,
            'paragraph' => 537, 'parenleft' => 333, 'parenright' => 333, 'percent' => 889,
            'period' => 278, 'periodcentered' => 278, 'perthousand' => 1000, 'plus' => 584,
            'plusminus' => 584, 'q' => 556, 'question' => 556, 'questiondown' => 611,
            'quotedbl' => 355, 'quotedblbase' => 333, 'quotedblleft' => 333, 'quotedblright' => 333,
            'quoteleft' => 222, 'quoteright' => 222, 'quotesinglbase' => 222, 'quotesingle' => 191,
            'r' => 333, 'registered' => 737, 's' => 500, 'scaron' => 500,
            'section' => 556, 'semicolon' => 278, 'seven' => 556, 'six' => 556,
            'slash' => 278, 'space' => 278, 'sterling' => 556, 't' => 278,
            'thorn' => 556, 'three' => 556, 'threequarters' => 834, 'threesuperior' => 333,
            'tilde' => 333, 'trademark' => 1000, 'two' => 556, 'twosuperior' => 333,
            'u' => 556, 'uacute' => 556, 'ucircumflex' => 556, 'udieresis' => 556,
            'ugrave' => 556, 'underscore' => 556, 'v' => 500, 'w' => 722,
            'x' => 500, 'y' => 500, 'yacute' => 500, 'ydieresis' => 500,
            'yen' => 556, 'z' => 500, 'zcaron' => 500, 'zero' => 556,
        ];
    }

    /** @return array<string, int> */
    private static function helveticaBoldWidths(): array
    {
        return [
            'A' => 722, 'AE' => 1000, 'Aacute' => 722, 'Acircumflex' => 722,
            'Adieresis' => 722, 'Agrave' => 722, 'Aring' => 722, 'Atilde' => 722,
            'B' => 722, 'C' => 722, 'Ccedilla' => 722, 'D' => 722,
            'E' => 667, 'Eacute' => 667, 'Ecircumflex' => 667, 'Edieresis' => 667,
            'Egrave' => 667, 'Eth' => 722, 'Euro' => 556, 'F' => 611,
            'G' => 778, 'H' => 722, 'I' => 278, 'Iacute' => 278,
            'Icircumflex' => 278, 'Idieresis' => 278, 'Igrave' => 278, 'J' => 556,
            'K' => 722, 'L' => 611, 'M' => 833, 'N' => 722,
            'Ntilde' => 722, 'O' => 778, 'OE' => 1000, 'Oacute' => 778,
            'Ocircumflex' => 778, 'Odieresis' => 778, 'Ograve' => 778, 'Oslash' => 778,
            'Otilde' => 778, 'P' => 667, 'Q' => 778, 'R' => 722,
            'S' => 667, 'Scaron' => 667, 'T' => 611, 'Thorn' => 667,
            'U' => 722, 'Uacute' => 722, 'Ucircumflex' => 722, 'Udieresis' => 722,
            'Ugrave' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
            'Y' => 667, 'Yacute' => 667, 'Ydieresis' => 667, 'Z' => 611,
            'Zcaron' => 611, 'a' => 556, 'aacute' => 556, 'acircumflex' => 556,
            'acute' => 333, 'adieresis' => 556, 'ae' => 889, 'agrave' => 556,
            'ampersand' => 722, 'aring' => 556, 'asciicircum' => 584, 'asciitilde' => 584,
            'asterisk' => 389, 'at' => 975, 'atilde' => 556, 'b' => 611,
            'backslash' => 278, 'bar' => 280, 'braceleft' => 389, 'braceright' => 389,
            'bracketleft' => 333, 'bracketright' => 333, 'brokenbar' => 280, 'bullet' => 350,
            'c' => 556, 'ccedilla' => 556, 'cedilla' => 333, 'cent' => 556,
            'circumflex' => 333, 'colon' => 333, 'comma' => 278, 'copyright' => 737,
            'currency' => 556, 'd' => 611, 'dagger' => 556, 'daggerdbl' => 556,
            'degree' => 400, 'dieresis' => 333, 'divide' => 584, 'dollar' => 556,
            'e' => 556, 'eacute' => 556, 'ecircumflex' => 556, 'edieresis' => 556,
            'egrave' => 556, 'eight' => 556, 'ellipsis' => 1000, 'emdash' => 1000,
            'endash' => 556, 'equal' => 584, 'eth' => 611, 'exclam' => 333,
            'exclamdown' => 333, 'f' => 333, 'five' => 556, 'florin' => 556,
            'four' => 556, 'g' => 611, 'germandbls' => 611, 'grave' => 333,
            'greater' => 584, 'guillemotleft' => 556, 'guillemotright' => 556, 'guilsinglleft' => 333,
            'guilsinglright' => 333, 'h' => 611, 'hyphen' => 333, 'i' => 278,
            'iacute' => 278, 'icircumflex' => 278, 'idieresis' => 278, 'igrave' => 278,
            'j' => 278, 'k' => 556, 'l' => 278, 'less' => 584,
            'logicalnot' => 584, 'm' => 889, 'macron' => 333, 'mu' => 611,
            'multiply' => 584, 'n' => 611, 'nine' => 556, 'ntilde' => 611,
            'numbersign' => 556, 'o' => 611, 'oacute' => 611, 'ocircumflex' => 611,
            'odieresis' => 611, 'oe' => 944, 'ograve' => 611, 'one' => 556,
            'onehalf' => 834, 'onequarter' => 834, 'onesuperior' => 333, 'ordfeminine' => 370,
            'ordmasculine' => 365, 'oslash' => 611, 'otilde' => 611, 'p' => 611,
            'paragraph' => 556, 'parenleft' => 333, 'parenright' => 333, 'percent' => 889,
            'period' => 278, 'periodcentered' => 278, 'perthousand' => 1000, 'plus' => 584,
            'plusminus' => 584, 'q' => 611, 'question' => 611, 'questiondown' => 611,
            'quotedbl' => 474, 'quotedblbase' => 500, 'quotedblleft' => 500, 'quotedblright' => 500,
            'quoteleft' => 278, 'quoteright' => 278, 'quotesinglbase' => 278, 'quotesingle' => 238,
            'r' => 389, 'registered' => 737, 's' => 556, 'scaron' => 556,
            'section' => 556, 'semicolon' => 333, 'seven' => 556, 'six' => 556,
            'slash' => 278, 'space' => 278, 'sterling' => 556, 't' => 333,
            'thorn' => 611, 'three' => 556, 'threequarters' => 834, 'threesuperior' => 333,
            'tilde' => 333, 'trademark' => 1000, 'two' => 556, 'twosuperior' => 333,
            'u' => 611, 'uacute' => 611, 'ucircumflex' => 611, 'udieresis' => 611,
            'ugrave' => 611, 'underscore' => 556, 'v' => 556, 'w' => 778,
            'x' => 556, 'y' => 556, 'yacute' => 556, 'ydieresis' => 556,
            'yen' => 556, 'z' => 500, 'zcaron' => 500, 'zero' => 556,
        ];
    }

    /** @return array<string, int> */
    private static function timesRomanWidths(): array
    {
        return [
            'A' => 722, 'AE' => 889, 'Aacute' => 722, 'Acircumflex' => 722,
            'Adieresis' => 722, 'Agrave' => 722, 'Aring' => 722, 'Atilde' => 722,
            'B' => 667, 'C' => 667, 'Ccedilla' => 667, 'D' => 722,
            'E' => 611, 'Eacute' => 611, 'Ecircumflex' => 611, 'Edieresis' => 611,
            'Egrave' => 611, 'Eth' => 722, 'Euro' => 500, 'F' => 556,
            'G' => 722, 'H' => 722, 'I' => 333, 'Iacute' => 333,
            'Icircumflex' => 333, 'Idieresis' => 333, 'Igrave' => 333, 'J' => 389,
            'K' => 722, 'L' => 611, 'M' => 889, 'N' => 722,
            'Ntilde' => 722, 'O' => 722, 'OE' => 889, 'Oacute' => 722,
            'Ocircumflex' => 722, 'Odieresis' => 722, 'Ograve' => 722, 'Oslash' => 722,
            'Otilde' => 722, 'P' => 556, 'Q' => 722, 'R' => 667,
            'S' => 556, 'Scaron' => 556, 'T' => 611, 'Thorn' => 556,
            'U' => 722, 'Uacute' => 722, 'Ucircumflex' => 722, 'Udieresis' => 722,
            'Ugrave' => 722, 'V' => 722, 'W' => 944, 'X' => 722,
            'Y' => 722, 'Yacute' => 722, 'Ydieresis' => 722, 'Z' => 611,
            'Zcaron' => 611, 'a' => 444, 'aacute' => 444, 'acircumflex' => 444,
            'acute' => 333, 'adieresis' => 444, 'ae' => 667, 'agrave' => 444,
            'ampersand' => 778, 'aring' => 444, 'asciicircum' => 469, 'asciitilde' => 541,
            'asterisk' => 500, 'at' => 921, 'atilde' => 444, 'b' => 500,
            'backslash' => 278, 'bar' => 200, 'braceleft' => 480, 'braceright' => 480,
            'bracketleft' => 333, 'bracketright' => 333, 'brokenbar' => 200, 'bullet' => 350,
            'c' => 444, 'ccedilla' => 444, 'cedilla' => 333, 'cent' => 500,
            'circumflex' => 333, 'colon' => 278, 'comma' => 250, 'copyright' => 760,
            'currency' => 500, 'd' => 500, 'dagger' => 500, 'daggerdbl' => 500,
            'degree' => 400, 'dieresis' => 333, 'divide' => 564, 'dollar' => 500,
            'e' => 444, 'eacute' => 444, 'ecircumflex' => 444, 'edieresis' => 444,
            'egrave' => 444, 'eight' => 500, 'ellipsis' => 1000, 'emdash' => 1000,
            'endash' => 500, 'equal' => 564, 'eth' => 500, 'exclam' => 333,
            'exclamdown' => 333, 'f' => 333, 'five' => 500, 'florin' => 500,
            'four' => 500, 'g' => 500, 'germandbls' => 500, 'grave' => 333,
            'greater' => 564, 'guillemotleft' => 500, 'guillemotright' => 500, 'guilsinglleft' => 333,
            'guilsinglright' => 333, 'h' => 500, 'hyphen' => 333, 'i' => 278,
            'iacute' => 278, 'icircumflex' => 278, 'idieresis' => 278, 'igrave' => 278,
            'j' => 278, 'k' => 500, 'l' => 278, 'less' => 564,
            'logicalnot' => 564, 'm' => 778, 'macron' => 333, 'mu' => 500,
            'multiply' => 564, 'n' => 500, 'nine' => 500, 'ntilde' => 500,
            'numbersign' => 500, 'o' => 500, 'oacute' => 500, 'ocircumflex' => 500,
            'odieresis' => 500, 'oe' => 722, 'ograve' => 500, 'one' => 500,
            'onehalf' => 750, 'onequarter' => 750, 'onesuperior' => 300, 'ordfeminine' => 276,
            'ordmasculine' => 310, 'oslash' => 500, 'otilde' => 500, 'p' => 500,
            'paragraph' => 453, 'parenleft' => 333, 'parenright' => 333, 'percent' => 833,
            'period' => 250, 'periodcentered' => 250, 'perthousand' => 1000, 'plus' => 564,
            'plusminus' => 564, 'q' => 500, 'question' => 444, 'questiondown' => 444,
            'quotedbl' => 408, 'quotedblbase' => 444, 'quotedblleft' => 444, 'quotedblright' => 444,
            'quoteleft' => 333, 'quoteright' => 333, 'quotesinglbase' => 333, 'quotesingle' => 180,
            'r' => 333, 'registered' => 760, 's' => 389, 'scaron' => 389,
            'section' => 500, 'semicolon' => 278, 'seven' => 500, 'six' => 500,
            'slash' => 278, 'space' => 250, 'sterling' => 500, 't' => 278,
            'thorn' => 500, 'three' => 500, 'threequarters' => 750, 'threesuperior' => 300,
            'tilde' => 333, 'trademark' => 980, 'two' => 500, 'twosuperior' => 300,
            'u' => 500, 'uacute' => 500, 'ucircumflex' => 500, 'udieresis' => 500,
            'ugrave' => 500, 'underscore' => 500, 'v' => 500, 'w' => 722,
            'x' => 500, 'y' => 500, 'yacute' => 500, 'ydieresis' => 500,
            'yen' => 500, 'z' => 444, 'zcaron' => 444, 'zero' => 500,
        ];
    }

    /** @return array<string, int> */
    private static function timesBoldWidths(): array
    {
        return [
            'A' => 722, 'AE' => 1000, 'Aacute' => 722, 'Acircumflex' => 722,
            'Adieresis' => 722, 'Agrave' => 722, 'Aring' => 722, 'Atilde' => 722,
            'B' => 667, 'C' => 722, 'Ccedilla' => 722, 'D' => 722,
            'E' => 667, 'Eacute' => 667, 'Ecircumflex' => 667, 'Edieresis' => 667,
            'Egrave' => 667, 'Eth' => 722, 'Euro' => 500, 'F' => 611,
            'G' => 778, 'H' => 778, 'I' => 389, 'Iacute' => 389,
            'Icircumflex' => 389, 'Idieresis' => 389, 'Igrave' => 389, 'J' => 500,
            'K' => 778, 'L' => 667, 'M' => 944, 'N' => 722,
            'Ntilde' => 722, 'O' => 778, 'OE' => 1000, 'Oacute' => 778,
            'Ocircumflex' => 778, 'Odieresis' => 778, 'Ograve' => 778, 'Oslash' => 778,
            'Otilde' => 778, 'P' => 611, 'Q' => 778, 'R' => 722,
            'S' => 556, 'Scaron' => 556, 'T' => 667, 'Thorn' => 611,
            'U' => 722, 'Uacute' => 722, 'Ucircumflex' => 722, 'Udieresis' => 722,
            'Ugrave' => 722, 'V' => 722, 'W' => 1000, 'X' => 722,
            'Y' => 722, 'Yacute' => 722, 'Ydieresis' => 722, 'Z' => 667,
            'Zcaron' => 667, 'a' => 500, 'aacute' => 500, 'acircumflex' => 500,
            'acute' => 333, 'adieresis' => 500, 'ae' => 722, 'agrave' => 500,
            'ampersand' => 833, 'aring' => 500, 'asciicircum' => 581, 'asciitilde' => 520,
            'asterisk' => 500, 'at' => 930, 'atilde' => 500, 'b' => 556,
            'backslash' => 278, 'bar' => 220, 'braceleft' => 394, 'braceright' => 394,
            'bracketleft' => 333, 'bracketright' => 333, 'brokenbar' => 220, 'bullet' => 350,
            'c' => 444, 'ccedilla' => 444, 'cedilla' => 333, 'cent' => 500,
            'circumflex' => 333, 'colon' => 333, 'comma' => 250, 'copyright' => 747,
            'currency' => 500, 'd' => 556, 'dagger' => 500, 'daggerdbl' => 500,
            'degree' => 400, 'dieresis' => 333, 'divide' => 570, 'dollar' => 500,
            'e' => 444, 'eacute' => 444, 'ecircumflex' => 444, 'edieresis' => 444,
            'egrave' => 444, 'eight' => 500, 'ellipsis' => 1000, 'emdash' => 1000,
            'endash' => 500, 'equal' => 570, 'eth' => 500, 'exclam' => 333,
            'exclamdown' => 333, 'f' => 333, 'five' => 500, 'florin' => 500,
            'four' => 500, 'g' => 500, 'germandbls' => 556, 'grave' => 333,
            'greater' => 570, 'guillemotleft' => 500, 'guillemotright' => 500, 'guilsinglleft' => 333,
            'guilsinglright' => 333, 'h' => 556, 'hyphen' => 333, 'i' => 278,
            'iacute' => 278, 'icircumflex' => 278, 'idieresis' => 278, 'igrave' => 278,
            'j' => 333, 'k' => 556, 'l' => 278, 'less' => 570,
            'logicalnot' => 570, 'm' => 833, 'macron' => 333, 'mu' => 556,
            'multiply' => 570, 'n' => 556, 'nine' => 500, 'ntilde' => 556,
            'numbersign' => 500, 'o' => 500, 'oacute' => 500, 'ocircumflex' => 500,
            'odieresis' => 500, 'oe' => 722, 'ograve' => 500, 'one' => 500,
            'onehalf' => 750, 'onequarter' => 750, 'onesuperior' => 300, 'ordfeminine' => 300,
            'ordmasculine' => 330, 'oslash' => 500, 'otilde' => 500, 'p' => 556,
            'paragraph' => 540, 'parenleft' => 333, 'parenright' => 333, 'percent' => 1000,
            'period' => 250, 'periodcentered' => 250, 'perthousand' => 1000, 'plus' => 570,
            'plusminus' => 570, 'q' => 556, 'question' => 500, 'questiondown' => 500,
            'quotedbl' => 555, 'quotedblbase' => 500, 'quotedblleft' => 500, 'quotedblright' => 500,
            'quoteleft' => 333, 'quoteright' => 333, 'quotesinglbase' => 333, 'quotesingle' => 278,
            'r' => 444, 'registered' => 747, 's' => 389, 'scaron' => 389,
            'section' => 500, 'semicolon' => 333, 'seven' => 500, 'six' => 500,
            'slash' => 278, 'space' => 250, 'sterling' => 500, 't' => 333,
            'thorn' => 556, 'three' => 500, 'threequarters' => 750, 'threesuperior' => 300,
            'tilde' => 333, 'trademark' => 1000, 'two' => 500, 'twosuperior' => 300,
            'u' => 556, 'uacute' => 556, 'ucircumflex' => 556, 'udieresis' => 556,
            'ugrave' => 556, 'underscore' => 500, 'v' => 500, 'w' => 722,
            'x' => 500, 'y' => 500, 'yacute' => 500, 'ydieresis' => 500,
            'yen' => 500, 'z' => 444, 'zcaron' => 444, 'zero' => 500,
        ];
    }

    /** @return array<string, int> */
    private static function timesItalicWidths(): array
    {
        return [
            'A' => 611, 'AE' => 889, 'Aacute' => 611, 'Acircumflex' => 611,
            'Adieresis' => 611, 'Agrave' => 611, 'Aring' => 611, 'Atilde' => 611,
            'B' => 611, 'C' => 667, 'Ccedilla' => 667, 'D' => 722,
            'E' => 611, 'Eacute' => 611, 'Ecircumflex' => 611, 'Edieresis' => 611,
            'Egrave' => 611, 'Eth' => 722, 'Euro' => 500, 'F' => 611,
            'G' => 722, 'H' => 722, 'I' => 333, 'Iacute' => 333,
            'Icircumflex' => 333, 'Idieresis' => 333, 'Igrave' => 333, 'J' => 444,
            'K' => 667, 'L' => 556, 'M' => 833, 'N' => 667,
            'Ntilde' => 667, 'O' => 722, 'OE' => 944, 'Oacute' => 722,
            'Ocircumflex' => 722, 'Odieresis' => 722, 'Ograve' => 722, 'Oslash' => 722,
            'Otilde' => 722, 'P' => 611, 'Q' => 722, 'R' => 611,
            'S' => 500, 'Scaron' => 500, 'T' => 556, 'Thorn' => 611,
            'U' => 722, 'Uacute' => 722, 'Ucircumflex' => 722, 'Udieresis' => 722,
            'Ugrave' => 722, 'V' => 611, 'W' => 833, 'X' => 611,
            'Y' => 556, 'Yacute' => 556, 'Ydieresis' => 556, 'Z' => 556,
            'Zcaron' => 556, 'a' => 500, 'aacute' => 500, 'acircumflex' => 500,
            'acute' => 333, 'adieresis' => 500, 'ae' => 667, 'agrave' => 500,
            'ampersand' => 778, 'aring' => 500, 'asciicircum' => 422, 'asciitilde' => 541,
            'asterisk' => 500, 'at' => 920, 'atilde' => 500, 'b' => 500,
            'backslash' => 278, 'bar' => 275, 'braceleft' => 400, 'braceright' => 400,
            'bracketleft' => 389, 'bracketright' => 389, 'brokenbar' => 275, 'bullet' => 350,
            'c' => 444, 'ccedilla' => 444, 'cedilla' => 333, 'cent' => 500,
            'circumflex' => 333, 'colon' => 333, 'comma' => 250, 'copyright' => 760,
            'currency' => 500, 'd' => 500, 'dagger' => 500, 'daggerdbl' => 500,
            'degree' => 400, 'dieresis' => 333, 'divide' => 675, 'dollar' => 500,
            'e' => 444, 'eacute' => 444, 'ecircumflex' => 444, 'edieresis' => 444,
            'egrave' => 444, 'eight' => 500, 'ellipsis' => 889, 'emdash' => 889,
            'endash' => 500, 'equal' => 675, 'eth' => 500, 'exclam' => 333,
            'exclamdown' => 389, 'f' => 278, 'five' => 500, 'florin' => 500,
            'four' => 500, 'g' => 500, 'germandbls' => 500, 'grave' => 333,
            'greater' => 675, 'guillemotleft' => 500, 'guillemotright' => 500, 'guilsinglleft' => 333,
            'guilsinglright' => 333, 'h' => 500, 'hyphen' => 333, 'i' => 278,
            'iacute' => 278, 'icircumflex' => 278, 'idieresis' => 278, 'igrave' => 278,
            'j' => 278, 'k' => 444, 'l' => 278, 'less' => 675,
            'logicalnot' => 675, 'm' => 722, 'macron' => 333, 'mu' => 500,
            'multiply' => 675, 'n' => 500, 'nine' => 500, 'ntilde' => 500,
            'numbersign' => 500, 'o' => 500, 'oacute' => 500, 'ocircumflex' => 500,
            'odieresis' => 500, 'oe' => 667, 'ograve' => 500, 'one' => 500,
            'onehalf' => 750, 'onequarter' => 750, 'onesuperior' => 300, 'ordfeminine' => 276,
            'ordmasculine' => 310, 'oslash' => 500, 'otilde' => 500, 'p' => 500,
            'paragraph' => 523, 'parenleft' => 333, 'parenright' => 333, 'percent' => 833,
            'period' => 250, 'periodcentered' => 250, 'perthousand' => 1000, 'plus' => 675,
            'plusminus' => 675, 'q' => 500, 'question' => 500, 'questiondown' => 500,
            'quotedbl' => 420, 'quotedblbase' => 556, 'quotedblleft' => 556, 'quotedblright' => 556,
            'quoteleft' => 333, 'quoteright' => 333, 'quotesinglbase' => 333, 'quotesingle' => 214,
            'r' => 389, 'registered' => 760, 's' => 389, 'scaron' => 389,
            'section' => 500, 'semicolon' => 333, 'seven' => 500, 'six' => 500,
            'slash' => 278, 'space' => 250, 'sterling' => 500, 't' => 278,
            'thorn' => 500, 'three' => 500, 'threequarters' => 750, 'threesuperior' => 300,
            'tilde' => 333, 'trademark' => 980, 'two' => 500, 'twosuperior' => 300,
            'u' => 500, 'uacute' => 500, 'ucircumflex' => 500, 'udieresis' => 500,
            'ugrave' => 500, 'underscore' => 500, 'v' => 444, 'w' => 667,
            'x' => 444, 'y' => 444, 'yacute' => 444, 'ydieresis' => 444,
            'yen' => 500, 'z' => 389, 'zcaron' => 389, 'zero' => 500,
        ];
    }

    /** @return array<string, int> */
    private static function timesBoldItalicWidths(): array
    {
        return [
            'A' => 667, 'AE' => 944, 'Aacute' => 667, 'Acircumflex' => 667,
            'Adieresis' => 667, 'Agrave' => 667, 'Aring' => 667, 'Atilde' => 667,
            'B' => 667, 'C' => 667, 'Ccedilla' => 667, 'D' => 722,
            'E' => 667, 'Eacute' => 667, 'Ecircumflex' => 667, 'Edieresis' => 667,
            'Egrave' => 667, 'Eth' => 722, 'Euro' => 500, 'F' => 667,
            'G' => 722, 'H' => 778, 'I' => 389, 'Iacute' => 389,
            'Icircumflex' => 389, 'Idieresis' => 389, 'Igrave' => 389, 'J' => 500,
            'K' => 667, 'L' => 611, 'M' => 889, 'N' => 722,
            'Ntilde' => 722, 'O' => 722, 'OE' => 944, 'Oacute' => 722,
            'Ocircumflex' => 722, 'Odieresis' => 722, 'Ograve' => 722, 'Oslash' => 722,
            'Otilde' => 722, 'P' => 611, 'Q' => 722, 'R' => 667,
            'S' => 556, 'Scaron' => 556, 'T' => 611, 'Thorn' => 611,
            'U' => 722, 'Uacute' => 722, 'Ucircumflex' => 722, 'Udieresis' => 722,
            'Ugrave' => 722, 'V' => 667, 'W' => 889, 'X' => 667,
            'Y' => 611, 'Yacute' => 611, 'Ydieresis' => 611, 'Z' => 611,
            'Zcaron' => 611, 'a' => 500, 'aacute' => 500, 'acircumflex' => 500,
            'acute' => 333, 'adieresis' => 500, 'ae' => 722, 'agrave' => 500,
            'ampersand' => 778, 'aring' => 500, 'asciicircum' => 570, 'asciitilde' => 570,
            'asterisk' => 500, 'at' => 832, 'atilde' => 500, 'b' => 500,
            'backslash' => 278, 'bar' => 220, 'braceleft' => 348, 'braceright' => 348,
            'bracketleft' => 333, 'bracketright' => 333, 'brokenbar' => 220, 'bullet' => 350,
            'c' => 444, 'ccedilla' => 444, 'cedilla' => 333, 'cent' => 500,
            'circumflex' => 333, 'colon' => 333, 'comma' => 250, 'copyright' => 747,
            'currency' => 500, 'd' => 500, 'dagger' => 500, 'daggerdbl' => 500,
            'degree' => 400, 'dieresis' => 333, 'divide' => 570, 'dollar' => 500,
            'e' => 444, 'eacute' => 444, 'ecircumflex' => 444, 'edieresis' => 444,
            'egrave' => 444, 'eight' => 500, 'ellipsis' => 1000, 'emdash' => 1000,
            'endash' => 500, 'equal' => 570, 'eth' => 500, 'exclam' => 389,
            'exclamdown' => 389, 'f' => 333, 'five' => 500, 'florin' => 500,
            'four' => 500, 'g' => 500, 'germandbls' => 500, 'grave' => 333,
            'greater' => 570, 'guillemotleft' => 500, 'guillemotright' => 500, 'guilsinglleft' => 333,
            'guilsinglright' => 333, 'h' => 556, 'hyphen' => 333, 'i' => 278,
            'iacute' => 278, 'icircumflex' => 278, 'idieresis' => 278, 'igrave' => 278,
            'j' => 278, 'k' => 500, 'l' => 278, 'less' => 570,
            'logicalnot' => 606, 'm' => 778, 'macron' => 333, 'mu' => 576,
            'multiply' => 570, 'n' => 556, 'nine' => 500, 'ntilde' => 556,
            'numbersign' => 500, 'o' => 500, 'oacute' => 500, 'ocircumflex' => 500,
            'odieresis' => 500, 'oe' => 722, 'ograve' => 500, 'one' => 500,
            'onehalf' => 750, 'onequarter' => 750, 'onesuperior' => 300, 'ordfeminine' => 266,
            'ordmasculine' => 300, 'oslash' => 500, 'otilde' => 500, 'p' => 500,
            'paragraph' => 500, 'parenleft' => 333, 'parenright' => 333, 'percent' => 833,
            'period' => 250, 'periodcentered' => 250, 'perthousand' => 1000, 'plus' => 570,
            'plusminus' => 570, 'q' => 500, 'question' => 500, 'questiondown' => 500,
            'quotedbl' => 555, 'quotedblbase' => 500, 'quotedblleft' => 500, 'quotedblright' => 500,
            'quoteleft' => 333, 'quoteright' => 333, 'quotesinglbase' => 333, 'quotesingle' => 278,
            'r' => 389, 'registered' => 747, 's' => 389, 'scaron' => 389,
            'section' => 500, 'semicolon' => 333, 'seven' => 500, 'six' => 500,
            'slash' => 278, 'space' => 250, 'sterling' => 500, 't' => 278,
            'thorn' => 500, 'three' => 500, 'threequarters' => 750, 'threesuperior' => 300,
            'tilde' => 333, 'trademark' => 1000, 'two' => 500, 'twosuperior' => 300,
            'u' => 556, 'uacute' => 556, 'ucircumflex' => 556, 'udieresis' => 556,
            'ugrave' => 556, 'underscore' => 500, 'v' => 444, 'w' => 667,
            'x' => 500, 'y' => 444, 'yacute' => 444, 'ydieresis' => 444,
            'yen' => 500, 'z' => 389, 'zcaron' => 389, 'zero' => 500,
        ];
    }

    /** @return array<string, int> */
    private static function courierWidths(): array
    {
        // Every WinAnsi glyph in the Courier family is 600 units wide.
        $widths = [];
        foreach (self::courierGlyphs() as $glyph) {
            $widths[$glyph] = 600;
        }
        return $widths;
    }

    /** @return list<string> */
    private static function courierGlyphs(): array
    {
        return [
            'A', 'AE', 'Aacute', 'Acircumflex', 'Adieresis', 'Agrave',
            'Aring', 'Atilde', 'B', 'C', 'Ccedilla', 'D',
            'E', 'Eacute', 'Ecircumflex', 'Edieresis', 'Egrave', 'Eth',
            'Euro', 'F', 'G', 'H', 'I', 'Iacute',
            'Icircumflex', 'Idieresis', 'Igrave', 'J', 'K', 'L',
            'M', 'N', 'Ntilde', 'O', 'OE', 'Oacute',
            'Ocircumflex', 'Odieresis', 'Ograve', 'Oslash', 'Otilde', 'P',
            'Q', 'R', 'S', 'Scaron', 'T', 'Thorn',
            'U', 'Uacute', 'Ucircumflex', 'Udieresis', 'Ugrave', 'V',
            'W', 'X', 'Y', 'Yacute', 'Ydieresis', 'Z',
            'Zcaron', 'a', 'aacute', 'acircumflex', 'acute', 'adieresis',
            'ae', 'agrave', 'ampersand', 'aring', 'asciicircum', 'asciitilde',
            'asterisk', 'at', 'atilde', 'b', 'backslash', 'bar',
            'braceleft', 'braceright', 'bracketleft', 'bracketright', 'brokenbar', 'bullet',
            'c', 'ccedilla', 'cedilla', 'cent', 'circumflex', 'colon',
            'comma', 'copyright', 'currency', 'd', 'dagger', 'daggerdbl',
            'degree', 'dieresis', 'divide', 'dollar', 'e', 'eacute',
            'ecircumflex', 'edieresis', 'egrave', 'eight', 'ellipsis', 'emdash',
            'endash', 'equal', 'eth', 'exclam', 'exclamdown', 'f',
            'five', 'florin', 'four', 'g', 'germandbls', 'grave',
            'greater', 'guillemotleft', 'guillemotright', 'guilsinglleft', 'guilsinglright', 'h',
            'hyphen', 'i', 'iacute', 'icircumflex', 'idieresis', 'igrave',
            'j', 'k', 'l', 'less', 'logicalnot', 'm',
            'macron', 'mu', 'multiply', 'n', 'nine', 'ntilde',
            'numbersign', 'o', 'oacute', 'ocircumflex', 'odieresis', 'oe',
            'ograve', 'one', 'onehalf', 'onequarter', 'onesuperior', 'ordfeminine',
            'ordmasculine', 'oslash', 'otilde', 'p', 'paragraph', 'parenleft',
            'parenright', 'percent', 'period', 'periodcentered', 'perthousand', 'plus',
            'plusminus', 'q', 'question', 'questiondown', 'quotedbl', 'quotedblbase',
            'quotedblleft', 'quotedblright', 'quoteleft', 'quoteright', 'quotesinglbase', 'quotesingle',
            'r', 'registered', 's', 'scaron', 'section', 'semicolon',
            'seven', 'six', 'slash', 'space', 'sterling', 't',
            'thorn', 'three', 'threequarters', 'threesuperior', 'tilde', 'trademark',
            'two', 'twosuperior', 'u', 'uacute', 'ucircumflex', 'udieresis',
            'ugrave', 'underscore', 'v', 'w', 'x', 'y',
            'yacute', 'ydieresis', 'yen', 'z', 'zcaron', 'zero',
        ];
    }

    /** @return array<string, int> */
    private static function symbolWidths(): array
    {
        return [
            'space' => 250, 'exclam' => 333, 'universal' => 713, 'numbersign' => 500,
            'existential' => 549, 'percent' => 833, 'ampersand' => 778, 'suchthat' => 439,
            'parenleft' => 333, 'parenright' => 333, 'asteriskmath' => 500, 'plus' => 549,
            'comma' => 250, 'minus' => 549, 'period' => 250, 'slash' => 278,
            'zero' => 500, 'one' => 500, 'two' => 500, 'three' => 500,
            'four' => 500, 'five' => 500, 'six' => 500, 'seven' => 500,
            'eight' => 500, 'nine' => 500,
            'colon' => 278, 'semicolon' => 278, 'less' => 549, 'equal' => 549,
            'greater' => 549, 'question' => 444, 'congruent' => 549,
            'Alpha' => 722, 'Beta' => 667, 'Chi' => 667, 'Delta' => 612,
            'Epsilon' => 611, 'Phi' => 763, 'Gamma' => 603, 'Eta' => 722,
            'Iota' => 333, 'theta1' => 631, 'Kappa' => 722, 'Lambda' => 686,
            'Mu' => 889, 'Nu' => 722, 'Omicron' => 722, 'Pi' => 768,
            'Theta' => 741, 'Rho' => 556, 'Sigma' => 592, 'Tau' => 611,
            'Upsilon' => 690, 'sigma1' => 439, 'Omega' => 768, 'Xi' => 645,
            'Psi' => 795, 'Zeta' => 611,
            'bracketleft' => 333, 'therefore' => 863, 'bracketright' => 333,
            'perpendicular' => 658, 'underscore' => 500, 'radicalex' => 500,
            'alpha' => 631, 'beta' => 549, 'chi' => 549, 'delta' => 494,
            'epsilon' => 439, 'phi' => 521, 'gamma' => 411, 'eta' => 603,
            'iota' => 329, 'phi1' => 603, 'kappa' => 549, 'lambda' => 549,
            'mu' => 576, 'nu' => 521, 'omicron' => 549, 'pi' => 549,
            'theta' => 521, 'rho' => 549, 'sigma' => 603, 'tau' => 439,
            'upsilon' => 576, 'omega1' => 713, 'omega' => 686, 'xi' => 493,
            'psi' => 686, 'zeta' => 494,
            'braceleft' => 480, 'bar' => 200, 'braceright' => 480, 'similar' => 549,
        ];
    }

    /** @return array<string, int> */
    private static function zapfDingbatsWidths(): array
    {
        return [
            'space' => 278,
            'a1' => 974, 'a2' => 961, 'a202' => 974, 'a3' => 980, 'a4' => 719,
            'a5' => 789, 'a119' => 790, 'a118' => 791, 'a117' => 690, 'a11' => 960,
            'a12' => 939, 'a13' => 549, 'a14' => 855, 'a15' => 911, 'a16' => 933,
            'a105' => 911, 'a17' => 945, 'a18' => 974, 'a19' => 755, 'a20' => 846,
            'a21' => 762, 'a22' => 761, 'a23' => 571, 'a24' => 677, 'a25' => 763,
            'a26' => 760, 'a27' => 759, 'a28' => 754, 'a6' => 494, 'a7' => 552,
            'a8' => 537, 'a9' => 577, 'a10' => 692, 'a29' => 786, 'a30' => 788,
            'a31' => 788, 'a32' => 790, 'a33' => 793, 'a34' => 794, 'a35' => 816,
            'a36' => 823, 'a37' => 789, 'a38' => 841, 'a39' => 823, 'a40' => 833,
            'a41' => 816, 'a42' => 831, 'a43' => 923, 'a44' => 744, 'a45' => 723,
            'a46' => 749, 'a47' => 790, 'a48' => 792, 'a49' => 695, 'a50' => 776,
            'a51' => 768, 'a52' => 792, 'a53' => 759, 'a54' => 707, 'a55' => 708,
            'a56' => 682, 'a57' => 701, 'a58' => 826, 'a59' => 815, 'a60' => 789,
            'a61' => 789, 'a62' => 707, 'a63' => 687, 'a64' => 696, 'a65' => 689,
            'a66' => 786, 'a67' => 787, 'a68' => 713, 'a69' => 791, 'a70' => 785,
            'a71' => 791, 'a72' => 873, 'a73' => 761, 'a74' => 762, 'a203' => 762,
            'a75' => 759, 'a204' => 759, 'a76' => 892, 'a77' => 892, 'a78' => 788,
            'a79' => 784, 'a81' => 438, 'a82' => 138, 'a83' => 277, 'a84' => 415,
            'a97' => 392, 'a98' => 392, 'a99' => 668, 'a100' => 668,
        ];
    }
}
