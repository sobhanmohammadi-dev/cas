<?php
namespace Sobhanmohammadi\CAS\Tests\Services;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\{SymbolTable, Simplifier, SimplifierObserver};
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Nodes\{MathNode, IntegerNode};
use Sobhanmohammadi\CAS\Exception\SimplifyException;

final class SimplifierTest extends TestCase
{
    private function simplify(string $src): string
    {
        $sym  = new SymbolTable();
        $simp = new Simplifier($sym);
        $tokens = (new Lexer($src))->tokenize();
        $ast = (new Parser($tokens, $src))->parse();
        return (string) $simp->simplifyFully($ast);
    }

    private function simplifyNode(string $src, Simplifier $simp): MathNode
    {
        $tokens = (new Lexer($src))->tokenize();
        $ast = (new Parser($tokens, $src))->parse();
        return $simp->simplifyFully($ast);
    }

    // ─── Identity / annihilation rules ─────────────────────────────────

    public function testAdditionIdentity(): void
    {
        $this->assertSame('5', $this->simplify('5 + 0'));
        $this->assertSame('5', $this->simplify('0 + 5'));
    }

    public function testMultiplicationByZero(): void
    {
        $this->assertSame('0', $this->simplify('5 * 0'));
        $this->assertSame('0', $this->simplify('0 * 5'));
    }

    public function testMultiplicationIdentity(): void
    {
        $this->assertSame('5', $this->simplify('5 * 1'));
        $this->assertSame('5', $this->simplify('1 * 5'));
    }

    public function testDivisionByOne(): void
    {
        $this->assertSame('7', $this->simplify('7 / 1'));
    }

    // ─── Power identity rules (incl. regression for the 0^r fix) ───────

    public function testPowerZeroExponent(): void
    {
        $this->assertSame('1', $this->simplify('5^0'));
    }

    public function testZeroToTheZeroIsOne(): void
    {
        $this->assertSame('1', $this->simplify('0^0'));
    }

    public function testZeroToPositivePowerIsZero(): void
    {
        $this->assertSame('0', $this->simplify('0^3'));
        $this->assertSame('0', $this->simplify('0^(1/2)'));
    }

    /**
     * Regression test: 0^(negative) is mathematically undefined (division by
     * zero) and must never silently fold to 0. Previously the identity rule
     * only checked "!isNumericZero($r)" and fired for any nonzero exponent,
     * including negative ones.
     */
    public function testZeroToNegativePowerThrowsInsteadOfFoldingToZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->simplify('0^(-2)');
    }

    public function testPowerOneExponent(): void
    {
        $this->assertSame('5', $this->simplify('5^1'));
    }

    public function testOneToAnyPowerIsOne(): void
    {
        $this->assertSame('1', $this->simplify('1^100'));
    }

    // ─── Constant folding ────────────────────────────────────────────

    public function testIntegerArithmeticFolding(): void
    {
        $this->assertSame('7', $this->simplify('3 + 4'));
        $this->assertSame('12', $this->simplify('3 * 4'));
        $this->assertSame('1/2', $this->simplify('1 / 2'));
        $this->assertSame('8', $this->simplify('2^3'));
    }

    public function testNegativeExponentFolding(): void
    {
        $this->assertSame('1/4', $this->simplify('2^(-2)'));
    }

    public function testDivisionByZeroThrows(): void
    {
        $this->expectException(SimplifyException::class);
        $this->simplify('5 / 0');
    }

    // ─── Power rule: (X^a)^b -> X^(a*b) ─────────────────────────────────

    public function testNestedPowerRule(): void
    {
        // (x^2)^3 -> x^6
        $this->assertSame('(x ^ 6)', $this->simplify('(x^2)^3'));
    }

    // ─── Like-term combination ──────────────────────────────────────────

    public function testCombineLikeTermsAddition(): void
    {
        $this->assertSame('(5 * x)', $this->simplify('2*x + 3*x'));
    }

    public function testCombineLikeTermsSubtractionToZero(): void
    {
        $this->assertSame('0', $this->simplify('3*x - 3*x'));
    }

    public function testCombineLikeTermsCoefficientOfOneCollapses(): void
    {
        // 3*x - 2*x -> 1*x -> x
        $this->assertSame('x', $this->simplify('3*x - 2*x'));
    }

    // ─── Distribution ────────────────────────────────────────────────

    public function testDistributionExpandsAndFolds(): void
    {
        $this->assertSame('11', $this->simplify('3*(2 + 1) + 2'));
    }

    // ─── Sqrt / Root simplification ─────────────────────────────────

    public function testSqrtOfPerfectSquare(): void
    {
        $this->assertSame('6', $this->simplify('sqrt(36)'));
    }

    public function testSqrtOfNonPerfectSquareStaysSymbolic(): void
    {
        $this->assertSame('sqrt(2)', $this->simplify('sqrt(2)'));
    }

    public function testRootDegreeOneIsIdentity(): void
    {
        $this->assertSame('9', $this->simplify('radical(1, 9)'));
    }

    public function testExactNthRoot(): void
    {
        $this->assertSame('3', $this->simplify('radical(3, 27)'));
    }

    // ─── Convergence / depth guards ─────────────────────────────────

    public function testSetObserverIsNotifiedOfRuleApplications(): void
    {
        $sym  = new SymbolTable();
        $simp = new Simplifier($sym);

        $observer = new class implements SimplifierObserver {
            public array $rules = [];
            public function onRuleApplied(string $ruleName, MathNode $before, MathNode $after): void
            {
                $this->rules[] = $ruleName;
            }
        };
        $simp->setObserver($observer);

        $this->simplifyNode('5 + 0', $simp);
        $this->assertNotEmpty($observer->rules);
    }

    public function testStructuralEqualsIsCommutativeForPlus(): void
    {
        $sym  = new SymbolTable();
        $simp = new Simplifier($sym);
        $a = $this->simplifyNode('x + 1', $simp);
        // Build "1 + x" without simplifying it into the exact same AST shape
        $tokens = (new Lexer('1 + x'))->tokenize();
        $b = (new Parser($tokens, '1 + x'))->parse();
        $this->assertTrue($simp->structuralEquals($a, $b));
    }

    public function testIsNumericZeroAndOneHelpers(): void
    {
        $sym  = new SymbolTable();
        $simp = new Simplifier($sym);
        $this->assertTrue($simp->isNumericZero(new IntegerNode('0', 0, 0)));
        $this->assertFalse($simp->isNumericZero(new IntegerNode('1', 0, 0)));
        $this->assertTrue($simp->isNumericOne(new IntegerNode('1', 0, 0)));
        $this->assertFalse($simp->isNumericOne(new IntegerNode('0', 0, 0)));
    }
}
