# Sobhanmohammadi\CAS — Developer Guide

A PHP 7.4-compatible Computer Algebra System (CAS): it parses math expressions
and linear equations from strings, evaluates them exactly (GMP-based
rationals) or approximately (bcmath decimals), simplifies expressions
symbolically, solves linear equations, and can explain every step it takes in
English or Persian.

This guide documents every module in `src/`, how they fit together, the
expression grammar, the math conventions used, the error catalogue, and how
to extend the library safely.

---

## 1. Mental model: the four layers

```
string  ──Lexer──▶  tokens  ──Parser──▶  AST (Nodes)
                                            │
                        ┌───────────────────┼───────────────────┐
                        ▼                   ▼                   ▼
                 NumericEvaluator     Simplifier /        StepEvaluator /
                 NumericSolver        SymbolicEvaluator   StepSolver /
                 (exact, GMP)         SymbolicSolver      MathFormatter
                                      (symbolic, GMP)      (decimal, bcmath,
                                                             +explanations)
```

| Layer | Namespace | Job | Arithmetic backend |
|---|---|---|---|
| **Parsing** | `Sobhanmohammadi\CAS\Parser` | Turn a string into an AST | — |
| **AST** | `Sobhanmohammadi\CAS\Nodes` | Represent expressions/equations as a tree | GMP (exact ints/rationals) |
| **Services** | `Sobhanmohammadi\CAS\Services` | Evaluate, simplify, solve | GMP (exact) |
| **StepExplainer** | `Sobhanmohammadi\CAS\StepExplainer` | Produce human-readable, step-by-step explanations | bcmath (decimal approximations) |
| **Exception** | `Sobhanmohammadi\CAS\Exception` | Typed errors | — |

**Why two arithmetic backends?** GMP gives exact, arbitrary-precision
integers and rationals — essential for symbolic correctness (`1/3 + 1/3` must
stay `2/3`, never `0.6666...7`). bcmath is used only in the explanation layer,
where the goal is a human-readable *decimal* answer (e.g. `sqrt(2) ≈
1.4142135623`), which is inherently an approximation and doesn't need to be
exact.

---

## 2. Requirements

- PHP ≥ 7.4
- `ext-gmp` — exact integer/rational/complex arithmetic (declared in `composer.json`)
- `ext-bcmath` — arbitrary-precision decimal arithmetic for the `StepExplainer` layer (declared in `composer.json`)

Both extensions are required, not optional — a fresh `composer install`
will fail loudly if either is missing rather than crashing later at runtime.

---

## 3. Expression grammar

The lexer/parser accept the following surface syntax:

```
expression   := term (('+' | '-') term)*
term         := unary (('*' | '/') unary)*      # implicit multiplication also
                                                 # allowed between adjacent factors,
                                                 # e.g. "2x", "2(3+4)", "2 3 4"
unary        := ('-' | '+') unary | power
power        := factor ('^' unary)?             # right-associative: 2^3^2 = 2^(3^2)
factor       := NUMBER
              | IDENTIFIER                      # variable, e.g. x, {x1}
              | 'pi'
              | 'sqrt' '(' expression ')'
              | 'radical' '(' expression ',' expression ')'   # (degree, radicand)
              | '(' expression ')'

equation     := expression '=' expression
```

Notes:

- **Numbers** accept decimals and scientific notation: `3`, `0.5`, `.25`,
  `1.5e2`, `2E-3`.
- **Identifiers** are ordinary variable names (`x`, `foo_bar`) or braced
  names (`{x1}`) for identifiers that would otherwise be ambiguous.
- Keywords (`sqrt`, `radical`, `pi`) are case-insensitive.
- **Implicit multiplication** is supported between a number/`)`/variable and
  an immediately following number, `(`, variable, `sqrt`, `radical`, or `pi`
  — e.g. `2x`, `2(3+4)`, `2 3` (→ `2*3`).
- `radical(degree, radicand)` — note the argument order: **degree first**.
  `sqrt(x)` is shorthand for `radical(2, x)`.
- A recursion-depth guard in the parser rejects pathologically nested
  parentheses (protects against stack-exhaustion on malicious input).
- `parseEquation()` is a separate entry point from `parse()` — use it when
  you specifically expect a single `=` at the top level (equation solving);
  `parse()` is for plain expressions and will reject a bare `=`.

---

## 4. Module reference

### 4.1 `Parser` namespace

#### `Token`
Immutable value object: `(type, value, startPos, endPos)`. `type` is one of
the `Token::*` integer constants (`NUMBER`, `IDENTIFIER`, `PLUS`, `MINUS`,
`MULTIPLY`, `DIVIDE`, `POWER`, `LPAREN`, `RPAREN`, `EQUALS`, `COMMA`,
`SQRT`, `RADICAL`, `PI`, `EOF`).

#### `Lexer`
```php
$tokens = (new Lexer($sourceString))->tokenize(); // Token[]
```
Scans the source left-to-right into a flat array of `Token`s, always
terminated by an `EOF` token. Throws `MathParseException` on: unclosed/empty
braces, invalid characters inside braces, malformed numbers (e.g. two decimal
points), or any unrecognized character.

#### `Parser`
```php
$parser = new Parser($tokens, $originalSource); // $originalSource used for error messages
$ast    = $parser->parse();          // MathNode  — plain expression
$eq     = $parser->parseEquation();  // EquationNode — "lhs = rhs"
```
Recursive-descent parser implementing the grammar above. Throws
`MathParseException` for any syntax error (unexpected token, unbalanced
parens, missing `=` when `parseEquation()` is called, excessive nesting
depth).

---

### 4.2 `Nodes` namespace (the AST)

All node types extend the abstract `MathNode`, which stores `(startPos,
endPos)` (source-position span, useful for error reporting and tooling) and
requires `__toString()`. `toMathString()` defaults to `__toString()` and can
be overridden if a node ever needs a display form that differs from its
canonical form.

| Class | Represents | Key accessors |
|---|---|---|
| `IntegerNode` | Exact integer (GMP-backed) | `getValue(): \GMP`, `getSign(): int`, `isZero()`, `isOne()` |
| `RationalNode` | Exact reduced fraction `p/q`, `q>0` (GMP-backed) | `getValueOfNumerator()`, `getValueOfDenominator()`, `getSignOfNumerator()`, `toInteger(): ?IntegerNode` |
| `ComplexNode` | `a + bi`, both parts exact integers | `getReal()`, `getImag()`, `getRealSign()`, `getImagSign()`, `toInteger(): ?IntegerNode` (non-null only when `b=0`) |
| `NumericNode` | *Abstract* — not directly instantiated. Provides the `fromDecimalString()` factory that parses a raw decimal/scientific-notation string into the *simplest* correct node: `IntegerNode` if it's a whole number, otherwise a fully-reduced `RationalNode`. | `static fromDecimalString(string $raw, int $s, int $e): MathNode` |
| `VariableNode` | A named unknown/symbol | `getName()` |
| `PiNode` | The constant π (kept symbolic — never silently approximated in the exact layers) | `getConstantName()` |
| `UnaryNode` | Prefix `+`/`-` | `getOp()`, `getOperand()` |
| `BinaryOperatorNode` | *Abstract* base for the five binary operators | `getLeft()`, `getRight()`, `getOperatorSymbol()` |
| `PlusNode`, `MinusNode`, `MultiplyNode`, `DivideNode`, `PowerNode` | Concrete binary operators | (inherit from `BinaryOperatorNode`) |
| `SqrtNode` | `sqrt(x)` | `getRadicand()` |
| `RootNode` | `radical(n, x)` — **degree, then radicand** | `getDegree()`, `getRadicand()` |
| `EquationNode` | `lhs = rhs` | `getLeft()`, `getRight()` |
| `AssignmentNode` | `name = expr` | `getVariableName()`, `getExpression()` — **currently unwired**: the parser never produces this node from user input. It exists as a ready-made building block for a future assignment-statement feature; don't rely on it being reachable through `Parser::parse()` today. |

**GMP correctness note:** every node that parses a string into a GMP value
(`IntegerNode`, `RationalNode`, `ComplexNode`, `NumericNode`) explicitly
passes base `10` to `gmp_init()`. **Never omit the base argument** when
constructing these from a string — `gmp_init()` auto-detects base from a
`0`/`0x`/`0b` prefix, and a bare `gmp_init("025")` silently parses as *octal*
(→ 21, not 25). This bit the library once already (see §7); if you add a new
call site that builds a GMP value from a string, always write
`gmp_init($str, 10)`.

---

### 4.3 `Services` namespace

#### `SymbolTable`
A simple named-value store (`assign`, `lookup`, `isAssigned`, `remove`,
`all`). Shared by evaluators and solvers so that `x = 5` set once is visible
everywhere. Solvers temporarily remove the unknown's own binding while
solving for it, then restore whatever was there before (see
`SymbolicSolver`/`NumericSolver`).

#### `Simplifier`
The symbolic simplification engine — the mathematical heart of the library.
```php
$simplifier = new Simplifier($symbolTable);
$simplified = $simplifier->simplifyFully($ast); // MathNode
```
Repeatedly applies a fixed set of algebraic rewrite rules to a node until no
rule applies or a safety iteration cap is hit (guards against a rule cycle
that never converges), in roughly this order per pass:

1. **Identities/annihilators** — `x+0→x`, `x*0→0`, `x*1→x`, `x/1→x`,
   `x^0→1`, `1^x→1`, `0^x→0` **only for `x>0`** (see §7 — `0^0=1` by
   convention, `0^negative` is undefined and must raise an error, not fold
   to `0`).
2. **Constant folding** — when both operands of a binary op are exact
   numeric literals, compute the result directly with GMP (integers/
   rationals) rather than leaving it symbolic. Division by zero and
   `0^negative` both throw at this stage if not caught earlier.
3. **Power-of-power** — `(x^a)^b → x^(a*b)`.
4. **Like-term combination** — `2x + 3x → 5x`, coefficients folded via GMP.
5. **Distribution** — `a*(b+c) → a*b + a*c` (and the mirror-image cases).
6. **Sqrt/root simplification** — exact roots collapse (`sqrt(36)→6`,
   `radical(3,27)→3`); inexact ones are left symbolic (`sqrt(2)` stays
   `sqrt(2)` rather than being approximated — approximation only happens in
   the `StepExplainer` layer).

Helper methods you can reuse when writing new rules: `isNumericZero(MathNode)`,
`isNumericOne(MathNode)`, `structuralEquals(MathNode, MathNode)` (tree-shape
equality, used to detect when like terms/subexpressions match).

**Observer hook.** `Simplifier::setObserver(SimplifierObserver $o)` lets a
caller be notified of every individual rule application
(`onRuleApplied(string $ruleName, MathNode $before, MathNode $after)`)
without the `Simplifier` itself knowing anything about step-recording. This
is how `SymbolicStepEvaluator` produces its step list — it *observes* the
same simplifier the rest of the library uses, instead of re-implementing
simplification logic. If you need a new kind of "watch the simplifier work"
feature, implement `SimplifierObserver` rather than subclassing or forking
`Simplifier`.

#### `SymbolicEvaluator`
Thin convenience wrapper: parses nothing itself — takes an already-built
`MathNode` and returns `simplifier->simplifyFully($node)`. Owns its own
`SymbolTable` + `Simplifier` if you don't supply one. Use this when you just
want "give me the simplified form" without any of the step/solve machinery.

#### `SymbolicSolver`
```php
$solver = new SymbolicSolver($symbolTable);
$root   = $solver->solve('3*(x + 2) = 15', 'x'); // MathNode, simplified
```
Parses the equation, simplifies both sides, and uses `LinearSolverTrait` to
extract a linear coefficient/constant for the unknown and solve
`coefficient * x = constant`. Throws `RuntimeException` for:
`"No solution (contradiction)."`, `"Infinite solutions (identity)."`, or a
`"Nonlinear equation: ..."` message (see below) when the equation isn't
actually linear in the unknown.

#### `NumericSolver`
Same public surface as `SymbolicSolver`, but delegates entirely to it and
then coerces the result to an exact numeric node. (Earlier revisions of this
class did fragile numeric sampling instead of delegating — don't reintroduce
that; delegating to the symbolic solver is both simpler and exact.)

#### `NumericEvaluator`
```php
$ev = new NumericEvaluator($symbolTable);
$value = $ev->evaluate($ast); // IntegerNode | RationalNode | ComplexNode
```
Evaluates an AST down to an exact numeric value using GMP throughout —
no symbolic leftovers (a bare `x` with no binding, or `pi`, will throw
rather than being returned symbolically). Requires **integer exponents**
(fractional powers are undefined in exact/GMP arithmetic — use the
`StepExplainer` layer for decimal approximations of things like `2^(1/2)`).
`sqrt`/`radical` only succeed when the result is exact (a perfect
square/cube/etc.); otherwise they throw.

#### `LinearSolverTrait`
Shared logic used by both solvers to classify and decompose an expression as
linear in a given unknown:
- `containsVariable(MathNode $node, string $varName): bool` — recursively
  checks whether `$varName` appears anywhere in the subtree, including
  inside `BinaryOperatorNode` children (`+ - * / ^`), `UnaryNode`,
  `SqrtNode`, `RootNode`.
- `extractLinearCoefficient(MathNode $expr, string $unknown): array` —
  returns `[coefficientNode, constantNode]` for a genuinely linear
  expression, or throws `RuntimeException` with a message like
  `"Nonlinear equation: variable in denominator."`,
  `"...variable inside sqrt."`, `"...variable in exponent."`, or
  `"...variable raised to power > 1."` for the various ways an expression
  can fail to be linear.

**If you use this trait anywhere new:** `containsVariable`'s correctness
depends entirely on its `instanceof` checks matching real, importable class
names. A namespace typo here (see §7) makes the check silently evaluate to
`false` rather than erroring, which is exactly the kind of bug that slips
past casual testing — always import node classes via `use` at the top of the
file rather than typing fully-qualified names inline, so a typo becomes an
"undefined class" fatal instead of a silent `false`.

#### `SimplifierObserver` (interface)
```php
interface SimplifierObserver
{
    public function onRuleApplied(string $ruleName, MathNode $before, MathNode $after): void;
}
```
Implement this to watch simplification happen in real time (see
`Simplifier` above and `SymbolicStepEvaluator` below for the reference
implementation).

---

### 4.4 `StepExplainer` namespace

This layer answers "how would a human explain this calculation?" — it works
in **decimal** (via bcmath) so results read naturally (`1.4142135623` rather
than a surd), and it always produces bilingual explanations (English +
Persian) alongside the numbers.

#### `StepText`
Immutable value object bundling four parallel representations of one step:
`getEn()`, `getFa()` (Persian), `getFormula()` (the symbolic rule, e.g.
`"a + b = c"`), `getCalculation()` (the concrete numbers plugged in, e.g.
`"3 + 4 = 7"`).

#### `Texts` / `StepExplainer` (static)
`Texts::$messages` is the bilingual message-template catalogue (keyed by
message name, each entry `['en' => ..., 'fa' => ...]` with `{placeholder}`
tokens). `StepExplainer` is a static factory with one method per kind of
step (`expressionStart`, `variableSubstitution`, `addition`, `subtraction`,
`multiplication`, `division`, `exponentiation`, `sqrtOperation`,
`radicalOperation`, `solverSimplify`, `solverCollect`, `solverDivide`,
`solverVerify`, `errorDivisionByZero`, `errorImaginarySqrt`, and more) —
each pulls the right template out of `Texts::$messages`, fills in the
placeholders, and returns a `StepText`. **To add a new kind of step**: add a
template pair to `Texts::$messages`, then add a thin static method to
`StepExplainer` that formats and returns it — don't hand-build `StepText`
objects with inline strings elsewhere, or the bilingual guarantee breaks.

#### `StepRecorder`
A minimal ordered buffer of `StepText`s (`record()`, `getSteps()`,
`reset()`). Used internally by the classes below; you generally won't touch
it directly.

#### `StepEvaluator`
```php
$ev = new StepEvaluator($symbolTable, /* scale = */ 10);
$steps = $ev->evaluateExpression('(2^3 * sqrt(36)) / 4 + 5^2'); // StepText[]
```
Walks the AST once, computing a running **decimal** value at each node via
bcmath and recording a `StepText` for every operation. Division by zero,
`sqrt`/`radical` of a negative number under an even root, and a
zero-degree root all throw `RuntimeException` with a clear message (not a
raw bcmath/`DivisionByZeroError`).

Nth roots (`radical(n, x)` for `n > 1`, and `sqrt`) are computed by
**Newton's method**, not `bcpow()` — see §7 for why `bcpow()` with a
fractional exponent is unsafe. If you need a root computed to decimal
precision anywhere else in this layer, call the existing `bcNthRoot(string
$rad, string $deg, int $scale): string` helper rather than reaching for
`bcpow($rad, bcdiv('1', $deg, ...), ...)` again.

#### `StepSolver`
```php
$solver = new StepSolver($symbolTable);
$steps  = $solver->solve('3*(x + 2) = 15', 'x'); // StepText[], ends with "Solution: x = 3" (+ verification)
```
The step-by-step equation solver: classifies the equation, simplifies each
side, collects like terms, isolates the unknown, and finally **substitutes
the solution back in and verifies both sides are equal** — recording a step
for every stage in both languages. This class merged the old numeric-only
`StepSolver` and a separate `SymbolicStepSolver` into one implementation;
there is no longer a reason to have two solver-step classes, so don't
reintroduce a split.

#### `SymbolicStepEvaluator`
```php
$ev = new SymbolicStepEvaluator($symbolTable);
$result = $ev->evaluate($ast); // MathNode, fully simplified
$steps  = $ev->getSteps();     // StepText[] — one per Simplifier rule application
```
Implements `SimplifierObserver` and hands itself to an internal `Simplifier`
instance, so it gets one step recorded per rewrite rule the simplifier
actually applies — with zero duplicated simplification logic. `reset()`s its
recorder at the start of each `evaluate()` call, so steps never leak between
calls.

#### `MathFormatter`
The richest, most consumer-facing class — produces a fully structured,
ready-to-render result:
```php
$fmt = new MathFormatter($symbolTable, 'en', /* scale = */ 10);
$result = $fmt->format('(2^3 * sqrt(36)) / 4 + 5^2');
```
returns an array shaped like:
```php
[
  'title'      => string,
  'expression' => string,           // the original input, as parsed
  'steps'      => [
      [
        'step' => int, 'operation' => string, 'target' => string,
        'before' => string, 'after' => string,
        'formula' => string, 'calculation' => string, 'explanation' => string,
      ],
      ...
  ],
  'result'     => ['value' => string, ...],
]
```
Internally walks the AST like `StepEvaluator` but additionally tracks
*before/after* expression states keyed by `spl_object_id()` of each node, so
it can render "the whole expression, with this subexpression circled" at
every step — `spl_object_id` is used rather than a value-based key because
two structurally-identical subtrees (e.g. `x` appearing twice) are still
different *objects* in the AST and must be tracked independently. If you
add new node types to the AST, make sure any new formatter code keys its
state map by `spl_object_id`, not by node content.

Root computation here uses the same `bcNthRoot()` Newton's-method helper as
`StepEvaluator` (duplicated in this class, not shared via a trait — if you
find yourself fixing a root/precision bug in one, check the other file too).

---

### 4.5 `Exception` namespace

- `MathParseException extends \RuntimeException` — lexer/parser syntax errors.
- `SimplifyException extends \RuntimeException` — simplification failures
  (e.g. exact division by zero during constant folding).

Both are plain `RuntimeException` subclasses purely for callers who want to
`catch` parse errors separately from simplification errors; everything else
in the library (solver rejections, evaluator errors, formatter errors) uses
plain `\RuntimeException` or `\InvalidArgumentException` directly rather
than inventing a new exception type per error — keep that convention when
adding new failure modes; don't create a new exception subclass unless
callers genuinely need to distinguish it from other `RuntimeException`s.

---

## 5. Worked example: what happens when you call `MathFormatter::format()`

```php
use Sobhanmohammadi\CAS\Services\SymbolTable;
use Sobhanmohammadi\CAS\StepExplainer\MathFormatter;

$sym = new SymbolTable();
$fmt = new MathFormatter($sym, 'en');
$result = $fmt->format('3*(x + 2)');
```

1. `MathFormatter` constructs a `Lexer`/`Parser` internally and parses the
   string into an AST: `Multiply(3, Plus(x, 2))`.
2. It walks the tree bottom-up. `x` has no binding in `$sym` → throws
   `RuntimeException("Undefined variable: x")` immediately (formatter
   requires every variable to be bound — it's for *computing an answer*,
   not for producing a *symbolic* form; use `SymbolicEvaluator`/`Simplifier`
   for that instead).
3. If `x` were bound (say `$sym->assign('x', new IntegerNode('5', 0, 0))`),
   the walk would compute `5+2=7`, then `3*7=21`, recording a `StepText`
   for each operation with matching English/Persian explanations, and
   return `['title'=>..., 'expression'=>'3*(x + 2)', 'steps'=>[...],
   'result'=>['value'=>'21', ...]]`.

---

## 6. Extending the library

**Adding a new operator/function (e.g. `log(base, x)`):**
1. Add a `Token` type + lexer keyword recognition.
2. Add a `LogNode extends MathNode` (or similar) in `Nodes/` with the same
   `(startPos, endPos)` constructor convention as `RootNode`.
2. Add parsing in `Parser::factor()` (follow the `sqrt`/`radical` pattern —
   note `radical` takes two comma-separated arguments; `log` likely does
   too).
3. Add exact evaluation in `NumericEvaluator` (throw if the result isn't
   exactly representable, same as `sqrt`/`radical` do).
4. Add a simplification rule in `Simplifier` if there's an obvious identity
   (`log(b, 1) = 0`, `log(b, b) = 1`, etc.) — guard sign/domain issues the
   same way §7's `0^negative` fix does; don't fold to a "plausible" answer
   without checking the domain first.
5. Add decimal evaluation + step text in `StepEvaluator` and
   `MathFormatter` (add a `Texts::$messages` entry + `StepExplainer` static
   method first, then use it from both places).
6. Add `containsVariable`/`extractLinearCoefficient` handling in
   `LinearSolverTrait` if the new node can appear in solvable equations
   (should almost certainly be treated as "nonlinear if the unknown is
   inside" unless you're specifically implementing a solver for that case).
7. **Write tests for every layer you touched** — the existing suite in
   `tests/` mirrors `src/` 1:1; add `tests/Nodes/LogNodeTest.php`,
   extend `ParserTest`, `NumericEvaluatorTest`, `SimplifierTest`,
   `StepEvaluatorTest`, `MathFormatterTest`, `LinearSolverTraitTest`
   accordingly, following the existing test files as templates.

**General rules of thumb baked into this codebase**, worth preserving:
- Exact arithmetic (`Nodes`, `Services`) always uses GMP with an explicit
  base; decimal/approximate arithmetic (`StepExplainer`) always uses
  bcmath with an explicit scale. Never mix the two within one code path.
- Every domain restriction (division by zero, even root of a negative,
  `0^negative`, zero-degree root) must raise a clear, typed exception —
  never silently return a "close enough" or mathematically wrong value.
- Prefer delegation/composition (`NumericSolver` → `SymbolicSolver`,
  `SymbolicStepEvaluator` observing `Simplifier`) over duplicating logic.
  If you catch yourself copy-pasting a chunk of `Simplifier` or
  `LinearSolverTrait` logic into a new class, stop and factor it out
  instead.

---

## 7. Bug history / lessons learned (read before touching arithmetic code)

These were found and fixed during the audits of this codebase and are worth
knowing before making further changes, since several are the kind of bug
that passes casual testing:

1. **`LinearSolverTrait::containsVariable()` namespace typo.** Checked
   `instanceof \CAS\Nodes\BinaryOperatorNode` (missing the
   `Sobhanmohammadi\` prefix). Since that class doesn't exist, `instanceof`
   silently evaluated `false` for *every* binary-operator subtree, so the
   recursion never looked inside `+ - * / ^`. Equations like `x/(x+1)=2`
   were wrongly accepted as linear and "solved" incorrectly. **Lesson:**
   always `use` node classes at the top of the file; never type a
   fully-qualified class name inline in an `instanceof` check.

2. **`0^(negative)` silently simplified to `0`.** The identity rule
   `0^r → 0` didn't check the sign of `r`; `0^(-2)` is undefined
   (division by zero) but was folding to `0`. Fixed by only applying the
   rule when `r` is strictly positive.

3. **Undeclared `ext-gmp`/`ext-bcmath` in `composer.json`.** The numeric
   core is GMP-based and the explanation layer is bcmath-based, but neither
   extension was declared as a requirement — a fresh install would crash
   at runtime with a confusing fatal error instead of failing at `composer
   install` with a clear message.

4. **Unguarded zero-degree root.** `radical(0, x)` computed
   `bcdiv('1', '0', ...)` with no guard, throwing a raw
   `DivisionByZeroError` (PHP 8+) or misbehaving silently (PHP 7.4)
   instead of the library's normal `RuntimeException` pattern.

5. **Octal misinterpretation of decimal literals.** `gmp_init()`
   auto-detects base from the string; any decimal `< 1` (e.g. `0.25`)
   produces a numerator string starting with `"0"` (`"025"`), which GMP
   parses as *octal* → `21`, not `25`. `0.25` silently became `21/100`.
   **Lesson:** always call `gmp_init($str, 10)` with an explicit base when
   parsing user-facing decimal strings.

6. **Fractional-exponent `bcpow()` for root computation.**
   `bcpow($rad, bcdiv('1', $deg, ...), ...)` was used to approximate nth
   roots, but `bcpow()` has never validly supported a fractional exponent —
   it either truncates it to an integer (silently wrong, PHP < 8) or throws
   `ValueError` (PHP ≥ 8). Replaced with a proper Newton's-method
   `bcNthRoot()` helper in both `StepEvaluator` and `MathFormatter`.

Each of the above has a dedicated regression test in `tests/` — see
`LinearSolverTraitTest`, `SimplifierTest::testZeroToNegativePowerThrows...`,
`StepEvaluatorTest`/`MathFormatterTest::testRootWithZeroDegree...`, and
`NumericNodeTest::testLeadingDotIsHandled` /
`testFractionIsFullyReduced`.

---

## 8. Testing

```bash
composer install     # or: php vendor_autoload.php approach if Packagist is unreachable
phpunit               # runs tests/, mirrors src/ 1:1
```

187 tests / 423 assertions at the time of writing, organized as
`tests/<SameSubnamespaceAs src>/<ClassName>Test.php`. When you add a class
to `src/`, add its test file in the mirrored location before considering
the change done.
