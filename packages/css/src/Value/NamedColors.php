<?php

declare(strict_types=1);

namespace Phpdftk\Css\Value;

/**
 * CSS named colors per CSS Color Module 4 §6.1. All keys are lower-case;
 * the value parser lowercases identifier input before lookup.
 *
 * The table is hand-maintained. Adding entries is additive (minor bump).
 *
 * @internal Used by the value parser only; consumers should construct `Color`
 *           values directly with explicit RGB.
 */
final class NamedColors
{
    /** @var array<string, array{0:int,1:int,2:int}> name → [r, g, b] (0-255) */
    public const array TABLE = [
        'aliceblue' => [240, 248, 255],
        'antiquewhite' => [250, 235, 215],
        'aqua' => [0, 255, 255],
        'aquamarine' => [127, 255, 212],
        'azure' => [240, 255, 255],
        'beige' => [245, 245, 220],
        'bisque' => [255, 228, 196],
        'black' => [0, 0, 0],
        'blanchedalmond' => [255, 235, 205],
        'blue' => [0, 0, 255],
        'blueviolet' => [138, 43, 226],
        'brown' => [165, 42, 42],
        'burlywood' => [222, 184, 135],
        'cadetblue' => [95, 158, 160],
        'chartreuse' => [127, 255, 0],
        'chocolate' => [210, 105, 30],
        'coral' => [255, 127, 80],
        'cornflowerblue' => [100, 149, 237],
        'cornsilk' => [255, 248, 220],
        'crimson' => [220, 20, 60],
        'cyan' => [0, 255, 255],
        'darkblue' => [0, 0, 139],
        'darkcyan' => [0, 139, 139],
        'darkgoldenrod' => [184, 134, 11],
        'darkgray' => [169, 169, 169],
        'darkgreen' => [0, 100, 0],
        'darkgrey' => [169, 169, 169],
        'darkkhaki' => [189, 183, 107],
        'darkmagenta' => [139, 0, 139],
        'darkolivegreen' => [85, 107, 47],
        'darkorange' => [255, 140, 0],
        'darkorchid' => [153, 50, 204],
        'darkred' => [139, 0, 0],
        'darksalmon' => [233, 150, 122],
        'darkseagreen' => [143, 188, 143],
        'darkslateblue' => [72, 61, 139],
        'darkslategray' => [47, 79, 79],
        'darkslategrey' => [47, 79, 79],
        'darkturquoise' => [0, 206, 209],
        'darkviolet' => [148, 0, 211],
        'deeppink' => [255, 20, 147],
        'deepskyblue' => [0, 191, 255],
        'dimgray' => [105, 105, 105],
        'dimgrey' => [105, 105, 105],
        'dodgerblue' => [30, 144, 255],
        'firebrick' => [178, 34, 34],
        'floralwhite' => [255, 250, 240],
        'forestgreen' => [34, 139, 34],
        'fuchsia' => [255, 0, 255],
        'gainsboro' => [220, 220, 220],
        'ghostwhite' => [248, 248, 255],
        'gold' => [255, 215, 0],
        'goldenrod' => [218, 165, 32],
        'gray' => [128, 128, 128],
        'green' => [0, 128, 0],
        'greenyellow' => [173, 255, 47],
        'grey' => [128, 128, 128],
        'honeydew' => [240, 255, 240],
        'hotpink' => [255, 105, 180],
        'indianred' => [205, 92, 92],
        'indigo' => [75, 0, 130],
        'ivory' => [255, 255, 240],
        'khaki' => [240, 230, 140],
        'lavender' => [230, 230, 250],
        'lavenderblush' => [255, 240, 245],
        'lawngreen' => [124, 252, 0],
        'lemonchiffon' => [255, 250, 205],
        'lightblue' => [173, 216, 230],
        'lightcoral' => [240, 128, 128],
        'lightcyan' => [224, 255, 255],
        'lightgoldenrodyellow' => [250, 250, 210],
        'lightgray' => [211, 211, 211],
        'lightgreen' => [144, 238, 144],
        'lightgrey' => [211, 211, 211],
        'lightpink' => [255, 182, 193],
        'lightsalmon' => [255, 160, 122],
        'lightseagreen' => [32, 178, 170],
        'lightskyblue' => [135, 206, 250],
        'lightslategray' => [119, 136, 153],
        'lightslategrey' => [119, 136, 153],
        'lightsteelblue' => [176, 196, 222],
        'lightyellow' => [255, 255, 224],
        'lime' => [0, 255, 0],
        'limegreen' => [50, 205, 50],
        'linen' => [250, 240, 230],
        'magenta' => [255, 0, 255],
        'maroon' => [128, 0, 0],
        'mediumaquamarine' => [102, 205, 170],
        'mediumblue' => [0, 0, 205],
        'mediumorchid' => [186, 85, 211],
        'mediumpurple' => [147, 112, 219],
        'mediumseagreen' => [60, 179, 113],
        'mediumslateblue' => [123, 104, 238],
        'mediumspringgreen' => [0, 250, 154],
        'mediumturquoise' => [72, 209, 204],
        'mediumvioletred' => [199, 21, 133],
        'midnightblue' => [25, 25, 112],
        'mintcream' => [245, 255, 250],
        'mistyrose' => [255, 228, 225],
        'moccasin' => [255, 228, 181],
        'navajowhite' => [255, 222, 173],
        'navy' => [0, 0, 128],
        'oldlace' => [253, 245, 230],
        'olive' => [128, 128, 0],
        'olivedrab' => [107, 142, 35],
        'orange' => [255, 165, 0],
        'orangered' => [255, 69, 0],
        'orchid' => [218, 112, 214],
        'palegoldenrod' => [238, 232, 170],
        'palegreen' => [152, 251, 152],
        'paleturquoise' => [175, 238, 238],
        'palevioletred' => [219, 112, 147],
        'papayawhip' => [255, 239, 213],
        'peachpuff' => [255, 218, 185],
        'peru' => [205, 133, 63],
        'pink' => [255, 192, 203],
        'plum' => [221, 160, 221],
        'powderblue' => [176, 224, 230],
        'purple' => [128, 0, 128],
        'rebeccapurple' => [102, 51, 153],
        'red' => [255, 0, 0],
        'rosybrown' => [188, 143, 143],
        'royalblue' => [65, 105, 225],
        'saddlebrown' => [139, 69, 19],
        'salmon' => [250, 128, 114],
        'sandybrown' => [244, 164, 96],
        'seagreen' => [46, 139, 87],
        'seashell' => [255, 245, 238],
        'sienna' => [160, 82, 45],
        'silver' => [192, 192, 192],
        'skyblue' => [135, 206, 235],
        'slateblue' => [106, 90, 205],
        'slategray' => [112, 128, 144],
        'slategrey' => [112, 128, 144],
        'snow' => [255, 250, 250],
        'springgreen' => [0, 255, 127],
        'steelblue' => [70, 130, 180],
        'tan' => [210, 180, 140],
        'teal' => [0, 128, 128],
        'thistle' => [216, 191, 216],
        'tomato' => [255, 99, 71],
        'turquoise' => [64, 224, 208],
        'violet' => [238, 130, 238],
        'wheat' => [245, 222, 179],
        'white' => [255, 255, 255],
        'whitesmoke' => [245, 245, 245],
        'yellow' => [255, 255, 0],
        'yellowgreen' => [154, 205, 50],
    ];

    public static function lookup(string $name): ?Color
    {
        $key = strtolower($name);
        if (isset(self::TABLE[$key])) {
            [$r, $g, $b] = self::TABLE[$key];
            return new Color($r / 255.0, $g / 255.0, $b / 255.0);
        }
        // CSS Color 4 §9 — system colors. Resolved here so both the
        // modern names (Canvas, CanvasText, …) and the §9.3 deprecated
        // aliases (Background, MenuText, ThreeDHighlight, …) parse as
        // valid colors. For a print rendering target with the standard
        // light theme: white backgrounds, black text, mid-gray
        // borders. Tests like css-color/deprecated-sameas-* rely on
        // the deprecated name resolving to the SAME RGB as the modern
        // alias it stands in for.
        if (isset(self::SYSTEM_COLORS[$key])) {
            [$r, $g, $b] = self::SYSTEM_COLORS[$key];
            return new Color($r / 255.0, $g / 255.0, $b / 255.0);
        }
        // 'transparent' and 'currentcolor' are special — handled separately.
        return null;
    }

    /**
     * Test whether a bareword names a CSS color we recognise — the
     * standard palette OR a system color. Used by `@supports` value
     * acceptance (CSS Cascade 5 §10.5) where a registered color-
     * typed property accepts any well-formed `<color>`.
     */
    public static function knows(string $name): bool
    {
        $key = strtolower($name);
        return isset(self::TABLE[$key]) || isset(self::SYSTEM_COLORS[$key]);
    }

    /**
     * CSS Color 4 §9 — system colors. The §9.3 deprecated aliases are
     * folded in with the same RGB as the modern color they alias, so
     * `MenuText` and `CanvasText` resolve identically (which the
     * `deprecated-sameas-*` WPT reftests assert).
     *
     * Values pick the standard light-theme palette appropriate for
     * print:
     *   • white-canvas family   → (255, 255, 255)
     *   • black-text family     → (0, 0, 0)
     *   • mid-gray button-edge  → (192, 192, 192)
     *   • dark-gray button-text → (128, 128, 128)
     *   • accent / link tones   → conventional blue/purple/red
     *
     * @var array<string, array{0:int,1:int,2:int}>
     */
    public const array SYSTEM_COLORS = [
        // Modern (CSS Color 4 §9.2) canvas family.
        'canvas' => [255, 255, 255],
        'canvastext' => [0, 0, 0],
        // Text accents.
        'accentcolor' => [0, 0, 238],
        'accentcolortext' => [255, 255, 255],
        'linktext' => [0, 0, 238],
        'visitedtext' => [85, 26, 139],
        'activetext' => [255, 0, 0],
        // Button surface.
        'buttonface' => [192, 192, 192],
        'buttontext' => [0, 0, 0],
        'buttonborder' => [128, 128, 128],
        // Input fields.
        'field' => [255, 255, 255],
        'fieldtext' => [0, 0, 0],
        // Highlights.
        'highlight' => [0, 0, 238],
        'highlighttext' => [255, 255, 255],
        'selecteditem' => [0, 0, 238],
        'selecteditemtext' => [255, 255, 255],
        'mark' => [255, 255, 0],
        'marktext' => [0, 0, 0],
        'graytext' => [128, 128, 128],
        // §9.3 deprecated — each aliases a modern name; values mirror
        // the modern entry above.
        'activecaption' => [255, 255, 255],         // → Canvas
        'appworkspace' => [255, 255, 255],          // → Canvas
        'background' => [255, 255, 255],            // → Canvas
        'buttonhighlight' => [192, 192, 192],       // → ButtonFace
        'buttonshadow' => [192, 192, 192],          // → ButtonFace
        'captiontext' => [0, 0, 0],                 // → CanvasText
        'inactiveborder' => [128, 128, 128],        // → ButtonBorder
        'inactivecaption' => [255, 255, 255],       // → Canvas
        'inactivecaptiontext' => [128, 128, 128],   // → GrayText
        'infobackground' => [255, 255, 255],        // → Canvas
        'infotext' => [0, 0, 0],                    // → CanvasText
        'menu' => [255, 255, 255],                  // → Canvas
        'menutext' => [0, 0, 0],                    // → CanvasText
        'scrollbar' => [255, 255, 255],             // → Canvas
        'threeddarkshadow' => [128, 128, 128],      // → ButtonBorder
        'threedface' => [192, 192, 192],            // → ButtonFace
        'threedhighlight' => [128, 128, 128],       // → ButtonBorder
        'threedlightshadow' => [128, 128, 128],     // → ButtonBorder
        'threedshadow' => [128, 128, 128],          // → ButtonBorder
        'window' => [255, 255, 255],                // → Canvas
        'windowframe' => [128, 128, 128],           // → ButtonBorder
        'windowtext' => [0, 0, 0],                  // → CanvasText
    ];
}
