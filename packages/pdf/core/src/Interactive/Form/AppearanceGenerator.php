<?php

declare(strict_types=1);

namespace ApprLabs\Pdf\Core\Interactive\Form;

use ApprLabs\Pdf\Core\Annotation\AppearanceDict;
use ApprLabs\Pdf\Core\Content\Resources;
use ApprLabs\Pdf\Core\Graphics\XObject\FormXObject;
use ApprLabs\Pdf\Core\PdfArray;
use ApprLabs\Pdf\Core\PdfName;
use ApprLabs\Pdf\Core\PdfNumber;
use ApprLabs\Pdf\Core\PdfReference;
use ApprLabs\Pdf\Core\PdfString;

/**
 * Generates appearance streams for interactive form fields.
 *
 * Produces FormXObject instances that render field UI (borders,
 * text, check marks, etc.) so fields are visible without relying
 * on /NeedAppearances=true.
 *
 * Each generate*() method returns a FormXObject that should be
 * registered with the writer and referenced from the field's
 * widget annotation's /AP/N entry.
 */
final class AppearanceGenerator
{
    /**
     * Generate a normal appearance for a text field.
     *
     * Draws a border rectangle and renders the field value using
     * the specified font resource name and size.
     *
     * @param PdfArray $rect  Widget rectangle [x1, y1, x2, y2]
     * @param string   $fontName Font resource name (e.g., "F1")
     * @param float    $fontSize Font size in points
     * @param string   $value Current field value text
     * @param int      $justification 0=left, 1=center, 2=right
     * @param float    $borderWidth Border line width
     */
    public static function textField(
        PdfArray $rect,
        string $fontName,
        float $fontSize,
        string $value = '',
        int $justification = 0,
        float $borderWidth = 1.0,
    ): FormXObject {
        $dims = self::rectDimensions($rect);
        $w = $dims['width'];
        $h = $dims['height'];

        $ops = [];

        // Border
        if ($borderWidth > 0) {
            $ops[] = sprintf('%.2f w', $borderWidth);
            $ops[] = '0.75 0.75 0.75 rg'; // light gray fill
            $ops[] = sprintf('0 0 %.2f %.2f re', $w, $h);
            $ops[] = 'f';
            $ops[] = '0 0 0 RG'; // black border
            $ops[] = sprintf('0 0 %.2f %.2f re', $w, $h);
            $ops[] = 'S';
        }

        // Text
        if ($value !== '') {
            $textY = ($h - $fontSize) / 2 + $fontSize * 0.15; // rough vertical centering
            $margin = $borderWidth + 2;
            $textX = match ($justification) {
                1 => $w / 2, // center — approximation without width measurement
                2 => $w - $margin,
                default => $margin,
            };

            $ops[] = 'BT';
            $ops[] = sprintf('/%s %.2f Tf', $fontName, $fontSize);
            $ops[] = '0 g';
            $ops[] = sprintf('%.2f %.2f Td', $textX, $textY);
            $ops[] = '(' . self::escapeString($value) . ') Tj';
            $ops[] = 'ET';
        }

        $bbox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber($w), new PdfNumber($h),
        ]);

        $xObj = new FormXObject($bbox, implode("\n", $ops));
        $xObj->resources = new Resources();

        return $xObj;
    }

    /**
     * Generate appearance streams for a checkbox field.
     *
     * Returns two FormXObjects: one for the "on" state (check mark)
     * and one for the "off" state (empty box).
     *
     * @return array{on: FormXObject, off: FormXObject}
     */
    public static function checkbox(
        PdfArray $rect,
        float $borderWidth = 1.0,
    ): array {
        $dims = self::rectDimensions($rect);
        $w = $dims['width'];
        $h = $dims['height'];

        $bbox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber($w), new PdfNumber($h),
        ]);

        // Off state: empty box
        $offOps = [];
        $offOps[] = sprintf('%.2f w', $borderWidth);
        $offOps[] = '0.95 0.95 0.95 rg';
        $offOps[] = sprintf('0 0 %.2f %.2f re', $w, $h);
        $offOps[] = 'f';
        $offOps[] = '0 0 0 RG';
        $offOps[] = sprintf('0 0 %.2f %.2f re', $w, $h);
        $offOps[] = 'S';

        $offXObj = new FormXObject($bbox, implode("\n", $offOps));
        $offXObj->resources = new Resources();

        // On state: box with check mark
        $onOps = $offOps; // start with the same box
        // Draw check mark using line segments
        $mx = $w * 0.2;
        $my = $h * 0.45;
        $cx = $w * 0.45;
        $cy = $h * 0.2;
        $ex = $w * 0.85;
        $ey = $h * 0.8;
        $onOps[] = sprintf('%.2f w', max(1.5, $w * 0.08));
        $onOps[] = '0 0 0 RG';
        $onOps[] = sprintf('%.2f %.2f m', $mx, $my);
        $onOps[] = sprintf('%.2f %.2f l', $cx, $cy);
        $onOps[] = sprintf('%.2f %.2f l', $ex, $ey);
        $onOps[] = 'S';

        $onXObj = new FormXObject(clone $bbox, implode("\n", $onOps));
        $onXObj->resources = new Resources();

        return ['on' => $onXObj, 'off' => $offXObj];
    }

    /**
     * Generate appearance streams for a radio button.
     *
     * @return array{on: FormXObject, off: FormXObject}
     */
    public static function radioButton(
        PdfArray $rect,
        float $borderWidth = 1.0,
    ): array {
        $dims = self::rectDimensions($rect);
        $w = $dims['width'];
        $h = $dims['height'];
        $r = min($w, $h) / 2 - $borderWidth;
        $cx = $w / 2;
        $cy = $h / 2;

        $bbox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber($w), new PdfNumber($h),
        ]);

        // Approximate circle with 4 Bézier curves
        $k = 0.5523; // magic constant for Bézier circle approximation

        $circleOps = self::buildCircleOps($cx, $cy, $r, $k);

        // Off state: empty circle
        $offOps = [];
        $offOps[] = sprintf('%.2f w', $borderWidth);
        $offOps[] = '0.95 0.95 0.95 rg';
        $offOps[] = '0 0 0 RG';
        $offOps = array_merge($offOps, $circleOps);
        $offOps[] = 'B'; // fill and stroke

        $offXObj = new FormXObject($bbox, implode("\n", $offOps));
        $offXObj->resources = new Resources();

        // On state: circle with filled dot
        $onOps = $offOps;
        $dotR = $r * 0.45;
        $dotOps = self::buildCircleOps($cx, $cy, $dotR, $k);
        $onOps[] = '0 0 0 rg';
        $onOps = array_merge($onOps, $dotOps);
        $onOps[] = 'f';

        $onXObj = new FormXObject(clone $bbox, implode("\n", $onOps));
        $onXObj->resources = new Resources();

        return ['on' => $onXObj, 'off' => $offXObj];
    }

    /**
     * Generate a normal appearance for a choice field (combo/list box).
     *
     * Renders the currently selected value text in a bordered box.
     */
    public static function choiceField(
        PdfArray $rect,
        string $fontName,
        float $fontSize,
        string $selectedValue = '',
        float $borderWidth = 1.0,
    ): FormXObject {
        // Choice field appearance is visually identical to text field
        return self::textField($rect, $fontName, $fontSize, $selectedValue, borderWidth: $borderWidth);
    }

    /**
     * Generate a push button appearance.
     */
    public static function pushButton(
        PdfArray $rect,
        string $fontName,
        float $fontSize,
        string $label = '',
        float $borderWidth = 1.5,
    ): FormXObject {
        $dims = self::rectDimensions($rect);
        $w = $dims['width'];
        $h = $dims['height'];

        $ops = [];

        // 3D-effect border
        $ops[] = '0.85 0.85 0.85 rg';
        $ops[] = sprintf('0 0 %.2f %.2f re', $w, $h);
        $ops[] = 'f';
        // Top/left highlight
        $ops[] = '1 1 1 RG';
        $ops[] = sprintf('%.2f w', $borderWidth);
        $ops[] = sprintf('0 0 m %.2f 0 l', $w);
        $ops[] = sprintf('0 0 m 0 %.2f l', $h);
        $ops[] = 'S';
        // Bottom/right shadow
        $ops[] = '0.5 0.5 0.5 RG';
        $ops[] = sprintf('%.2f 0 m %.2f %.2f l', $w, $w, $h);
        $ops[] = sprintf('0 %.2f m %.2f %.2f l', $h, $w, $h);
        $ops[] = 'S';

        // Label text centered
        if ($label !== '') {
            $textX = $w / 2;
            $textY = ($h - $fontSize) / 2 + $fontSize * 0.15;

            $ops[] = 'BT';
            $ops[] = sprintf('/%s %.2f Tf', $fontName, $fontSize);
            $ops[] = '0 g';
            $ops[] = sprintf('%.2f %.2f Td', $textX, $textY);
            $ops[] = '(' . self::escapeString($label) . ') Tj';
            $ops[] = 'ET';
        }

        $bbox = new PdfArray([
            new PdfNumber(0), new PdfNumber(0),
            new PdfNumber($w), new PdfNumber($h),
        ]);

        $xObj = new FormXObject($bbox, implode("\n", $ops));
        $xObj->resources = new Resources();

        return $xObj;
    }

    /**
     * Build an AppearanceDict with a single normal appearance.
     */
    public static function buildAppearanceDict(PdfReference $normalRef): AppearanceDict
    {
        $ap = new AppearanceDict();
        $ap->n = $normalRef;
        return $ap;
    }

    /**
     * Build an AppearanceDict for checkbox/radio with on/off states.
     *
     * @param PdfReference $onRef  Reference to the "on" FormXObject
     * @param PdfReference $offRef Reference to the "off" FormXObject
     * @param string $onStateName The appearance state name for "on" (e.g., "Yes")
     */
    public static function buildStateAppearanceDict(
        PdfReference $onRef,
        PdfReference $offRef,
        string $onStateName = 'Yes',
    ): AppearanceDict {
        $ap = new AppearanceDict();
        $stateDict = new \ApprLabs\Pdf\Core\PdfDictionary();
        $stateDict->set($onStateName, $onRef);
        $stateDict->set('Off', $offRef);
        $ap->n = $stateDict;
        return $ap;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return array{width: float, height: float}
     */
    private static function rectDimensions(PdfArray $rect): array
    {
        $items = $rect->items;
        $x1 = self::numVal($items[0] ?? null);
        $y1 = self::numVal($items[1] ?? null);
        $x2 = self::numVal($items[2] ?? null);
        $y2 = self::numVal($items[3] ?? null);

        return [
            'width' => abs($x2 - $x1),
            'height' => abs($y2 - $y1),
        ];
    }

    private static function numVal(mixed $val): float
    {
        if ($val instanceof PdfNumber) {
            return (float) $val->toPdf();
        }
        if (is_int($val) || is_float($val)) {
            return (float) $val;
        }
        return 0.0;
    }

    private static function escapeString(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $text
        );
    }

    /**
     * Build Bézier curve operators for a circle.
     *
     * @return list<string>
     */
    private static function buildCircleOps(float $cx, float $cy, float $r, float $k): array
    {
        $ops = [];
        $ops[] = sprintf('%.2f %.2f m', $cx + $r, $cy);
        $ops[] = sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            $cx + $r, $cy + $r * $k,
            $cx + $r * $k, $cy + $r,
            $cx, $cy + $r
        );
        $ops[] = sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            $cx - $r * $k, $cy + $r,
            $cx - $r, $cy + $r * $k,
            $cx - $r, $cy
        );
        $ops[] = sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            $cx - $r, $cy - $r * $k,
            $cx - $r * $k, $cy - $r,
            $cx, $cy - $r
        );
        $ops[] = sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c',
            $cx + $r * $k, $cy - $r,
            $cx + $r, $cy - $r * $k,
            $cx + $r, $cy
        );
        return $ops;
    }
}
