<?php declare(strict_types=1);
namespace Phpdftk\Encoding;

/**
 * MacExpertEncoding — used by expert/small-caps Type 1 fonts on Mac.
 * Per PDF spec ISO 32000-2:2020, Table D.4.
 */
final class MacExpertEncodingTable {
    /** @return array<int, string> byte value (0-255) to PostScript glyph name */
    public static function getTable(): array {
        $notdef = '.notdef';
        $table = array_fill(0, 256, $notdef);

        $table[202] = 'space';
        // Ligatures and fractions
        $table[242] = 'fi';
        $table[243] = 'fl';

        // Small caps and expert glyphs
        $table[276 - 256] = $notdef; // keep notdef for unmapped

        // Per the PostScript Language Reference / PDF spec Table D.4
        $table[207] = 'Acircumflexsmall'; // N/A in some implementations; included per spec
        $table[171] = 'Aacutesmall';
        $table[198] = 'ACsmall'; // non-standard name; some fonts use this

        // Full mapping per Table D.4
        $table[202] = 'space';
        $table[244] = 'exclamsmall';
        $table[245] = 'Hungarumlautsmall';
        $table[246] = 'dollaroldstyle';
        $table[247] = 'dollarsuperior';
        $table[248] = 'ampersandsmall';
        $table[249] = 'Acutesmall';
        $table[250] = 'parenleftsuperior';
        $table[251] = 'parenrightsuperior';
        $table[252] = 'twodotenleader';
        $table[253] = 'onedotenleader';
        $table[254] = 'comma';
        $table[255] = 'hyphen';
        $table[46]  = 'period';
        $table[47]  = 'fraction';
        $table[48]  = 'zerooldstyle';
        $table[49]  = 'oneoldstyle';
        $table[50]  = 'twooldstyle';
        $table[51]  = 'threeoldstyle';
        $table[52]  = 'fouroldstyle';
        $table[53]  = 'fiveoldstyle';
        $table[54]  = 'sixoldstyle';
        $table[55]  = 'sevenoldstyle';
        $table[56]  = 'eightoldstyle';
        $table[57]  = 'nineoldstyle';
        $table[58]  = 'colon';
        $table[59]  = 'semicolon';
        $table[60]  = 'commasuperior';
        $table[61]  = 'threequartersemdash';
        $table[62]  = 'periodsuperior';
        $table[63]  = 'questionsmall';
        $table[65]  = 'asuperior';
        $table[66]  = 'bsuperior';
        $table[67]  = 'centsuperior';
        $table[68]  = 'dsuperior';
        $table[69]  = 'esuperior';
        $table[73]  = 'isuperior';
        $table[76]  = 'lsuperior';
        $table[77]  = 'msuperior';
        $table[78]  = 'nsuperior';
        $table[79]  = 'osuperior';
        $table[82]  = 'rsuperior';
        $table[83]  = 'ssuperior';
        $table[84]  = 'tsuperior';
        $table[86]  = 'ff';
        $table[87]  = 'ffi';
        $table[88]  = 'ffl';
        $table[89]  = 'parenleftinferior';
        $table[90]  = 'parenrightinferior';
        $table[91]  = 'Circumflexsmall';
        $table[92]  = 'hyphensuperior';
        $table[93]  = 'Gravesmall';
        $table[94]  = 'Asmall';
        $table[95]  = 'Bsmall';
        $table[96]  = 'Csmall';
        $table[97]  = 'Dsmall';
        $table[98]  = 'Esmall';
        $table[99]  = 'Fsmall';
        $table[100] = 'Gsmall';
        $table[101] = 'Hsmall';
        $table[102] = 'Ismall';
        $table[103] = 'Jsmall';
        $table[104] = 'Ksmall';
        $table[105] = 'Lsmall';
        $table[106] = 'Msmall';
        $table[107] = 'Nsmall';
        $table[108] = 'Osmall';
        $table[109] = 'Psmall';
        $table[110] = 'Qsmall';
        $table[111] = 'Rsmall';
        $table[112] = 'Ssmall';
        $table[113] = 'Tsmall';
        $table[114] = 'Usmall';
        $table[115] = 'Vsmall';
        $table[116] = 'Wsmall';
        $table[117] = 'Xsmall';
        $table[118] = 'Ysmall';
        $table[119] = 'Zsmall';
        $table[120] = 'colonmonetary';
        $table[121] = 'onefitted';
        $table[122] = 'rupiah';
        $table[123] = 'Tildesmall';
        $table[125] = 'centoldstyle';
        $table[126] = 'figuredash';
        $table[128] = 'hypheninferior';
        $table[129] = 'Ogoneksmall';
        $table[130] = 'Ringsmall';
        $table[131] = 'Cedillasmall';
        $table[133] = 'onequarter';
        $table[134] = 'onehalf';
        $table[135] = 'threequarters';
        $table[136] = 'oneeighth';
        $table[137] = 'threeeighths';
        $table[138] = 'fiveeighths';
        $table[139] = 'seveneighths';
        $table[140] = 'onethird';
        $table[141] = 'twothirds';
        $table[143] = 'zerosuperior';
        $table[144] = 'onesuperior';
        $table[145] = 'twosuperior';
        $table[146] = 'threesuperior';
        $table[147] = 'foursuperior';
        $table[148] = 'fivesuperior';
        $table[149] = 'sixsuperior';
        $table[150] = 'sevensuperior';
        $table[151] = 'eightsuperior';
        $table[152] = 'ninesuperior';
        $table[153] = 'zeroinferior';
        $table[154] = 'oneinferior';
        $table[155] = 'twoinferior';
        $table[156] = 'threeinferior';
        $table[157] = 'fourinferior';
        $table[158] = 'fiveinferior';
        $table[159] = 'sixinferior';
        $table[160] = 'seveninferior';
        $table[161] = 'eightinferior';
        $table[162] = 'nineinferior';
        $table[163] = 'centinferior';
        $table[164] = 'dollarinferior';
        $table[165] = 'periodinferior';
        $table[166] = 'commainferior';
        $table[167] = 'Agravesmall';
        $table[168] = 'Aacutesmall';
        $table[169] = 'Acircumflexsmall';
        $table[170] = 'Atildesmall';
        $table[171] = 'Adieresissmall';
        $table[172] = 'Aringsmall';
        $table[173] = 'AEsmall';
        $table[174] = 'Ccedillasmall';
        $table[175] = 'Egravesmall';
        $table[176] = 'Eacutesmall';
        $table[177] = 'Ecircumflexsmall';
        $table[178] = 'Edieresissmall';
        $table[179] = 'Igravesmall';
        $table[180] = 'Iacutesmall';
        $table[181] = 'Icircumflexsmall';
        $table[182] = 'Idieresissmall';
        $table[183] = 'Ethsmall';
        $table[184] = 'Ntildesmall';
        $table[185] = 'Ogravesmall';
        $table[186] = 'Oacutesmall';
        $table[187] = 'Ocircumflexsmall';
        $table[188] = 'Otildesmall';
        $table[189] = 'Odieresissmall';
        $table[190] = 'OEsmall';
        $table[191] = 'Oslashsmall';
        $table[192] = 'Ugravesmall';
        $table[193] = 'Uacutesmall';
        $table[194] = 'Ucircumflexsmall';
        $table[195] = 'Udieresissmall';
        $table[196] = 'Yacutesmall';
        $table[197] = 'Thornsmall';
        $table[198] = 'Ydieresissmall';
        $table[33]  = 'exclamdownsmall';
        $table[34]  = 'centsmall'; // non-standard; some implementations differ
        $table[36]  = 'dollarsmall'; // non-standard; some implementations differ
        $table[199] = 'sixoldstyle'; // duplicate remapped
        $table[200] = 'sevenoldstyle'; // duplicate remapped
        $table[201] = 'eightoldstyle'; // duplicate remapped
        $table[203] = 'exclamsmall'; // duplicate
        $table[204] = 'Dieresissmall';
        $table[206] = 'Brevesmall';
        $table[207] = 'Caronsmall';
        $table[209] = 'Dotaccentsmall';
        $table[211] = 'Macronsmall';
        $table[214] = 'figuredash'; // duplicate
        $table[218] = 'Scaronsmall';
        $table[223] = 'Zcaronsmall';
        $table[224] = 'Dieresissmall'; // duplicate
        $table[225] = 'Brevesmall'; // duplicate
        $table[227] = 'Dotaccentsmall'; // duplicate
        $table[232] = 'Lslashsmall';
        $table[233] = 'Oslashsmall'; // duplicate
        $table[234] = 'OEsmall'; // duplicate
        $table[235] = 'ordmasculine';
        $table[241] = 'Scaronsmall'; // duplicate
        $table[242] = 'fi';
        $table[243] = 'fl';
        $table[37]  = 'Lslashsmall'; // non-standard position

        return $table;
    }
}
