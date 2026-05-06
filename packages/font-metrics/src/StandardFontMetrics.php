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
            'space' => 278, 'exclam' => 278, 'quotedbl' => 355, 'numbersign' => 556,
            'dollar' => 556, 'percent' => 889, 'ampersand' => 667, 'quotesingle' => 191,
            'parenleft' => 333, 'parenright' => 333, 'asterisk' => 389, 'plus' => 584,
            'comma' => 278, 'hyphen' => 333, 'period' => 278, 'slash' => 278,
            'zero' => 556, 'one' => 556, 'two' => 556, 'three' => 556,
            'four' => 556, 'five' => 556, 'six' => 556, 'seven' => 556,
            'eight' => 556, 'nine' => 556,
            'colon' => 278, 'semicolon' => 278, 'less' => 584, 'equal' => 584,
            'greater' => 584, 'question' => 556, 'at' => 1015,
            'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667,
            'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278, 'J' => 500,
            'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
            'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611,
            'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667,
            'Z' => 611,
            'bracketleft' => 278, 'backslash' => 278, 'bracketright' => 278,
            'asciicircum' => 469, 'underscore' => 556, 'grave' => 333,
            'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556,
            'f' => 278, 'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222,
            'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556,
            'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278,
            'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500,
            'z' => 500,
            'braceleft' => 334, 'bar' => 260, 'braceright' => 334, 'asciitilde' => 584,
        ];
    }

    /** @return array<string, int> */
    private static function helveticaBoldWidths(): array
    {
        return [
            'space' => 278, 'exclam' => 333, 'quotedbl' => 474, 'numbersign' => 556,
            'dollar' => 556, 'percent' => 889, 'ampersand' => 722, 'quotesingle' => 238,
            'parenleft' => 333, 'parenright' => 333, 'asterisk' => 389, 'plus' => 584,
            'comma' => 278, 'hyphen' => 333, 'period' => 278, 'slash' => 278,
            'zero' => 556, 'one' => 556, 'two' => 556, 'three' => 556,
            'four' => 556, 'five' => 556, 'six' => 556, 'seven' => 556,
            'eight' => 556, 'nine' => 556,
            'colon' => 333, 'semicolon' => 333, 'less' => 584, 'equal' => 584,
            'greater' => 584, 'question' => 611, 'at' => 975,
            'A' => 722, 'B' => 722, 'C' => 722, 'D' => 722, 'E' => 667,
            'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278, 'J' => 556,
            'K' => 722, 'L' => 611, 'M' => 833, 'N' => 722, 'O' => 778,
            'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611,
            'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667,
            'Z' => 611,
            'a' => 556, 'b' => 611, 'c' => 556, 'd' => 611, 'e' => 556,
            'f' => 333, 'g' => 611, 'h' => 611, 'i' => 278, 'j' => 278,
            'k' => 556, 'l' => 278, 'm' => 889, 'n' => 611, 'o' => 611,
            'p' => 611, 'q' => 611, 'r' => 389, 's' => 556, 't' => 333,
            'u' => 611, 'v' => 556, 'w' => 778, 'x' => 556, 'y' => 556,
            'z' => 500,
        ];
    }

    /** @return array<string, int> */
    private static function timesRomanWidths(): array
    {
        return [
            'space' => 250, 'exclam' => 333, 'quotedbl' => 408, 'numbersign' => 500,
            'dollar' => 500, 'percent' => 833, 'ampersand' => 778, 'quotesingle' => 180,
            'parenleft' => 333, 'parenright' => 333, 'asterisk' => 500, 'plus' => 564,
            'comma' => 250, 'hyphen' => 333, 'period' => 250, 'slash' => 278,
            'zero' => 500, 'one' => 500, 'two' => 500, 'three' => 500,
            'four' => 500, 'five' => 500, 'six' => 500, 'seven' => 500,
            'eight' => 500, 'nine' => 500,
            'colon' => 278, 'semicolon' => 278, 'less' => 564, 'equal' => 564,
            'greater' => 564, 'question' => 444, 'at' => 921,
            'A' => 722, 'B' => 667, 'C' => 667, 'D' => 722, 'E' => 611,
            'F' => 556, 'G' => 722, 'H' => 722, 'I' => 333, 'J' => 389,
            'K' => 722, 'L' => 611, 'M' => 889, 'N' => 722, 'O' => 722,
            'P' => 556, 'Q' => 722, 'R' => 667, 'S' => 556, 'T' => 611,
            'U' => 722, 'V' => 722, 'W' => 944, 'X' => 722, 'Y' => 722,
            'Z' => 611,
            'a' => 444, 'b' => 500, 'c' => 444, 'd' => 500, 'e' => 444,
            'f' => 278, 'g' => 500, 'h' => 500, 'i' => 278, 'j' => 278,
            'k' => 500, 'l' => 278, 'm' => 778, 'n' => 500, 'o' => 500,
            'p' => 500, 'q' => 500, 'r' => 333, 's' => 389, 't' => 278,
            'u' => 500, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500,
            'z' => 444,
        ];
    }

    /** @return array<string, int> */
    private static function timesBoldWidths(): array
    {
        return [
            'space' => 250, 'exclam' => 333, 'quotedbl' => 555, 'numbersign' => 500,
            'dollar' => 500, 'percent' => 1000, 'ampersand' => 833, 'quotesingle' => 278,
            'parenleft' => 333, 'parenright' => 333, 'asterisk' => 500, 'plus' => 570,
            'comma' => 250, 'hyphen' => 333, 'period' => 250, 'slash' => 278,
            'zero' => 500, 'one' => 500, 'two' => 500, 'three' => 500,
            'four' => 500, 'five' => 500, 'six' => 500, 'seven' => 500,
            'eight' => 500, 'nine' => 500,
            'colon' => 333, 'semicolon' => 333, 'less' => 570, 'equal' => 570,
            'greater' => 570, 'question' => 500,
            'A' => 722, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667,
            'F' => 611, 'G' => 778, 'H' => 778, 'I' => 389, 'J' => 500,
            'K' => 778, 'L' => 667, 'M' => 944, 'N' => 722, 'O' => 778,
            'P' => 611, 'Q' => 778, 'R' => 722, 'S' => 556, 'T' => 667,
            'U' => 722, 'V' => 722, 'W' => 1000, 'X' => 722, 'Y' => 722,
            'Z' => 667,
            'a' => 500, 'b' => 556, 'c' => 444, 'd' => 556, 'e' => 444,
            'f' => 333, 'g' => 500, 'h' => 556, 'i' => 278, 'j' => 333,
            'k' => 556, 'l' => 278, 'm' => 833, 'n' => 556, 'o' => 500,
            'p' => 556, 'q' => 556, 'r' => 444, 's' => 389, 't' => 333,
            'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500,
            'z' => 444,
        ];
    }

    /** @return array<string, int> */
    private static function timesItalicWidths(): array
    {
        return [
            'space' => 250, 'exclam' => 333, 'quotedbl' => 420, 'numbersign' => 500,
            'dollar' => 500, 'percent' => 833, 'ampersand' => 778, 'quotesingle' => 214,
            'parenleft' => 333, 'parenright' => 333, 'asterisk' => 500, 'plus' => 675,
            'comma' => 250, 'hyphen' => 333, 'period' => 250, 'slash' => 278,
            'zero' => 500, 'one' => 500, 'two' => 500, 'three' => 500,
            'four' => 500, 'five' => 500, 'six' => 500, 'seven' => 500,
            'eight' => 500, 'nine' => 500,
            'colon' => 333, 'semicolon' => 333,
            'A' => 611, 'B' => 611, 'C' => 667, 'D' => 722, 'E' => 611,
            'F' => 611, 'G' => 722, 'H' => 722, 'I' => 333, 'J' => 444,
            'K' => 667, 'L' => 556, 'M' => 833, 'N' => 667, 'O' => 722,
            'P' => 611, 'Q' => 722, 'R' => 611, 'S' => 500, 'T' => 556,
            'U' => 722, 'V' => 611, 'W' => 833, 'X' => 611, 'Y' => 556,
            'Z' => 556,
            'a' => 500, 'b' => 500, 'c' => 444, 'd' => 500, 'e' => 444,
            'f' => 278, 'g' => 500, 'h' => 500, 'i' => 278, 'j' => 278,
            'k' => 444, 'l' => 278, 'm' => 722, 'n' => 500, 'o' => 500,
            'p' => 500, 'q' => 500, 'r' => 389, 's' => 389, 't' => 278,
            'u' => 500, 'v' => 444, 'w' => 667, 'x' => 444, 'y' => 444,
            'z' => 389,
        ];
    }

    /** @return array<string, int> */
    private static function timesBoldItalicWidths(): array
    {
        return [
            'space' => 250,
            'A' => 667, 'B' => 667, 'C' => 667, 'D' => 722, 'E' => 667,
            'F' => 667, 'G' => 722, 'H' => 778, 'I' => 389, 'J' => 500,
            'K' => 667, 'L' => 611, 'M' => 889, 'N' => 722, 'O' => 722,
            'P' => 611, 'Q' => 722, 'R' => 667, 'S' => 556, 'T' => 611,
            'U' => 722, 'V' => 667, 'W' => 889, 'X' => 667, 'Y' => 611,
            'Z' => 611,
            'a' => 500, 'b' => 556, 'c' => 444, 'd' => 556, 'e' => 444,
            'f' => 333, 'g' => 500, 'h' => 556, 'i' => 278, 'j' => 278,
            'k' => 500, 'l' => 278, 'm' => 778, 'n' => 556, 'o' => 500,
            'p' => 556, 'q' => 556, 'r' => 444, 's' => 389, 't' => 333,
            'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500,
            'z' => 444,
        ];
    }

    /** @return array<string, int> */
    private static function courierWidths(): array
    {
        // All glyphs in Courier family are 600 units wide
        $widths = [];
        $glyphs = [
            'space', 'exclam', 'quotedbl', 'numbersign', 'dollar', 'percent',
            'ampersand', 'quotesingle', 'parenleft', 'parenright', 'asterisk', 'plus',
            'comma', 'hyphen', 'period', 'slash',
            'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'colon', 'semicolon', 'less', 'equal', 'greater', 'question', 'at',
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
            'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            'bracketleft', 'backslash', 'bracketright', 'asciicircum', 'underscore', 'grave',
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
            'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            'braceleft', 'bar', 'braceright', 'asciitilde',
        ];
        foreach ($glyphs as $glyph) {
            $widths[$glyph] = 600;
        }
        return $widths;
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
