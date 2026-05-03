<?php declare(strict_types=1);
namespace Phpdftk\Geometry;

/**
 * Cubic Bézier curve — the primitive behind PDF path operators (c, v, y).
 *
 * Four control points define the curve. `boundingBox()` computes tight
 * bounds via derivative roots, used for clipping and hit-testing.
 */
final class BezierCurve {
    public function __construct(
        public readonly Point $p0,
        public readonly Point $p1,
        public readonly Point $p2,
        public readonly Point $p3,
    ) {}

    public function pointAt(float $t): Point {
        $u = 1 - $t;
        return new Point(
            $u**3*$this->p0->x + 3*$u**2*$t*$this->p1->x + 3*$u*$t**2*$this->p2->x + $t**3*$this->p3->x,
            $u**3*$this->p0->y + 3*$u**2*$t*$this->p1->y + 3*$u*$t**2*$this->p2->y + $t**3*$this->p3->y,
        );
    }

    /** Bounding box via extrema of the derivative polynomial */
    public function bounds(): Rectangle {
        $xs = [$this->p0->x, $this->p3->x];
        $ys = [$this->p0->y, $this->p3->y];
        foreach (['x', 'y'] as $axis) {
            $p0 = $this->p0->$axis; $p1 = $this->p1->$axis;
            $p2 = $this->p2->$axis; $p3 = $this->p3->$axis;
            $a = -3*$p0 + 9*$p1 - 9*$p2 + 3*$p3;
            $b =  6*$p0 - 12*$p1 + 6*$p2;
            $c = -3*$p0 + 3*$p1;
            if (abs($a) > 1e-10) {
                $disc = $b*$b - 4*$a*$c;
                if ($disc >= 0) {
                    foreach ([(-$b + sqrt($disc))/(2*$a), (-$b - sqrt($disc))/(2*$a)] as $t) {
                        if ($t > 0 && $t < 1) { $pt = $this->pointAt($t); ${$axis . 's'}[] = $pt->$axis; }
                    }
                }
            } elseif (abs($b) > 1e-10) {
                $t = -$c / $b;
                if ($t > 0 && $t < 1) { $pt = $this->pointAt($t); ${$axis . 's'}[] = $pt->$axis; }
            }
        }
        $minX = min($xs); $maxX = max($xs); $minY = min($ys); $maxY = max($ys);
        return new Rectangle($minX, $minY, $maxX - $minX, $maxY - $minY);
    }
}
