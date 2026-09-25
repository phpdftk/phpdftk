<?php

declare(strict_types=1);

namespace Phpdftk\Html;

/**
 * HTML §13.2.3 — "determining the character encoding", reduced to what a
 * static renderer can actually observe.
 *
 * The parser works in UTF-8. A document served in a legacy encoding is
 * therefore not merely mis-labelled, it is mis-READ: every non-ASCII byte
 * becomes U+FFFD, so `<meta charset=windows-1251>` + byte 0xE6 rendered
 * as a replacement box instead of `ж`. This class finds the declared
 * encoding and transcodes the source before tokenization.
 *
 * Two signals, in the spec's priority order:
 *
 *   1. A byte order mark, which overrides everything including an
 *      explicit `<meta>` that contradicts it.
 *   2. A `<meta>` charset declaration, found by {@see sniffMeta()}.
 *
 * With no signal the source is left alone and treated as UTF-8. That is
 * deliberately NOT the spec's fallback (which is locale-dependent, and
 * windows-1252 for most of the web): guessing windows-1252 for every
 * undeclared document would re-interpret every byte of the large majority
 * of documents that are in fact UTF-8, to fix the few that are not.
 */
final class EncodingSniffer
{
    /**
     * Elements whose content is TEXT, not markup. A `<meta charset>`
     * inside one of these is not a tag and must not be honoured — which
     * is the whole difference between this scan and a naive search for
     * `<meta`, and what `html/syntax/charset/in-script`, `in-style` and
     * `in-title` check by asserting the encoding does NOT change.
     *
     * `<template>` and foreign content (`<svg>`, `<math>`) are absent on
     * purpose: their children ARE tokenized as markup, so a `<meta>`
     * there does count.
     */
    /**
     * How far into the source a `<meta>` declaration may begin and still
     * count (HTML §13.2.3.2, "prescan a byte stream to determine its
     * encoding"). Past this the document is already being decoded, and
     * the declaration is simply too late.
     */
    private const int PRESCAN_LIMIT = 1024;

    private const array RAWTEXT_ELEMENTS = [
        'script', 'style', 'title', 'textarea', 'xmp',
        'iframe', 'noembed', 'noframes', 'plaintext',
    ];

    /**
     * Encoding Standard §4.2 labels → a name the transcoders understand.
     *
     * Only the legacy families that actually appear in documents are
     * listed. An unknown label resolves to null and the source is left
     * alone, which is the same outcome as before this existed.
     *
     * `iso-8859-1`, `ascii` and friends map to windows-1252 rather than
     * to themselves — that is the Encoding Standard's mapping, not a
     * shortcut, and it matters because the C1 range those labels leave
     * undefined is where windows-1252 keeps its curly quotes.
     */
    private const array LABELS = [
        'unicode-1-1-utf-8' => 'UTF-8',
        'utf-8' => 'UTF-8',
        'utf8' => 'UTF-8',
        'ascii' => 'Windows-1252',
        'us-ascii' => 'Windows-1252',
        'iso-8859-1' => 'Windows-1252',
        'iso8859-1' => 'Windows-1252',
        'latin1' => 'Windows-1252',
        'l1' => 'Windows-1252',
        'cp1252' => 'Windows-1252',
        'windows-1252' => 'Windows-1252',
        'iso-8859-2' => 'ISO-8859-2',
        'latin2' => 'ISO-8859-2',
        'iso-8859-5' => 'ISO-8859-5',
        'cyrillic' => 'ISO-8859-5',
        'iso-8859-6' => 'ISO-8859-6',
        'arabic' => 'ISO-8859-6',
        'iso-8859-7' => 'ISO-8859-7',
        'greek' => 'ISO-8859-7',
        'iso-8859-8' => 'ISO-8859-8',
        'hebrew' => 'ISO-8859-8',
        'iso-8859-8-i' => 'ISO-8859-8',
        'iso-8859-15' => 'ISO-8859-15',
        'cp1250' => 'Windows-1250',
        'windows-1250' => 'Windows-1250',
        'cp1251' => 'Windows-1251',
        'windows-1251' => 'Windows-1251',
        'cp1253' => 'Windows-1253',
        'windows-1253' => 'Windows-1253',
        'cp1254' => 'Windows-1254',
        'windows-1254' => 'Windows-1254',
        'cp1255' => 'Windows-1255',
        'windows-1255' => 'Windows-1255',
        'cp1256' => 'Windows-1256',
        'windows-1256' => 'Windows-1256',
        'cp1257' => 'Windows-1257',
        'windows-1257' => 'Windows-1257',
        'cp1258' => 'Windows-1258',
        'windows-1258' => 'Windows-1258',
        'koi8-r' => 'KOI8-R',
        'koi8-u' => 'KOI8-U',
        'ibm866' => 'CP866',
        'cp866' => 'CP866',
        'macintosh' => 'MacRoman',
        'x-mac-roman' => 'MacRoman',
        'shift_jis' => 'SJIS',
        'shift-jis' => 'SJIS',
        'sjis' => 'SJIS',
        'ms_kanji' => 'SJIS',
        'windows-31j' => 'SJIS',
        'euc-jp' => 'EUC-JP',
        'iso-2022-jp' => 'ISO-2022-JP',
        'euc-kr' => 'EUC-KR',
        'big5' => 'BIG-5',
        'big5-hkscs' => 'BIG-5',
        'csbig5' => 'BIG-5',
        'gbk' => 'CP936',
        'gb2312' => 'CP936',
        'gb18030' => 'GB18030',
        'utf-16' => 'UTF-16',
        'utf-16le' => 'UTF-16LE',
        'utf-16be' => 'UTF-16BE',
    ];

    /**
     * Transcode `$bytes` to UTF-8 using the declared encoding.
     *
     * `$override` takes priority over anything in the document — it is
     * the transport-level `Content-Type` charset, which outranks `<meta>`
     * in the spec's confidence ordering. Returns the source unchanged
     * when no encoding is found, when it is already UTF-8, or when no
     * transcoder on this build can handle it.
     */
    public static function toUtf8(string $bytes, ?string $override = null): string
    {
        $bom = self::bomEncoding($bytes);
        if ($bom !== null) {
            // A BOM outranks both `<meta>` and the transport. It is also
            // not content: leaving it in place puts a U+FEFF at the front
            // of the document, which the tree builder happily turns into
            // a stray text node.
            $bytes = substr($bytes, $bom === 'UTF-8' ? 3 : 2);
            return self::transcode($bytes, $bom);
        }
        $encoding = self::resolveLabel($override ?? '') ?? self::sniffMeta($bytes);
        if ($encoding === null || $encoding === 'UTF-8') {
            return $bytes;
        }
        return self::transcode($bytes, $encoding);
    }

    /** The encoding named by a leading byte order mark, if there is one. */
    public static function bomEncoding(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }
        if (str_starts_with($bytes, "\xFE\xFF")) {
            return 'UTF-16BE';
        }
        if (str_starts_with($bytes, "\xFF\xFE")) {
            return 'UTF-16LE';
        }
        return null;
    }

    /**
     * Scan `$bytes` for a `<meta>` charset declaration and return the
     * encoding it names, or null.
     *
     * This is the spec's "prescan a byte stream" with the tag awareness a
     * real tokenizer has: comments are skipped, and so is the CONTENT of
     * a rawtext element. Both matter — `<!--<meta charset=x>-->` and
     * `<script><meta charset=x></script>` must not change the encoding,
     * and a scan that only looked for `<meta` would honour both.
     *
     * The scan is capped at the spec's first 1024 bytes — measured at the
     * point a tag BEGINS, so a `<meta>` starting at byte 1023 is read to
     * completion while one starting at 1024 is not considered at all.
     * The cap is not an optimisation, it is observable: WPT's
     * `after-head-after-1kb` and `in-template-after-1kb` are `mismatch`
     * tests that assert a late declaration does NOT take effect, and an
     * uncapped scan honours them and fails.
     */
    public static function sniffMeta(string $bytes): ?string
    {
        $length = strlen($bytes);
        $i = 0;
        while ($i < $length && $i < self::PRESCAN_LIMIT) {
            if ($bytes[$i] !== '<') {
                $i++;
                continue;
            }
            if (substr($bytes, $i, 4) === '<!--') {
                $end = strpos($bytes, '-->', $i + 4);
                $i = $end === false ? $length : $end + 3;
                continue;
            }
            $rest = substr($bytes, $i + 1, 1);
            if ($rest === '!' || $rest === '?') {
                $end = strpos($bytes, '>', $i);
                $i = $end === false ? $length : $end + 1;
                continue;
            }
            $isEndTag = $rest === '/';
            $nameStart = $i + ($isEndTag ? 2 : 1);
            if (preg_match('/\G[a-zA-Z][^\t\n\f\r \/>]*/', $bytes, $m, 0, $nameStart) !== 1) {
                $i++;
                continue;
            }
            $tagName = strtolower($m[0]);
            $attributes = [];
            $i = self::readAttributes($bytes, $nameStart + strlen($m[0]), $attributes);
            if ($isEndTag) {
                continue;
            }
            if ($tagName === 'meta') {
                $found = self::encodingFromMetaAttributes($attributes);
                if ($found !== null) {
                    return $found;
                }
                continue;
            }
            if (in_array($tagName, self::RAWTEXT_ELEMENTS, true)) {
                // Everything up to the matching end tag is text.
                $close = stripos($bytes, '</' . $tagName, $i);
                $i = $close === false ? $length : $close;
            }
        }
        return null;
    }

    /**
     * The encoding a `<meta>`'s attributes declare, or null.
     *
     * Both spellings count: `charset="x"`, and the older
     * `http-equiv="content-type" content="text/html; charset=x"`.
     *
     * @param array<string, string> $attributes
     */
    private static function encodingFromMetaAttributes(array $attributes): ?string
    {
        if (isset($attributes['charset'])) {
            return self::resolveLabel($attributes['charset']);
        }
        $httpEquiv = strtolower(trim($attributes['http-equiv'] ?? ''));
        if ($httpEquiv !== 'content-type' || !isset($attributes['content'])) {
            return null;
        }
        if (preg_match('/charset[\t\n\f\r ]*=[\t\n\f\r ]*"?\'?([^"\';\t\n\f\r ]+)/i', $attributes['content'], $m) !== 1) {
            return null;
        }
        return self::resolveLabel($m[1]);
    }

    /**
     * Read a start tag's attributes into `$out`, returning the offset
     * just past the tag.
     *
     * Character references in the value ARE decoded, because a tokenizer
     * decodes them: `<meta charset="&#119;indows-1251">` declares
     * windows-1251, and WPT's `charset/ncr` exists to say so.
     *
     * @param array<string, string> $out
     */
    private static function readAttributes(string $bytes, int $i, array &$out): int
    {
        $length = strlen($bytes);
        while ($i < $length) {
            $i += strspn($bytes, "\t\n\f\r /", $i);
            if ($i >= $length) {
                return $length;
            }
            if ($bytes[$i] === '>') {
                return $i + 1;
            }
            $nameLength = strcspn($bytes, "\t\n\f\r =/>", $i);
            if ($nameLength === 0) {
                // Neither a name nor a terminator — step over it so a
                // malformed tag cannot spin here forever.
                $i++;
                continue;
            }
            $name = strtolower(substr($bytes, $i, $nameLength));
            $i += $nameLength;
            $i += strspn($bytes, "\t\n\f\r ", $i);
            if ($i >= $length || $bytes[$i] !== '=') {
                $out[$name] ??= '';
                continue;
            }
            $i++;
            $i += strspn($bytes, "\t\n\f\r ", $i);
            if ($i >= $length) {
                return $length;
            }
            $quote = $bytes[$i];
            if ($quote === '"' || $quote === "'") {
                $end = strpos($bytes, $quote, $i + 1);
                $value = $end === false
                    ? substr($bytes, $i + 1)
                    : substr($bytes, $i + 1, $end - $i - 1);
                $i = $end === false ? $length : $end + 1;
            } else {
                $valueLength = strcspn($bytes, "\t\n\f\r >", $i);
                $value = substr($bytes, $i, $valueLength);
                $i += $valueLength;
            }
            $out[$name] ??= html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $length;
    }

    /** Encoding Standard §4.2 — label → encoding name, or null. */
    private static function resolveLabel(string $label): ?string
    {
        return self::LABELS[strtolower(trim($label, "\t\n\f\r "))] ?? null;
    }

    /**
     * Transcode to UTF-8, preferring mbstring and falling back to iconv
     * for the encodings this build's mbstring lacks. When neither can
     * do it the source is returned untouched — a mis-decoded document
     * still renders something, where a thrown exception renders nothing.
     */
    private static function transcode(string $bytes, string $encoding): string
    {
        if ($encoding === 'UTF-8') {
            return $bytes;
        }
        if (in_array($encoding, mb_list_encodings(), true)) {
            $converted = @mb_convert_encoding($bytes, 'UTF-8', $encoding);
            if ($converted !== false) {
                return $converted;
            }
        }
        if (function_exists('iconv')) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $bytes);
            if ($converted !== false) {
                return $converted;
            }
        }
        return $bytes;
    }
}
