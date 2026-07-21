<?php
namespace Sobhanmohammadi\CAS\StepExplainer;

use Sobhanmohammadi\CAS\Nodes\{
    MathNode, IntegerNode,
    MinusNode, DivideNode, UnaryNode,
    VariableNode, SqrtNode, RootNode,
    PlusNode, MultiplyNode, PowerNode,
    EquationNode
};
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Services\{LinearSolverTrait, Simplifier, SymbolTable};

/**
 * Solves a linear equation one step at a time, recording each transformation
 * as a StepText so callers can render a full worked solution.
 *
 * This class merges the old StepSolver (numeric mode) and SymbolicStepSolver
 * (symbolic mode) into a single implementation that always works symbolically
 * and relies on the shared LinearSolverTrait for coefficient extraction.
 */
class StepSolver
{
    use LinearSolverTrait;

    private SymbolTable  $symbolTable;
    private Simplifier   $simplifier;
    private StepRecorder $recorder;

    public function __construct(SymbolTable $symbolTable)
    {
        $this->symbolTable = $symbolTable;
        $this->simplifier  = new Simplifier($symbolTable);
        $this->recorder    = new StepRecorder();
    }

    /** @return StepText[] */
    public function solve(string $equation, string $unknown): array
    {
        $this->recorder->reset();

        $lexer  = new Lexer($equation);
        $tokens = $lexer->tokenize();
        $parser = new Parser($tokens, $equation);
        $eqNode = $parser->parseEquation();

        $this->recorder->record(StepExplainer::equationStart($equation));
        $this->recorder->record(StepExplainer::classificationEquation($unknown));

        // Temporarily remove the unknown so it is treated as free during simplification
        $savedValue = $this->symbolTable->lookup($unknown);
        if ($savedValue !== null) {
            $this->symbolTable->remove($unknown);
        }

        try {
            return $this->solveNode($eqNode, $unknown, $equation);
        } catch (\RuntimeException $e) {
            if (strpos($e->getMessage(), 'Nonlinear') !== false) {
                $this->recorder->record(
                    StepExplainer::solverNonLinear($unknown, $e->getMessage())
                );
            }
            throw $e;
        } finally {
            if ($savedValue !== null) {
                $this->symbolTable->assign($unknown, $savedValue);
            }
        }
    }

    // ─── Core solving logic ───────────────────────────────────────────

    /** @return StepText[] */
    private function solveNode(EquationNode $eqNode, string $unknown, string $originalEq): array
    {
        // Step 1 – simplify each side independently
        $leftNode  = $this->simplifier->simplifyFully($eqNode->getLeft());
        $rightNode = $this->simplifier->simplifyFully($eqNode->getRight());

        // Step 2 – extract linear coefficients from each side
        [$a1, $b1] = $this->extractLinearCoefficient($leftNode,  $unknown);
        [$a2, $b2] = $this->extractLinearCoefficient($rightNode, $unknown);

        $a1 = $this->simplifier->simplifyFully($a1);
        $b1 = $this->simplifier->simplifyFully($b1);
        $a2 = $this->simplifier->simplifyFully($a2);
        $b2 = $this->simplifier->simplifyFully($b2);

        $this->recorder->record(StepExplainer::solverSimplify(
            $unknown,
            $b1->toMathString(),
            $a1->toMathString(),
            $b2->toMathString(),
            $a2->toMathString(),
            $leftNode->toMathString() . ' = ' . $rightNode->toMathString()
        ));

        // Step 3 – collect unknown on the left, constants on the right
        //   (a1 - a2)*x = b2 - b1  =>  netCoeff*x = negB
        $aExpr    = $this->simplifier->simplifyFully(new MinusNode($a1, $a2, 0, 0));
        $bExpr    = $this->simplifier->simplifyFully(new MinusNode($b1, $b2, 0, 0));
        $negBExpr = $this->simplifier->simplifyFully(new UnaryNode('-', $bExpr, 0, 0));

        $this->recorder->record(StepExplainer::solverCollect(
            $unknown,
            $a1->toMathString(),
            $a2->toMathString(),
            $aExpr->toMathString(),
            $b1->toMathString(),
            $b2->toMathString(),
            $negBExpr->toMathString(),
            $aExpr->toMathString() . ' * ' . $unknown . ' = ' . $negBExpr->toMathString()
        ));

        // Step 4 – degenerate cases
        if ($this->isZeroNode($aExpr)) {
            $isIdentity = $this->isZeroNode($bExpr);
            $this->recorder->record(
                StepExplainer::solverDegenerate($unknown, $isIdentity, $bExpr->toMathString())
            );
            throw new \RuntimeException(
                $isIdentity ? 'Infinite solutions (identity).' : 'No solution (contradiction).'
            );
        }

        // Step 5 – divide to isolate the unknown
        $solution = $this->simplifier->simplifyFully(
            new DivideNode($negBExpr, $aExpr, 0, 0)
        );

        if ($this->isOneNode($aExpr)) {
            $this->recorder->record(
                StepExplainer::solverDivideIsolated($unknown, $solution->toMathString())
            );
        } else {
            $this->recorder->record(StepExplainer::solverDivide(
                $unknown,
                $aExpr->toMathString(),
                $negBExpr->toMathString(),
                $solution->toMathString()
            ));
        }

        // Step 6 – verify by substituting the solution back
        $verifLeft  = $this->simplifier->simplifyFully(
            $this->substitute($eqNode->getLeft(),  $unknown, $solution)
        );
        $verifRight = $this->simplifier->simplifyFully(
            $this->substitute($eqNode->getRight(), $unknown, $solution)
        );

        $lhsStr = $verifLeft->toMathString();
        $rhsStr = $verifRight->toMathString();
        $ok     = ($lhsStr === $rhsStr);

        $this->recorder->record(StepExplainer::solverVerify(
            $unknown,
            $solution->toMathString(),
            $originalEq,
            $lhsStr,
            $rhsStr,
            '',
            $ok
        ));

        $this->recorder->record(
            StepExplainer::finalEquationResult($unknown, $solution->toMathString())
        );

        return $this->recorder->getSteps();
    }

    // ─── Substitution ─────────────────────────────────────────────────

    private function substitute(MathNode $expr, string $varName, MathNode $value): MathNode
    {
        if ($expr instanceof VariableNode && $expr->getName() === $varName) {
            return $value;
        }
        if ($expr instanceof PlusNode
            || $expr instanceof MinusNode
            || $expr instanceof MultiplyNode
            || $expr instanceof DivideNode
            || $expr instanceof PowerNode
        ) {
            $left  = $this->substitute($expr->getLeft(),  $varName, $value);
            $right = $this->substitute($expr->getRight(), $varName, $value);
            $class = get_class($expr);
            return new $class($left, $right, $expr->getStartPos(), $expr->getEndPos());
        }
        if ($expr instanceof UnaryNode) {
            return new UnaryNode(
                $expr->getOp(),
                $this->substitute($expr->getOperand(), $varName, $value),
                $expr->getStartPos(),
                $expr->getEndPos()
            );
        }
        if ($expr instanceof SqrtNode) {
            return new SqrtNode(
                $this->substitute($expr->getRadicand(), $varName, $value),
                $expr->getStartPos(),
                $expr->getEndPos()
            );
        }
        if ($expr instanceof RootNode) {
            return new RootNode(
                $this->substitute($expr->getDegree(),   $varName, $value),
                $this->substitute($expr->getRadicand(), $varName, $value),
                $expr->getStartPos(),
                $expr->getEndPos()
            );
        }
        if ($expr instanceof \Sobhanmohammadi\CAS\Nodes\Atan2Node) {
            return new \Sobhanmohammadi\CAS\Nodes\Atan2Node(
                $this->substitute($expr->getY(), $varName, $value),
                $this->substitute($expr->getX(), $varName, $value),
                $expr->getStartPos(),
                $expr->getEndPos()
            );
        }
        if ($expr instanceof \Sobhanmohammadi\CAS\Nodes\TrigFunctionNode) {
            $class = get_class($expr);
            return new $class(
                $this->substitute($expr->getArgument(), $varName, $value),
                $expr->getStartPos(),
                $expr->getEndPos()
            );
        }
        return $expr;
    }

    // ─── Node predicates ──────────────────────────────────────────────

    private function isZeroNode(MathNode $node): bool
    {
        return $node instanceof IntegerNode && \gmp_cmp($node->getValue(), 0) === 0;
    }

    private function isOneNode(MathNode $node): bool
    {
        return $node instanceof IntegerNode && \gmp_cmp($node->getValue(), 1) === 0;
    }
}
