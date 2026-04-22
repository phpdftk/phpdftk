# apprlabs/geometry

Geometric primitives: Point, Rectangle, Matrix (CTM), PageSize constants, and BezierCurve. No PDF dependency — usable in any PHP project that deals with 2D geometry.

## Installation

```bash
composer require apprlabs/geometry
```

## Usage

```php
use ApprLabs\Geometry\Point;
use ApprLabs\Geometry\Rectangle;
use ApprLabs\Geometry\Matrix;
use ApprLabs\Geometry\PageSize;
use ApprLabs\Geometry\BezierCurve;

// Point
$p = new Point(72.0, 720.0);
echo $p->x; // 72.0

// Rectangle — origin (x, y) + dimensions, or from corner coordinates
$rect = new Rectangle(x: 0, y: 0, width: 612, height: 792);
[$x1, $y1, $x2, $y2] = $rect->toArray(); // [0, 0, 612, 792]

// Standard page sizes (in PDF points)
$letter = PageSize::letter();   // Rectangle(0, 0, 612, 792)
$a4     = PageSize::a4();       // Rectangle(0, 0, 595, 842)

// Current Transformation Matrix (6-element affine matrix)
$m = Matrix::identity();
$m = $m->translate(100, 200);
$m = $m->scale(2.0, 2.0);
$m = $m->rotate(deg2rad(45));
$m = $m->multiply($otherMatrix);
[$a, $b, $c, $d, $e, $f] = $m->toArray();

// Cubic Bézier curve — control points
$curve = new BezierCurve(
    new Point(0, 0),    // p0 start
    new Point(0, 100),  // p1 control
    new Point(100, 100),// p2 control
    new Point(100, 0)   // p3 end
);
$point = $curve->pointAt(t: 0.5);  // Point at t=0.5
$bounds = $curve->bounds();        // Bounding Rectangle
```

## Classes

| Class | Description |
|---|---|
| `Point` | Immutable 2D point with `x`, `y` float properties |
| `Rectangle` | Immutable rectangle; `toArray()` returns `[x, y, x+w, y+h]` |
| `Matrix` | 6-element CTM; `identity()`, `translate()`, `scale()`, `rotate()`, `multiply()` |
| `PageSize` | 12 standard page sizes (`letter()`, `a4()`, `a3()`, `legal()`, etc.) as Rectangle instances |
| `BezierCurve` | Cubic Bézier; `pointAt(float): Point`, `bounds(): Rectangle` via derivative extrema |
