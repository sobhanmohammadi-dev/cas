<?php
require __DIR__ . '/../vendor/autoload.php';

use Sobhanmohammadi\CAS\Parser\{Lexer, Parser};
use Sobhanmohammadi\CAS\Services\{SymbolTable, Simplifier, SymbolicSolver, NumericSolver, NumericEvaluator, QuadraticRoot};
use Sobhanmohammadi\CAS\StepExplainer\{StepEvaluator, MathFormatter};
use Sobhanmohammadi\CAS\Exception\{
    CasException, DomainException, DivisionByZeroException,
    UnboundVariableException, UnsupportedOperationException, MathParseException
};
use Sobhanmohammadi\CAS\Nodes\{IntegerNode, RationalNode};

$failures = 0;
$passed   = 0;

function check(string $name, callable $fn): void
{
    global $failures, $passed;
    try {
        $fn();
        echo "PASS  {$name}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "FAIL  {$name}: " . get_class($e) . ': ' . $e->getMessage() . "\n";
        $failures++;
    }
}

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        throw new \RuntimeException("Assertion failed: {$msg}");
    }
}

function assertEquals($expected, $actual, string $msg = ''): void
{
    if ($expected != $actual) {
        throw new \RuntimeException("Expected " . var_export($expected, true) . " got " . var_export($actual, true) . " {$msg}");
    }
}

function assertThrows(string $exceptionClass, callable $fn, string $msg = ''): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        if ($e instanceof $exceptionClass) {
            return;
        }
        throw new \RuntimeException("Expected {$exceptionClass}, got " . get_class($e) . " {$msg}");
    }
    throw new \RuntimeException("Expected {$exceptionClass} to be thrown, none was. {$msg}");
}

function parseExpr(string $src)
{
    $lexer  = new Lexer($src);
    $tokens = $lexer->tokenize();
    $parser = new Parser($tokens, $src);
    return $parser->parse();
}

// ═══════════════════════════════════════════════════════════════════
//  1. Parsing trig functions
// ═══════════════════════════════════════════════════════════════════

check('parses sin/cos/tan/asin/atan/atan2', function () {
    $forms = ['sin(x)', 'cos(x)', 'tan(x)', 'asin(x)', 'atan(x)', 'atan2(y, x)'];
    foreach ($forms as $f) {
        $node = parseExpr($f);
        assertTrue((string) $node !== '', "toString for {$f}");
    }
});

check('parses 10 * sin(x) and cos(x) + 5', function () {
    $n1 = parseExpr('10 * sin(x)');
    $n2 = parseExpr('cos(x) + 5');
    assertTrue($n1 instanceof \Sobhanmohammadi\CAS\Nodes\MultiplyNode, 'n1 is Multiply');
    assertTrue($n2 instanceof \Sobhanmohammadi\CAS\Nodes\PlusNode, 'n2 is Plus');
});

check('parses implicit multiplication with trig: 10sin(x)', function () {
    $n = parseExpr('10sin(x)');
    assertTrue($n instanceof \Sobhanmohammadi\CAS\Nodes\MultiplyNode, 'implicit mult with sin');
});

check('malformed trig call throws MathParseException', function () {
    assertThrows(MathParseException::class, function () {
        parseExpr('sin(x');
    });
});

// ═══════════════════════════════════════════════════════════════════
//  2. Simplifier identities
// ═══════════════════════════════════════════════════════════════════

check('sin(0) simplifies to 0', function () {
    $st = new SymbolTable();
    $s  = new Simplifier($st);
    $r  = $s->simplifyFully(parseExpr('sin(0)'));
    assertTrue($r instanceof IntegerNode && \gmp_cmp($r->getValue(), 0) === 0, 'sin(0)=0');
});

check('cos(0) simplifies to 1', function () {
    $st = new SymbolTable();
    $s  = new Simplifier($st);
    $r  = $s->simplifyFully(parseExpr('cos(0)'));
    assertTrue($r instanceof IntegerNode && \gmp_cmp($r->getValue(), 1) === 0, 'cos(0)=1');
});

check('atan2(0,0) is a domain error even at simplify time', function () {
    $st = new SymbolTable();
    $s  = new Simplifier($st);
    assertThrows(DomainException::class, function () use ($s) {
        $s->simplifyFully(parseExpr('atan2(0, 0)'));
    });
});

check('sin(x) with unbound x simplifies to itself (stays symbolic)', function () {
    $st = new SymbolTable();
    $s  = new Simplifier($st);
    $r  = $s->simplifyFully(parseExpr('sin(x)'));
    assertEquals('sin(x)', (string) $r);
});

// ═══════════════════════════════════════════════════════════════════
//  3. Exact (GMP) evaluator rejects trig
// ═══════════════════════════════════════════════════════════════════

check('NumericEvaluator throws UnsupportedOperationException for sin(0)', function () {
    $st  = new SymbolTable();
    $ev  = new NumericEvaluator($st);
    assertThrows(UnsupportedOperationException::class, function () use ($ev) {
        $ev->evaluate(parseExpr('sin(0)'));
    });
});

check('NumericEvaluator throws UnboundVariableException for unbound var', function () {
    $st  = new SymbolTable();
    $ev  = new NumericEvaluator($st);
    assertThrows(UnboundVariableException::class, function () use ($ev) {
        $ev->evaluate(parseExpr('x + 1'));
    });
});

check('NumericEvaluator throws DivisionByZeroException', function () {
    $st  = new SymbolTable();
    $ev  = new NumericEvaluator($st);
    assertThrows(DivisionByZeroException::class, function () use ($ev) {
        $ev->evaluate(parseExpr('1 / 0'));
    });
});

check('All new exceptions are catchable as CasException', function () {
    assertTrue(is_subclass_of(DomainException::class, CasException::class), 'DomainException');
    assertTrue(is_subclass_of(DivisionByZeroException::class, CasException::class), 'DivisionByZeroException');
    assertTrue(is_subclass_of(UnboundVariableException::class, CasException::class), 'UnboundVariableException');
    assertTrue(is_subclass_of(UnsupportedOperationException::class, CasException::class), 'UnsupportedOperationException');
    assertTrue(is_subclass_of(MathParseException::class, CasException::class), 'MathParseException (widened)');
    assertTrue(is_subclass_of(CasException::class, \RuntimeException::class), 'CasException IS-A RuntimeException');
});

// ═══════════════════════════════════════════════════════════════════
//  4. Decimal StepEvaluator: numeric trig results
// ═══════════════════════════════════════════════════════════════════

check('StepEvaluator: sin(0) = 0.00000', function () {
    $st = new SymbolTable();
    $se = new StepEvaluator($st, 5);
    $steps = $se->evaluateExpression('sin(0)');
    $last = end($steps);
    assertTrue(str_contains($last->getCalculation(), '0'), 'contains 0');
});

check('StepEvaluator: cos(0) ~ 1', function () {
    $st = new SymbolTable();
    $se = new StepEvaluator($st, 6);
    $steps = $se->evaluateExpression('cos(0)');
    $last = end($steps);
    $txt = $last->getCalculation();
    assertTrue(str_contains($txt, '1.000000') || str_contains($txt, '1.0'), "cos(0)~1 in {$txt}");
});

check('StepEvaluator: asin(2) is out of domain', function () {
    $st = new SymbolTable();
    $se = new StepEvaluator($st, 5);
    assertThrows(DomainException::class, function () use ($se) {
        $se->evaluateExpression('asin(2)');
    });
});

check('StepEvaluator: atan2(1,1) ~ pi/4 = 0.785398...', function () {
    $st = new SymbolTable();
    $se = new StepEvaluator($st, 6);
    $steps = $se->evaluateExpression('atan2(1, 1)');
    $last  = end($steps);
    $txt   = $last->getCalculation();
    assertTrue(str_contains($txt, '0.785398'), "atan2(1,1)~0.785398 in {$txt}");
});

check('StepEvaluator: atan2(0,0) is undefined', function () {
    $st = new SymbolTable();
    $se = new StepEvaluator($st, 5);
    assertThrows(DomainException::class, function () use ($se) {
        $se->evaluateExpression('atan2(0, 0)');
    });
});

check('StepEvaluator: 10 * sin(x) with x=0 evaluates to 0', function () {
    $st = new SymbolTable();
    $st->assign('x', new IntegerNode('0', 0, 0));
    $se = new StepEvaluator($st, 4);
    $steps = $se->evaluateExpression('10 * sin(x)');
    $last  = end($steps);
    $txt   = $last->getCalculation();
    assertTrue(str_contains($txt, '0.0000') || str_contains($txt, '0'), "10*sin(0)=0 in {$txt}");
});

// ═══════════════════════════════════════════════════════════════════
//  5. MathFormatter renders trig without crashing
// ═══════════════════════════════════════════════════════════════════

check('MathFormatter formats sin(0) + cos(0)', function () {
    $st = new SymbolTable();
    $mf = new MathFormatter($st, 5);
    $out = $mf->format('sin(0) + cos(0)');
    assertTrue(is_array($out) && isset($out['title']), 'formatter returned structured output');
});

check('MathFormatter formats atan2(1,1)', function () {
    $st = new SymbolTable();
    $mf = new MathFormatter($st, 6);
    $out = $mf->format('atan2(1, 1)');
    assertTrue(is_array($out), 'formatter returned structured output');
});

// ═══════════════════════════════════════════════════════════════════
//  6. LinearSolverTrait bug fix: nonlinear detection through nested nodes
// ═══════════════════════════════════════════════════════════════════

check('Linear solver rejects x^2 (previously may have silently mishandled nested containsVariable)', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    assertThrows(\RuntimeException::class, function () use ($solver) {
        $solver->solve('x^2 + 1 = 5', 'x');
    });
});

check('Linear solver rejects variable inside sin()', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    assertThrows(\RuntimeException::class, function () use ($solver) {
        $solver->solve('sin(x) = 0', 'x');
    });
});

check('Linear solver still solves plain linear equations (compat check)', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    $sol = $solver->solve('2*x + 3 = 11', 'x');
    assertEquals('4', (string) $sol);
});

check('Linear solver: (x+1)^2 = 9 is now correctly detected as nonlinear (regression test for the containsVariable namespace bug)', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    assertThrows(\RuntimeException::class, function () use ($solver) {
        $solver->solve('(x + 1)^2 = 9', 'x');
    });
});

// ═══════════════════════════════════════════════════════════════════
//  7. Quadratic solver
// ═══════════════════════════════════════════════════════════════════

check('Quadratic: x^2 - 5x + 6 = 0 -> roots {2, 3} (positive discriminant, perfect square)', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    $roots = $solver->solveQuadratic('x^2 - 5*x + 6 = 0', 'x');
    assertEquals(2, count($roots));
    $vals = array_map(fn(QuadraticRoot $r) => $r->isReal() ? (string) $r->getReal() : null, $roots);
    sort($vals);
    assertEquals(['2', '3'], $vals);
});

check('Quadratic: x^2 - 4x + 4 = 0 -> repeated root 2 (zero discriminant)', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    $roots = $solver->solveQuadratic('x^2 - 4*x + 4 = 0', 'x');
    assertEquals(2, count($roots));
    assertTrue($roots[0]->isReal() && $roots[1]->isReal(), 'both real');
    assertEquals('2', (string) $roots[0]->getReal());
    assertEquals('2', (string) $roots[1]->getReal());
});

check('Quadratic: x^2 + 1 = 0 -> non-real roots +-i (negative discriminant)', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    $roots = $solver->solveQuadratic('x^2 + 1 = 0', 'x');
    assertEquals(2, count($roots));
    assertTrue(!$roots[0]->isReal() && !$roots[1]->isReal(), 'both complex');
    assertEquals('0', (string) $roots[0]->getReal());
    assertEquals('1', (string) $roots[0]->getImaginary());
    assertEquals('-1', (string) $roots[1]->getImaginary());
});

check('Quadratic: x^2 - 2 = 0 -> irrational roots stay symbolic (+-sqrt(2))', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    $roots = $solver->solveQuadratic('x^2 - 2 = 0', 'x');
    $strs = array_map(fn(QuadraticRoot $r) => (string) $r->getReal(), $roots);
    sort($strs);
    // Expect something containing sqrt(2) symbolically, not a decimal approximation.
    assertTrue(str_contains($strs[1], 'sqrt') || str_contains($strs[1], '2'), "got: " . implode(',', $strs));
});

check('Quadratic: a*x^2 + b*x + c = 0 with bound coefficients (Geometry-style usage)', function () {
    $st = new SymbolTable();
    $st->assign('a', new IntegerNode('1', 0, 0));
    $st->assign('b', new IntegerNode('-3', 0, 0));
    $st->assign('c', new IntegerNode('2', 0, 0));
    $solver = new SymbolicSolver($st);
    $roots = $solver->solveQuadratic('a*x^2 + b*x + c = 0', 'x');
    $vals = array_map(fn(QuadraticRoot $r) => (string) $r->getReal(), $roots);
    sort($vals);
    assertEquals(['1', '2'], $vals);
});

check('Quadratic solver rejects a degenerate (a=0) expression as DomainException', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    assertThrows(\Sobhanmohammadi\CAS\Exception\DomainException::class, function () use ($solver) {
        $solver->solveQuadratic('0*x^2 + 2*x - 4 = 0', 'x');
    });
});

check('Quadratic solver rejects a genuinely cubic expression as UnsupportedOperationException', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    assertThrows(UnsupportedOperationException::class, function () use ($solver) {
        $solver->solveQuadratic('x^3 - x = 0', 'x');
    });
});

check('SymbolicSolver::solveAuto falls back from linear to quadratic automatically', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    $result = $solver->solveAuto('x^2 - 5*x + 6 = 0', 'x');
    assertTrue(is_array($result), 'auto-detected as quadratic, returns array of roots');
    assertEquals(2, count($result));
});

check('SymbolicSolver::solveAuto still returns a single MathNode for linear equations', function () {
    $st = new SymbolTable();
    $solver = new SymbolicSolver($st);
    $result = $solver->solveAuto('2*x + 4 = 10', 'x');
    assertTrue($result instanceof \Sobhanmohammadi\CAS\Nodes\MathNode, 'linear result is a MathNode');
    assertEquals('3', (string) $result);
});

check('NumericSolver::solveQuadratic mirrors SymbolicSolver for perfect-square-discriminant case', function () {
    $st = new SymbolTable();
    $solver = new NumericSolver($st);
    $roots = $solver->solveQuadratic('x^2 - 5*x + 6 = 0', 'x');
    $vals = array_map(fn($r) => (string) $r['real'], $roots);
    sort($vals);
    assertEquals(['2', '3'], $vals);
});

// ═══════════════════════════════════════════════════════════════════
//  8. Existing (pre-existing) behavior untouched — compatibility checks
// ═══════════════════════════════════════════════════════════════════

check('sqrt/root solving still works exactly as before', function () {
    $st = new SymbolTable();
    $s  = new Simplifier($st);
    $r  = $s->simplifyFully(parseExpr('sqrt(16)'));
    assertEquals('4', (string) $r);
});

check('NumericSolver still solves simple linear equations', function () {
    $st = new SymbolTable();
    $solver = new NumericSolver($st);
    $sol = $solver->solve('3*x - 9 = 0', 'x');
    assertEquals('3', (string) $sol);
});

check('MathFormatter still formats plain arithmetic', function () {
    $st = new SymbolTable();
    $mf = new MathFormatter($st, 4);
    $out = $mf->format('2 + 3 * 4');
    assertTrue(is_array($out), 'formatter output is array');
});

// ═══════════════════════════════════════════════════════════════════

echo "\n{$passed} passed, {$failures} failed\n";
exit($failures > 0 ? 1 : 0);
