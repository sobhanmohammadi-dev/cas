<?php
namespace Sobhanmohammadi\CAS\Nodes;

abstract class MathNode
{
    protected int $startPos;
    protected int $endPos;

    public function __construct(int $s, int $e)
    {
        $this->startPos = $s;
        $this->endPos   = $e;
    }

    public function getStartPos(): int { return $this->startPos; }
    public function getEndPos(): int   { return $this->endPos; }

    abstract public function __toString(): string;

    /**
     * Returns a math-formatted string representation.
     * Numeric subclasses override this to return a clean "num/den" form.
     * All other nodes delegate to __toString().
     */
    public function toMathString(): string
    {
        return $this->__toString();
    }
}
