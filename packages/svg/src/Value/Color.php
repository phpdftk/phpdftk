<?php

declare(strict_types=1);

namespace Phpdftk\Svg\Value;

use Phpdftk\Color\ColorInterface;
use Phpdftk\Color\RgbColor;

/**
 * Parse an SVG colour string into a `Phpdftk\Color\ColorInterface`.
 *
 * Recognised forms (SVG 2 / CSS Color 3 subset):
 *
 *  - Hex: `#rgb` (each digit doubled to a byte) and `#rrggbb`.
 *  - `rgb(R, G, B)` with numeric (0–255) or percentage (0–100%)
 *    components; comma-or-whitespace separated.
 *  - CSS named colours — the CSS Color 3 §4.3 table (148 entries
 *    including the spec-only `transparent`). Lookups are
 *    case-insensitive per spec.
 *
 * Returns null on absent / empty / malformed input — SVG 2's "invalid →
 * ignored" semantics. Callers that want a hard error (`Paint::parse` does
 * not) should check the return.
 *
 * Out of scope at 3E: `rgba()`, `hsl()`, `hwb()`, `lab()`, `lch()`,
 * `oklch()`, modern slash-syntax alpha, calc() inside components. These
 * land alongside the cascade work in 3J.
 */
final class Color
{
    public static function parse(string $raw): ?ColorInterface
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '#') {
            return self::parseHex(substr($value, 1));
        }
        if (preg_match('/^rgb\s*\(\s*(.*)\s*\)\s*$/i', $value, $m) === 1) {
            return self::parseRgb($m[1]);
        }

        $named = self::namedColors()[strtolower($value)] ?? null;
        if ($named !== null) {
            return RgbColor::fromHex($named);
        }
        return null;
    }

    private static function parseHex(string $body): ?ColorInterface
    {
        if (preg_match('/^[0-9a-fA-F]+$/', $body) !== 1) {
            return null;
        }
        if (strlen($body) === 3) {
            $body = $body[0] . $body[0] . $body[1] . $body[1] . $body[2] . $body[2];
        }
        if (strlen($body) !== 6) {
            return null;
        }
        try {
            return RgbColor::fromHex($body);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function parseRgb(string $body): ?ColorInterface
    {
        $parts = preg_split('/\s*,\s*|\s+/', trim($body)) ?: [];
        if (count($parts) !== 3) {
            return null;
        }
        $components = [];
        foreach ($parts as $part) {
            if ($part === '') {
                return null;
            }
            if (str_ends_with($part, '%')) {
                $n = substr($part, 0, -1);
                if (!is_numeric($n)) {
                    return null;
                }
                $components[] = max(0.0, min(1.0, ((float) $n) / 100.0));
                continue;
            }
            if (!is_numeric($part)) {
                return null;
            }
            $components[] = max(0.0, min(1.0, ((float) $part) / 255.0));
        }
        return new RgbColor($components[0], $components[1], $components[2]);
    }

    /**
     * CSS Color 3 §4.3 named-colour table. Lazily materialised at first
     * lookup — keeps the parse() fast-path free of the ~150-entry map.
     *
     * @return array<string, string>
     */
    private static function namedColors(): array
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'aliceblue' => 'F0F8FF', 'antiquewhite' => 'FAEBD7', 'aqua' => '00FFFF',
                'aquamarine' => '7FFFD4', 'azure' => 'F0FFFF', 'beige' => 'F5F5DC',
                'bisque' => 'FFE4C4', 'black' => '000000', 'blanchedalmond' => 'FFEBCD',
                'blue' => '0000FF', 'blueviolet' => '8A2BE2', 'brown' => 'A52A2A',
                'burlywood' => 'DEB887', 'cadetblue' => '5F9EA0', 'chartreuse' => '7FFF00',
                'chocolate' => 'D2691E', 'coral' => 'FF7F50', 'cornflowerblue' => '6495ED',
                'cornsilk' => 'FFF8DC', 'crimson' => 'DC143C', 'cyan' => '00FFFF',
                'darkblue' => '00008B', 'darkcyan' => '008B8B', 'darkgoldenrod' => 'B8860B',
                'darkgray' => 'A9A9A9', 'darkgreen' => '006400', 'darkgrey' => 'A9A9A9',
                'darkkhaki' => 'BDB76B', 'darkmagenta' => '8B008B', 'darkolivegreen' => '556B2F',
                'darkorange' => 'FF8C00', 'darkorchid' => '9932CC', 'darkred' => '8B0000',
                'darksalmon' => 'E9967A', 'darkseagreen' => '8FBC8F', 'darkslateblue' => '483D8B',
                'darkslategray' => '2F4F4F', 'darkslategrey' => '2F4F4F', 'darkturquoise' => '00CED1',
                'darkviolet' => '9400D3', 'deeppink' => 'FF1493', 'deepskyblue' => '00BFFF',
                'dimgray' => '696969', 'dimgrey' => '696969', 'dodgerblue' => '1E90FF',
                'firebrick' => 'B22222', 'floralwhite' => 'FFFAF0', 'forestgreen' => '228B22',
                'fuchsia' => 'FF00FF', 'gainsboro' => 'DCDCDC', 'ghostwhite' => 'F8F8FF',
                'gold' => 'FFD700', 'goldenrod' => 'DAA520', 'gray' => '808080',
                'green' => '008000', 'greenyellow' => 'ADFF2F', 'grey' => '808080',
                'honeydew' => 'F0FFF0', 'hotpink' => 'FF69B4', 'indianred' => 'CD5C5C',
                'indigo' => '4B0082', 'ivory' => 'FFFFF0', 'khaki' => 'F0E68C',
                'lavender' => 'E6E6FA', 'lavenderblush' => 'FFF0F5', 'lawngreen' => '7CFC00',
                'lemonchiffon' => 'FFFACD', 'lightblue' => 'ADD8E6', 'lightcoral' => 'F08080',
                'lightcyan' => 'E0FFFF', 'lightgoldenrodyellow' => 'FAFAD2', 'lightgray' => 'D3D3D3',
                'lightgreen' => '90EE90', 'lightgrey' => 'D3D3D3', 'lightpink' => 'FFB6C1',
                'lightsalmon' => 'FFA07A', 'lightseagreen' => '20B2AA', 'lightskyblue' => '87CEFA',
                'lightslategray' => '778899', 'lightslategrey' => '778899', 'lightsteelblue' => 'B0C4DE',
                'lightyellow' => 'FFFFE0', 'lime' => '00FF00', 'limegreen' => '32CD32',
                'linen' => 'FAF0E6', 'magenta' => 'FF00FF', 'maroon' => '800000',
                'mediumaquamarine' => '66CDAA', 'mediumblue' => '0000CD', 'mediumorchid' => 'BA55D3',
                'mediumpurple' => '9370DB', 'mediumseagreen' => '3CB371', 'mediumslateblue' => '7B68EE',
                'mediumspringgreen' => '00FA9A', 'mediumturquoise' => '48D1CC', 'mediumvioletred' => 'C71585',
                'midnightblue' => '191970', 'mintcream' => 'F5FFFA', 'mistyrose' => 'FFE4E1',
                'moccasin' => 'FFE4B5', 'navajowhite' => 'FFDEAD', 'navy' => '000080',
                'oldlace' => 'FDF5E6', 'olive' => '808000', 'olivedrab' => '6B8E23',
                'orange' => 'FFA500', 'orangered' => 'FF4500', 'orchid' => 'DA70D6',
                'palegoldenrod' => 'EEE8AA', 'palegreen' => '98FB98', 'paleturquoise' => 'AFEEEE',
                'palevioletred' => 'DB7093', 'papayawhip' => 'FFEFD5', 'peachpuff' => 'FFDAB9',
                'peru' => 'CD853F', 'pink' => 'FFC0CB', 'plum' => 'DDA0DD',
                'powderblue' => 'B0E0E6', 'purple' => '800080', 'rebeccapurple' => '663399',
                'red' => 'FF0000', 'rosybrown' => 'BC8F8F', 'royalblue' => '4169E1',
                'saddlebrown' => '8B4513', 'salmon' => 'FA8072', 'sandybrown' => 'F4A460',
                'seagreen' => '2E8B57', 'seashell' => 'FFF5EE', 'sienna' => 'A0522D',
                'silver' => 'C0C0C0', 'skyblue' => '87CEEB', 'slateblue' => '6A5ACD',
                'slategray' => '708090', 'slategrey' => '708090', 'snow' => 'FFFAFA',
                'springgreen' => '00FF7F', 'steelblue' => '4682B4', 'tan' => 'D2B48C',
                'teal' => '008080', 'thistle' => 'D8BFD8', 'tomato' => 'FF6347',
                'turquoise' => '40E0D0', 'violet' => 'EE82EE', 'wheat' => 'F5DEB3',
                'white' => 'FFFFFF', 'whitesmoke' => 'F5F5F5', 'yellow' => 'FFFF00',
                'yellowgreen' => '9ACD32',
            ];
        }
        return $map;
    }
}
