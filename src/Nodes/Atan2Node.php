<?php
namespace Sobhanmohammadi\CAS\Nodes;

/**
 * atan2(y, x) — two-argument inverse tangent, result in radians in
 * (-pi, pi], correctly resolving all four quadrants (unlike atan(y/x),
 * which loses the quadrant information once y/x is formed as a single
 * ratio). Argument order matches the universal atan2(y, x) convention
 * (mirrors RootNode's documented "degree, radicand" ordering convention
 * for two-argument functions in this grammar).
 */
class Atan2Node extends MathNode
{
    private MathNode $y;
    private MathNode $x;

    public function __construct(MathNode $y, MathNode $x, int $s, int $e)
    {
        parent::__construct($s, $e);
        $this->y = $y;
        $this->x = $x;
    }

    public function getY(): MathNode { return $this->y; }
    public function getX(): MathNode { return $this->x; }

    public function __toString(): string
    {
        return 'atan2(' . $this->y . ', ' . $this->x . ')';
    }
}
