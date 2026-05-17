<?php
namespace CAS\Nodes;

class SqrtNode extends MathNode
{
    private MathNode $radicand;

    public function __construct(MathNode $radicand, int $s, int $e)
    {
        parent::__construct($s, $e);
        $this->radicand = $radicand;
    }

    public function getRadicand(): MathNode
    {
        return $this->radicand;
    }

    public function __toString(): string
    {
        return 'sqrt(' . $this->radicand . ')';
    }
}