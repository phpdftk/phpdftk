<?php

declare(strict_types=1);

namespace Phpdftk\Encoding;

/**
 * StandardEncoding — the default encoding for Type 1 fonts when no /Encoding is specified.
 * Per PDF spec ISO 32000-2:2020, Table D.1 / PostScript Language Reference, Appendix E.
 */
final class StandardEncodingTable
{
    /** @return array<int, string> byte value (0-255) to PostScript glyph name */
    public static function getTable(): array
    {
        $notdef = '.notdef';
        $table = array_fill(0, 256, $notdef);

        // 32-126: standard printable ASCII (with StandardEncoding-specific differences)
        $table[32]  = 'space';
        $table[33]  = 'exclam';
        $table[34]  = 'quotedbl';
        $table[35]  = 'numbersign';
        $table[36]  = 'dollar';
        $table[37]  = 'percent';
        $table[38]  = 'ampersand';
        $table[39]  = 'quoteright';
        $table[40]  = 'parenleft';
        $table[41]  = 'parenright';
        $table[42]  = 'asterisk';
        $table[43]  = 'plus';
        $table[44]  = 'comma';
        $table[45]  = 'hyphen';
        $table[46]  = 'period';
        $table[47]  = 'slash';
        $table[48]  = 'zero';
        $table[49]  = 'one';
        $table[50]  = 'two';
        $table[51]  = 'three';
        $table[52]  = 'four';
        $table[53]  = 'five';
        $table[54]  = 'six';
        $table[55]  = 'seven';
        $table[56]  = 'eight';
        $table[57]  = 'nine';
        $table[58]  = 'colon';
        $table[59]  = 'semicolon';
        $table[60]  = 'less';
        $table[61]  = 'equal';
        $table[62]  = 'greater';
        $table[63]  = 'question';
        $table[64]  = 'at';
        $table[65]  = 'A';
        $table[66]  = 'B';
        $table[67]  = 'C';
        $table[68]  = 'D';
        $table[69]  = 'E';
        $table[70]  = 'F';
        $table[71]  = 'G';
        $table[72]  = 'H';
        $table[73]  = 'I';
        $table[74]  = 'J';
        $table[75]  = 'K';
        $table[76]  = 'L';
        $table[77]  = 'M';
        $table[78]  = 'N';
        $table[79]  = 'O';
        $table[80]  = 'P';
        $table[81]  = 'Q';
        $table[82]  = 'R';
        $table[83]  = 'S';
        $table[84]  = 'T';
        $table[85]  = 'U';
        $table[86]  = 'V';
        $table[87]  = 'W';
        $table[88]  = 'X';
        $table[89]  = 'Y';
        $table[90]  = 'Z';
        $table[91]  = 'bracketleft';
        $table[92]  = 'backslash';
        $table[93]  = 'bracketright';
        $table[94]  = 'asciicircum';
        $table[95]  = 'underscore';
        $table[96]  = 'quoteleft';
        $table[97]  = 'a';
        $table[98]  = 'b';
        $table[99]  = 'c';
        $table[100] = 'd';
        $table[101] = 'e';
        $table[102] = 'f';
        $table[103] = 'g';
        $table[104] = 'h';
        $table[105] = 'i';
        $table[106] = 'j';
        $table[107] = 'k';
        $table[108] = 'l';
        $table[109] = 'm';
        $table[110] = 'n';
        $table[111] = 'o';
        $table[112] = 'p';
        $table[113] = 'q';
        $table[114] = 'r';
        $table[115] = 's';
        $table[116] = 't';
        $table[117] = 'u';
        $table[118] = 'v';
        $table[119] = 'w';
        $table[120] = 'x';
        $table[121] = 'y';
        $table[122] = 'z';
        $table[123] = 'braceleft';
        $table[124] = 'bar';
        $table[125] = 'braceright';
        $table[126] = 'asciitilde';

        // 128-255: StandardEncoding high bytes
        $table[161] = 'exclamdown';
        $table[162] = 'cent';
        $table[163] = 'sterling';
        $table[164] = 'fraction';
        $table[165] = 'yen';
        $table[166] = 'florin';
        $table[167] = 'section';
        $table[168] = 'currency';
        $table[169] = 'quotesingle';
        $table[170] = 'quotedblleft';
        $table[171] = 'guillemotleft';
        $table[172] = 'guilsinglleft';
        $table[173] = 'guilsinglright';
        $table[174] = 'fi';
        $table[175] = 'fl';
        $table[177] = 'endash';
        $table[178] = 'dagger';
        $table[179] = 'daggerdbl';
        $table[180] = 'periodcentered';
        $table[182] = 'paragraph';
        $table[183] = 'bullet';
        $table[184] = 'quotesinglbase';
        $table[185] = 'quotedblbase';
        $table[186] = 'quotedblright';
        $table[187] = 'guillemotright';
        $table[188] = 'ellipsis';
        $table[189] = 'perthousand';
        $table[191] = 'questiondown';
        $table[193] = 'grave';
        $table[194] = 'acute';
        $table[195] = 'circumflex';
        $table[196] = 'tilde';
        $table[197] = 'macron';
        $table[198] = 'breve';
        $table[199] = 'dotaccent';
        $table[200] = 'dieresis';
        $table[202] = 'ring';
        $table[203] = 'cedilla';
        $table[205] = 'hungarumlaut';
        $table[206] = 'ogonek';
        $table[207] = 'caron';
        $table[208] = 'emdash';
        $table[225] = 'AE';
        $table[227] = 'ordfeminine';
        $table[232] = 'Lslash';
        $table[233] = 'Oslash';
        $table[234] = 'OE';
        $table[235] = 'ordmasculine';
        $table[241] = 'ae';
        $table[245] = 'dotlessi';
        $table[248] = 'lslash';
        $table[249] = 'oslash';
        $table[250] = 'oe';
        $table[251] = 'germandbls';

        return $table;
    }
}
