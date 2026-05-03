<?php declare(strict_types=1);
namespace Phpdftk\Geometry;

/**
 * Standard page dimensions as Rectangles in PDF points (1/72 inch).
 *
 * ISO 216 (A0–A6, B4–B5) and North American sizes (Letter, Legal,
 * Tabloid). All returned in portrait orientation — use `landscape()`
 * to swap width and height.
 */
final class PageSize {
    // All sizes in PDF points (1/72 inch), portrait orientation
    public static function letter(): Rectangle  { return new Rectangle(0, 0, 612, 792); }
    public static function legal(): Rectangle   { return new Rectangle(0, 0, 612, 1008); }
    public static function tabloid(): Rectangle { return new Rectangle(0, 0, 792, 1224); }
    public static function a0(): Rectangle      { return new Rectangle(0, 0, 2384, 3370); }
    public static function a1(): Rectangle      { return new Rectangle(0, 0, 1684, 2384); }
    public static function a2(): Rectangle      { return new Rectangle(0, 0, 1191, 1684); }
    public static function a3(): Rectangle      { return new Rectangle(0, 0, 842, 1191); }
    public static function a4(): Rectangle      { return new Rectangle(0, 0, 595, 842); }
    public static function a5(): Rectangle      { return new Rectangle(0, 0, 420, 595); }
    public static function a6(): Rectangle      { return new Rectangle(0, 0, 298, 420); }
    public static function b4(): Rectangle      { return new Rectangle(0, 0, 709, 1001); }
    public static function b5(): Rectangle      { return new Rectangle(0, 0, 499, 709); }
    public static function landscape(Rectangle $portrait): Rectangle {
        return new Rectangle(0, 0, $portrait->height, $portrait->width);
    }
}
