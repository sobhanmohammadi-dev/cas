<?php
namespace Sobhanmohammadi\CAS\Nodes;

class PiNode extends MathNode
{
    public function __construct(int $s, int $e)
    {
        parent::__construct($s, $e);
    }

    public function getConstantName(): string { return 'pi'; }

    public function __toString(): string { return 'π'; }
}
