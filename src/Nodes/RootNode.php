<?php
namespace CAS\Nodes;

class RootNode extends MathNode
{
    private MathNode $radicand;
    private MathNode $degree;

    public function __construct(MathNode $radicand, MathNode $degree, int $s, int $e)
    {
        parent::__construct($s, $e);
        $this->radicand = $radicand;
        $this->degree = $degree;
    }

    public function getRadicand(): MathNode
    {
        return $this->radicand;
    }

    public function getDegree(): MathNode
    {
        return $this->degree;
    }

    public function __toString(): string
    {
        return 'root(' . $this->degree . ', ' . $this->radicand . ')';
    }
}