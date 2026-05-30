<?php
namespace Sobhanmohammadi\CAS\Nodes;

/**
 * Represents an nth-root: radical(degree, radicand).
 *
 * Constructor order: degree first, radicand second.
 * This matches every call-site in the parser and evaluators.
 */
class RootNode extends MathNode
{
    private MathNode $degree;
    private MathNode $radicand;

    public function __construct(MathNode $degree, MathNode $radicand, int $s, int $e)
    {
        parent::__construct($s, $e);
        $this->degree   = $degree;
        $this->radicand = $radicand;
    }

    public function getDegree(): MathNode   { return $this->degree; }
    public function getRadicand(): MathNode { return $this->radicand; }

    public function __toString(): string
    {
        return 'root(' . $this->degree . ', ' . $this->radicand . ')';
    }
}
