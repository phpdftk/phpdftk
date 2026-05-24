<?php

declare(strict_types=1);

namespace Phpdftk\Html\Tokenizer;

/**
 * Named character references per WHATWG HTML §13.5.
 *
 * This file ships a hand-curated subset of the spec's ~2200-entry table:
 * the highest-frequency entries (legacy ASCII shortcuts, common typography,
 * Greek letters, math, currency, Latin-1 accented forms, common arrows).
 * Together they cover the overwhelming majority of named references in
 * real-world HTML.
 *
 * Full table generation: run `php scripts/generate-html-entities.php` after
 * pulling a fresh copy of https://html.spec.whatwg.org/entities.json into
 * `vendor-data/whatwg/entities.json`. The generator overwrites this file
 * with the complete ~2200-entry table.
 *
 * Matching is longest-prefix per WHATWG: the tokenizer's named-character-
 * reference state tries successive lengths and keeps the longest hit.
 */
final class NamedCharacterReferences
{
    /**
     * Map of name (without leading &, with optional trailing ;) → resolved
     * codepoint(s) as a UTF-8 string. Some names map to multi-codepoint
     * sequences (e.g. NotEqualTilde produces two codepoints).
     *
     * @var array<string, string>
     */
    public const array TABLE = [
        // === Legacy entries (with and without trailing ;) ===
        'amp;' => '&', 'amp' => '&',
        'lt;' => '<', 'lt' => '<',
        'gt;' => '>', 'gt' => '>',
        'quot;' => '"', 'quot' => '"',
        'apos;' => "'",
        'nbsp;' => "\u{00A0}", 'nbsp' => "\u{00A0}",
        'copy;' => "\u{00A9}", 'copy' => "\u{00A9}",
        'reg;' => "\u{00AE}", 'reg' => "\u{00AE}",
        'trade;' => "\u{2122}",
        // === Typography ===
        'hellip;' => "\u{2026}",
        'mdash;' => "\u{2014}", 'ndash;' => "\u{2013}",
        'lsquo;' => "\u{2018}", 'rsquo;' => "\u{2019}",
        'ldquo;' => "\u{201C}", 'rdquo;' => "\u{201D}",
        'sbquo;' => "\u{201A}", 'bdquo;' => "\u{201E}",
        'laquo;' => "\u{00AB}", 'raquo;' => "\u{00BB}",
        'bull;' => "\u{2022}",
        'middot;' => "\u{00B7}",
        'sect;' => "\u{00A7}",
        'para;' => "\u{00B6}",
        'dagger;' => "\u{2020}", 'Dagger;' => "\u{2021}",
        'permil;' => "\u{2030}",
        'prime;' => "\u{2032}", 'Prime;' => "\u{2033}",
        'lsaquo;' => "\u{2039}", 'rsaquo;' => "\u{203A}",
        'oline;' => "\u{203E}",
        'shy;' => "\u{00AD}",
        'iexcl;' => "\u{00A1}", 'iquest;' => "\u{00BF}",
        'brvbar;' => "\u{00A6}",
        'deg;' => "\u{00B0}",
        'acute;' => "\u{00B4}",
        'cedil;' => "\u{00B8}",
        'uml;' => "\u{00A8}",
        'macr;' => "\u{00AF}",
        'not;' => "\u{00AC}",
        'curren;' => "\u{00A4}",
        // === Currency ===
        'cent;' => "\u{00A2}", 'pound;' => "\u{00A3}", 'yen;' => "\u{00A5}",
        'euro;' => "\u{20AC}",
        // === Math operators ===
        'plusmn;' => "\u{00B1}", 'times;' => "\u{00D7}", 'divide;' => "\u{00F7}",
        'minus;' => "\u{2212}",
        'frac12;' => "\u{00BD}", 'frac14;' => "\u{00BC}", 'frac34;' => "\u{00BE}",
        'sup1;' => "\u{00B9}", 'sup2;' => "\u{00B2}", 'sup3;' => "\u{00B3}",
        'forall;' => "\u{2200}", 'part;' => "\u{2202}", 'exist;' => "\u{2203}",
        'empty;' => "\u{2205}", 'nabla;' => "\u{2207}",
        'isin;' => "\u{2208}", 'notin;' => "\u{2209}", 'ni;' => "\u{220B}",
        'prod;' => "\u{220F}", 'sum;' => "\u{2211}",
        'lowast;' => "\u{2217}", 'radic;' => "\u{221A}",
        'prop;' => "\u{221D}", 'infin;' => "\u{221E}",
        'ang;' => "\u{2220}", 'and;' => "\u{2227}", 'or;' => "\u{2228}",
        'cap;' => "\u{2229}", 'cup;' => "\u{222A}", 'int;' => "\u{222B}",
        'there4;' => "\u{2234}",
        'sim;' => "\u{223C}", 'cong;' => "\u{2245}", 'asymp;' => "\u{2248}",
        'ne;' => "\u{2260}", 'equiv;' => "\u{2261}",
        'le;' => "\u{2264}", 'ge;' => "\u{2265}",
        'sub;' => "\u{2282}", 'sup;' => "\u{2283}",
        'nsub;' => "\u{2284}", 'sube;' => "\u{2286}", 'supe;' => "\u{2287}",
        'oplus;' => "\u{2295}", 'otimes;' => "\u{2297}", 'perp;' => "\u{22A5}",
        'sdot;' => "\u{22C5}",
        // === Greek letters ===
        'Alpha;' => "\u{0391}", 'Beta;' => "\u{0392}", 'Gamma;' => "\u{0393}",
        'Delta;' => "\u{0394}", 'Epsilon;' => "\u{0395}", 'Zeta;' => "\u{0396}",
        'Eta;' => "\u{0397}", 'Theta;' => "\u{0398}", 'Iota;' => "\u{0399}",
        'Kappa;' => "\u{039A}", 'Lambda;' => "\u{039B}", 'Mu;' => "\u{039C}",
        'Nu;' => "\u{039D}", 'Xi;' => "\u{039E}", 'Omicron;' => "\u{039F}",
        'Pi;' => "\u{03A0}", 'Rho;' => "\u{03A1}", 'Sigma;' => "\u{03A3}",
        'Tau;' => "\u{03A4}", 'Upsilon;' => "\u{03A5}", 'Phi;' => "\u{03A6}",
        'Chi;' => "\u{03A7}", 'Psi;' => "\u{03A8}", 'Omega;' => "\u{03A9}",
        'alpha;' => "\u{03B1}", 'beta;' => "\u{03B2}", 'gamma;' => "\u{03B3}",
        'delta;' => "\u{03B4}", 'epsilon;' => "\u{03B5}", 'zeta;' => "\u{03B6}",
        'eta;' => "\u{03B7}", 'theta;' => "\u{03B8}", 'iota;' => "\u{03B9}",
        'kappa;' => "\u{03BA}", 'lambda;' => "\u{03BB}", 'mu;' => "\u{03BC}",
        'nu;' => "\u{03BD}", 'xi;' => "\u{03BE}", 'omicron;' => "\u{03BF}",
        'pi;' => "\u{03C0}", 'rho;' => "\u{03C1}", 'sigmaf;' => "\u{03C2}",
        'sigma;' => "\u{03C3}", 'tau;' => "\u{03C4}", 'upsilon;' => "\u{03C5}",
        'phi;' => "\u{03C6}", 'chi;' => "\u{03C7}", 'psi;' => "\u{03C8}",
        'omega;' => "\u{03C9}",
        'thetasym;' => "\u{03D1}", 'upsih;' => "\u{03D2}", 'piv;' => "\u{03D6}",
        // === Latin-1 supplement (accented forms) ===
        'Agrave;' => "\u{00C0}", 'Aacute;' => "\u{00C1}", 'Acirc;' => "\u{00C2}",
        'Atilde;' => "\u{00C3}", 'Auml;' => "\u{00C4}", 'Aring;' => "\u{00C5}",
        'AElig;' => "\u{00C6}", 'Ccedil;' => "\u{00C7}",
        'Egrave;' => "\u{00C8}", 'Eacute;' => "\u{00C9}", 'Ecirc;' => "\u{00CA}",
        'Euml;' => "\u{00CB}",
        'Igrave;' => "\u{00CC}", 'Iacute;' => "\u{00CD}", 'Icirc;' => "\u{00CE}",
        'Iuml;' => "\u{00CF}",
        'ETH;' => "\u{00D0}", 'Ntilde;' => "\u{00D1}",
        'Ograve;' => "\u{00D2}", 'Oacute;' => "\u{00D3}", 'Ocirc;' => "\u{00D4}",
        'Otilde;' => "\u{00D5}", 'Ouml;' => "\u{00D6}", 'Oslash;' => "\u{00D8}",
        'Ugrave;' => "\u{00D9}", 'Uacute;' => "\u{00DA}", 'Ucirc;' => "\u{00DB}",
        'Uuml;' => "\u{00DC}", 'Yacute;' => "\u{00DD}",
        'THORN;' => "\u{00DE}", 'szlig;' => "\u{00DF}",
        'agrave;' => "\u{00E0}", 'aacute;' => "\u{00E1}", 'acirc;' => "\u{00E2}",
        'atilde;' => "\u{00E3}", 'auml;' => "\u{00E4}", 'aring;' => "\u{00E5}",
        'aelig;' => "\u{00E6}", 'ccedil;' => "\u{00E7}",
        'egrave;' => "\u{00E8}", 'eacute;' => "\u{00E9}", 'ecirc;' => "\u{00EA}",
        'euml;' => "\u{00EB}",
        'igrave;' => "\u{00EC}", 'iacute;' => "\u{00ED}", 'icirc;' => "\u{00EE}",
        'iuml;' => "\u{00EF}",
        'eth;' => "\u{00F0}", 'ntilde;' => "\u{00F1}",
        'ograve;' => "\u{00F2}", 'oacute;' => "\u{00F3}", 'ocirc;' => "\u{00F4}",
        'otilde;' => "\u{00F5}", 'ouml;' => "\u{00F6}", 'oslash;' => "\u{00F8}",
        'ugrave;' => "\u{00F9}", 'uacute;' => "\u{00FA}", 'ucirc;' => "\u{00FB}",
        'uuml;' => "\u{00FC}", 'yacute;' => "\u{00FD}",
        'thorn;' => "\u{00FE}", 'yuml;' => "\u{00FF}",
        // Latin Extended
        'OElig;' => "\u{0152}", 'oelig;' => "\u{0153}",
        'Scaron;' => "\u{0160}", 'scaron;' => "\u{0161}",
        'Yuml;' => "\u{0178}",
        // === Arrows ===
        'larr;' => "\u{2190}", 'uarr;' => "\u{2191}", 'rarr;' => "\u{2192}",
        'darr;' => "\u{2193}", 'harr;' => "\u{2194}",
        'lArr;' => "\u{21D0}", 'uArr;' => "\u{21D1}", 'rArr;' => "\u{21D2}",
        'dArr;' => "\u{21D3}", 'hArr;' => "\u{21D4}",
        'crarr;' => "\u{21B5}",
        // === Spaces & layout ===
        'ensp;' => "\u{2002}", 'emsp;' => "\u{2003}", 'thinsp;' => "\u{2009}",
        'zwnj;' => "\u{200C}", 'zwj;' => "\u{200D}",
        'lrm;' => "\u{200E}", 'rlm;' => "\u{200F}",
        // === Geometric & misc symbols ===
        'loz;' => "\u{25CA}",
        'spades;' => "\u{2660}", 'clubs;' => "\u{2663}", 'hearts;' => "\u{2665}",
        'diams;' => "\u{2666}",
        'circ;' => "\u{02C6}", 'tilde;' => "\u{02DC}",
    ];

    /**
     * Names that do NOT require a trailing semicolon (legacy entries per spec).
     *
     * @var list<string>
     */
    public const array NO_SEMICOLON_ALLOWED = [
        'amp', 'lt', 'gt', 'quot', 'nbsp', 'copy', 'reg',
    ];

    /**
     * Numeric character reference codepoint substitution table per WHATWG
     * §13.2.5.80. Windows-1252-compatibility remappings for the C1 range.
     *
     * @var array<int, int>
     */
    public const array NUMERIC_REPLACEMENTS = [
        0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E,
        0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6,
        0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
        0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C,
        0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
        0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
        0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
    ];
}
