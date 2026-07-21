<?php
namespace Sobhanmohammadi\CAS\Nodes;

/**
 * Shared base for the single-argument trigonometric/inverse-trigonometric
 * function nodes (SinNode, CosNode, TanNode, AsinNode, AtanNode).
 *
 * Mirrors the shape of SqrtNode (one child, `(startPos, endPos)` span) so
 * the rest of the codebase can keep treating "a function of one
 * expression" uniformly. Concrete subclasses only need to supply their
 * function name for display/formatting.
 */
abstract class TrigFunctionNode extends MathNode
{
    private MathNode $argument;

    public function __construct(MathNode $argument, int $s, int $e)
    {
        parent::__construct($s, $e);
        $this->argument = $argument;
    }

    public function getArgument(): MathNode { return $this->argument; }

    /** Lowercase function name as it appears in source, e.g. "sin". */
    abstract public function getFunctionName(): string;

    public function __toString(): string
    {
        return $this->getFunctionName() . '(' . $this->argument . ')';
    }
}
