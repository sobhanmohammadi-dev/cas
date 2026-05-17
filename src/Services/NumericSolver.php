<?php
namespace CAS\Services;

use CAS\Nodes\{
    MathNode, IntegerNode, RationalNode,
    PlusNode, MinusNode, MultiplyNode, DivideNode,
    UnaryNode,
    VariableNode, EquationNode
};
use CAS\Parser\{
    Lexer, Parser
};

class NumericSolver
{
    private SymbolTable $symbolTable;
    private NumericEvaluator $evaluator;

    public function __construct(SymbolTable $symbolTable)
    {
        $this->symbolTable = $symbolTable;
        $this->evaluator = new NumericEvaluator($symbolTable);
    }

    public function solve(string $equation, string $unknown): MathNode
    {
        $lexer  = new Lexer($equation);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $equation);
        $eqNode = $parser->parseEquation();

        return $this->solveEquationNode($eqNode, $unknown);
    }


    public function solveEquationNode(EquationNode $eqNode, string $unknown): MathNode
    {
        $diff = new MinusNode(
            $eqNode->getLeft(),
            $eqNode->getRight(),
            $eqNode->getStartPos(),
            $eqNode->getEndPos()
        );

        $origValue = $this->symbolTable->lookup($unknown);
        if ($origValue !== null) {
            $this->symbolTable->remove($unknown);
        }

        try {
            $this->symbolTable->assign($unknown, new IntegerNode('0', 0, 0));
            $f0 = $this->evaluator->evaluate($diff);

            $this->symbolTable->assign($unknown, new IntegerNode('1', 0, 0));
            $f1 = $this->evaluator->evaluate($diff);

            $b = $f0;
            $a = $this->evaluator->evaluate(
                new MinusNode($f1, $f0, 0, 0)
            );

            if ($this->isZero($a)) {
                if ($this->isZero($b)) {
                    throw new \RuntimeException('Infinite solutions (identity).');
                } else {
                    throw new \RuntimeException('No solution (contradiction).');
                }
            }

            $minusB = new UnaryNode('-', $b, 0, 0);
            $negB = $this->evaluator->evaluate($minusB);
            $solution = new DivideNode($negB, $a, 0, 0);
            $result = $this->evaluator->evaluate($solution);

            $this->symbolTable->assign($unknown, new IntegerNode('2', 0, 0));
            $f2 = $this->evaluator->evaluate($diff);

            $twoA = $this->evaluator->evaluate(new MultiplyNode(new IntegerNode('2', 0, 0), $a, 0, 0));
            $expected = $this->evaluator->evaluate(new PlusNode($twoA, $b, 0, 0));

            if (!$this->structuralEquals($f2, $expected)) {
                throw new \RuntimeException('The equation is not linear in the unknown variable.');
            }

            return $result;
        } finally {
            if ($origValue !== null) {
                $this->symbolTable->assign($unknown, $origValue);
            } else {
                $this->symbolTable->remove($unknown);
            }
        }
    }

    private function isZero(MathNode $node): bool
    {
        return $node instanceof IntegerNode && \gmp_cmp($node->getValue(), 0) === 0;
    }

    private function structuralEquals(MathNode $a, MathNode $b): bool
    {
        return (new Simplifier($this->symbolTable))->simplifyFully($a)->__toString()
            === (new Simplifier($this->symbolTable))->simplifyFully($b)->__toString();
    }
}