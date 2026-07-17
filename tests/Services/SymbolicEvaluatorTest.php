<?php
namespace Sobhanmohammadi\CAS\Tests\Services;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Services\SymbolicEvaluator;
use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Nodes\IntegerNode;

final class SymbolicEvaluatorTest extends TestCase
{
    private function parse(string $src)
    {
        $tokens = (new Lexer($src))->tokenize();
        return (new Parser($tokens, $src))->parse();
    }

    public function testEvaluateSimplifiesExpression(): void
    {
        $ev = new SymbolicEvaluator();
        $result = $ev->evaluate($this->parse('2 + 3 * 4'));
        $this->assertSame('14', (string) $result);
    }

    public function testDefaultSymbolTableIsCreatedWhenNoneGiven(): void
    {
        $ev = new SymbolicEvaluator();
        $this->assertNotNull($ev->getSymbolTable());
    }

    public function testAssignUpdatesSymbolTable(): void
    {
        $ev = new SymbolicEvaluator();
        $ev->assign('x', new IntegerNode('7', 0, 1));
        $this->assertSame('7', (string) $ev->getSymbolTable()->lookup('x'));
    }

    public function testEvaluateUsesAssignedVariable(): void
    {
        $ev = new SymbolicEvaluator();
        $ev->assign('x', new IntegerNode('7', 0, 1));
        $result = $ev->evaluate($this->parse('x + 3'));
        $this->assertSame('10', (string) $result);
    }

    public function testGetSimplifierReturnsSameInstanceUsedInternally(): void
    {
        $ev = new SymbolicEvaluator();
        $this->assertNotNull($ev->getSimplifier());
    }
}
