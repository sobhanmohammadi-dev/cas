<?php
namespace Sobhanmohammadi\CAS\Tests\Services;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\LinearSolverTrait;
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Nodes\MathNode;

/**
 * Regression coverage for the LinearSolverTrait::containsVariable() bug where
 * the recursive check used the wrong namespace (\CAS\Nodes\BinaryOperatorNode
 * instead of \Sobhanmohammadi\CAS\Nodes\BinaryOperatorNode). Because that class
 * did not exist, `instanceof` silently evaluated to false and the recursion
 * never descended into +, -, *, /, ^ subtrees, so a variable buried inside one
 * (e.g. in "x/(x+1)") went undetected and the equation was wrongly treated as
 * linear.
 */
final class LinearSolverTraitTest extends TestCase
{
    /** Exposes the private trait methods for direct testing. */
    private function harness()
    {
        return new class {
            use LinearSolverTrait;

            public function containsVariablePublic(MathNode $node, string $varName): bool
            {
                return $this->containsVariable($node, $varName);
            }

            public function extractLinearCoefficientPublic(MathNode $expr, string $unknown): array
            {
                return $this->extractLinearCoefficient($expr, $unknown);
            }
        };
    }

    private function parseExpr(string $src): MathNode
    {
        $tokens = (new Lexer($src))->tokenize();
        return (new Parser($tokens, $src))->parse();
    }

    public function testContainsVariableDetectsVariableInsidePlusSubtree(): void
    {
        $h = $this->harness();
        $expr = $this->parseExpr('x + 1');
        $this->assertTrue($h->containsVariablePublic($expr, 'x'));
    }

    public function testContainsVariableDetectsVariableInsideNestedMultiplySubtree(): void
    {
        $h = $this->harness();
        $expr = $this->parseExpr('2 * (3 * (x + 1))');
        $this->assertTrue($h->containsVariablePublic($expr, 'x'));
    }

    public function testContainsVariableFalseWhenAbsent(): void
    {
        $h = $this->harness();
        $expr = $this->parseExpr('2 * (3 + 4)');
        $this->assertFalse($h->containsVariablePublic($expr, 'x'));
    }

    public function testDivisionWithVariableNestedInDenominatorIsRejectedAsNonlinear(): void
    {
        $h = $this->harness();
        // x/(x+1): x is buried inside a PlusNode inside the denominator.
        $expr = $this->parseExpr('x/(x+1)');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Nonlinear/');
        $h->extractLinearCoefficientPublic($expr, 'x');
    }

    public function testExponentWithVariableNestedInsideIsRejectedAsNonlinear(): void
    {
        $h = $this->harness();
        // 2^(x+1): x is buried inside a PlusNode inside the exponent.
        $expr = $this->parseExpr('2^(x+1)');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Nonlinear/');
        $h->extractLinearCoefficientPublic($expr, 'x');
    }

    public function testSqrtWithVariableNestedInsideIsRejectedAsNonlinear(): void
    {
        $h = $this->harness();
        $expr = $this->parseExpr('sqrt(x+1)');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Nonlinear/');
        $h->extractLinearCoefficientPublic($expr, 'x');
    }

    public function testGenuinelyLinearExpressionStillExtractsCorrectly(): void
    {
        $h = $this->harness();
        // 3*(x + 2) -> coefficient 3, constant 6 (before simplification, structurally: 3*x + 3*2)
        $expr = $this->parseExpr('3*(x + 2)');
        [$coeff, $constant] = $h->extractLinearCoefficientPublic($expr, 'x');
        $this->assertInstanceOf(MathNode::class, $coeff);
        $this->assertInstanceOf(MathNode::class, $constant);
    }
}
