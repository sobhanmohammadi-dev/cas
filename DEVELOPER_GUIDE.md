# Developer Guide

This is a from-scratch rewrite of the CAS library, targeting PHP 8.2+, under
the same `Sobhanmohammadi\CAS` namespace. It is **not** backward compatible
with the previous version at the class level -- see `MIGRATION.md` for a
map from old classes/APIs to new ones.

## Design goals

- **Exact arithmetic by default.** All rational arithmetic is exact (GMP
  numerator/denominator), never floating point, unless you explicitly ask
  for a numeric approximation (for transcendental functions like `sin`).
- **Immutability.** Every `Node` and value object (`Rational`,
  `Translatable`, `Step`, ...) is immutable. Transformations return new
  values; nothing is mutated in place. This removes a whole class of
  aliasing bugs and makes step-by-step recording straightforward: a step
  is just "the whole tree before" and "the whole tree after."
- **Small, composable pieces.** Instead of one class per operator/function
  (the old design had `PlusNode`, `MinusNode`, `SinNode`, `CosNode`, ...),
  operators and functions are PHP 8.1 enums (`BinaryOperator`,
  `FunctionKind`) carried by a handful of generic node types
  (`BinaryNode`, `FunctionNode`, ...).
- **Localizable narration, not hardcoded language.** Every step-by-step
  document carries `Translatable` values (a stable key + English default +
  interpolation params) instead of raw strings, so any language can be
  layered on top without touching the library's logic.

## Architecture

```
src/
  Number/          Rational: the single exact-arithmetic value type (GMP-backed)
  Node/             Immutable AST: Node, NumberNode, VariableNode, ConstantNode,
                     BinaryNode, NegateNode, FunctionNode, EquationNode,
                     plus enums BinaryOperator, FunctionKind, ConstantKind
  Lexing/           Lexer, Token, TokenType
  Parsing/          Parser (recursive descent / precedence climbing)
  Evaluation/       ExactEvaluator (rational-only), NumericEvaluator (float,
                     handles transcendental functions), NumericStepEvaluator
                     (narrated, one operation at a time), SymbolTable
  Simplification/   Simplifier + SimplificationRule + Rules/
                     (ConstantFoldingRule, IdentityRule, DistributiveRule,
                     LikeTermsRule)
  Solving/          EquationSolver (constant/linear/quadratic),
                     RadicalEquationSolver (single square-root term),
                     PolynomialCoefficients, Solution, SolvedEquation
  Explain/          Translatable, Step, StepDocument, FinalResult,
                     LocalizationExtractor, Translator,
                     SimplificationTrace
  Exception/        CasException (base) and specific subtypes
  Cas.php           Facade tying the above together for common use cases
```

## Expression grammar

```
equation   := expr ( '=' expr )?
expr       := term ( ('+' | '-') term )*
term       := unary ( ('*' | '/') unary | implicitFactor )*
unary      := '-' unary | '+' unary | power
power      := primary ( '^' unary )?          // right-associative
primary    := NUMBER
            | IDENTIFIER
            | FUNCTION '(' expr (',' expr)* ')'
            | '(' expr ')'
```

Notes:

- Implicit multiplication is supported: `2x`, `2(x+1)`, `x(x+1)`, `3xy`
  (the last splits into `3 * x * y` since `xy` isn't a recognized function).
- Numbers accept decimals and scientific notation: `3.14`, `.5`, `2e10`,
  `1e-3`. Parsing goes straight to an exact `Rational` -- no float
  round-tripping.
- Recognized functions (see `FunctionKind`): `sin, cos, tan, asin, acos,
  atan, atan2, sqrt, root, abs, ln, log, exp`.
- Recognized constants: `pi`, `e`.

## The two evaluators, and when to use which

- **`ExactEvaluator`** only succeeds when the whole expression reduces to
  a rational number using `+ - * / ^` with integer exponents. It throws
  `UnsupportedOperationException` the moment it hits a transcendental
  function or a non-integer exponent. Use this whenever you need an exact
  answer (e.g. checking equation roots).
- **`NumericEvaluator`** always returns a `float`, evaluating trig/log/etc.
  via PHP's math functions. Use this for approximate results.
- **`NumericStepEvaluator`** does the same as `NumericEvaluator` but
  narrates every single operation as a `Step`, in natural precedence order
  (which falls out of the tree shape: tighter-binding operators are always
  nested more deeply, so a simple innermost-first walk visits them in the
  right order). For a positive base raised to a power, it also shows the
  `a^b = e^(b * ln(a))` breakdown with the intermediate `ln_a` and
  `b_times_ln_a` values, mirroring how you'd show the work by hand.

## Simplification rules

`Simplifier` applies a list of `SimplificationRule`s repeatedly
(innermost-first for `simplifyWithSteps()`, or in a single bottom-up sweep
per pass for `simplify()`) until nothing changes:

- `ConstantFoldingRule` -- evaluates any subtree made entirely of numbers.
- `DistributiveRule` -- `a*(b+c) => a*b + a*c` (and the mirror image with
  the sum on the left, and with `-` instead of `+`).
- `LikeTermsRule` -- flattens an additive chain and merges terms that
  share the same non-numeric "base" (a variable, a power, or even a
  function call like `sin(x)`), summing their coefficients.
- `IdentityRule` -- `x+0`, `x*1`, `x*0`, `x^1`, `x^0`, `0^n`, double
  negation, etc.

To customize the rule set, construct `new Simplifier([...])` with your own
list of `SimplificationRule` implementations.

## Solving equations

`EquationSolver::solve()`/`solveWithSteps()` handles:

- **Degree 0** (no variable terms) -- either an identity (`3 = 3`) or a
  contradiction (`3 = 4`).
- **Degree 1** (linear) -- `ax + b = 0 => x = -b/a`.
- **Degree 2** (quadratic) -- via the quadratic formula, exact when the
  discriminant is a perfect square; throws `UnsupportedOperationException`
  for irrational roots (the library only produces exact results).
- **Degree 3+** -- throws `UnsupportedOperationException`.
- **A single square-root term** -- automatically falls back to
  `RadicalEquationSolver`, which isolates the radical, squares both sides,
  solves the resulting polynomial, and **verifies every candidate root**
  by substituting it back into the original equation (squaring can
  introduce extraneous roots, so this step is not optional). Equations
  where the variable appears both inside and outside the radical are
  outside this solver's scope and raise `UnsupportedOperationException`.

## Step-by-step documents and localization

Every step-producing operation returns a `StepDocument` (or, for
simplification, a `SimplificationTrace`; for solving, a `SolvedEquation`
wrapping a `Solution` plus `Step[]`). A `StepDocument`:

```php
final class StepDocument {
    public readonly Translatable $title;
    public readonly string $subject;          // the original expression/equation
    public readonly Translatable $goal;
    public readonly Translatable[] $orderOfOperations;
    public readonly Step[] $steps;
    public readonly FinalResult $finalResult;
    public function toArray(): array;          // plain-array rendering, if you need one
}
```

Every `Step` carries plain-string math (`currentExpression`, `result`,
`updatedExpression`, `calculation`, `details`) alongside `Translatable`
narration (`title`, `rule`, `formula`). A `Translatable` is a stable key +
default English text + interpolation params:

```php
Translatable::of('rule.addition', 'Addition');
Translatable::of('formula.discriminant', 'b^2 - 4ac');
Translatable::of('rule.solve_linear', 'Solve {a}{variable} + {b} = 0 for {variable}', [
    'a' => '2', 'b' => '4', 'variable' => 'x',
]);
```

To localize a document:

```php
$catalog = (new LocalizationExtractor())->extract($document); // key => English text
// ... send $catalog to a translator, get back $persianPack (same keys, Persian text) ...
$translated = (new Translator())->translate($document, $persianPack);
```

Any key missing from the language pack silently falls back to English.
Mathematical content is never touched by translation -- only the keyed
narration fields are.

## Extending the library

- **New simplification rule**: implement `SimplificationRule` (`name():
  Translatable`, `apply(Node): ?Node`), add it to the list passed into
  `new Simplifier([...])` (or add it to the default list in
  `Simplifier::__construct()`).
- **New function**: add a case to `FunctionKind`, teach `NumericEvaluator`,
  `NumericStepEvaluator::applyFunction()`, and (if it should participate in
  exact/polynomial work) `PolynomialCoefficients` about it.
- **New node type**: extend `Node`, implement `__toString()`, `equals()`,
  `children()`, `withChildren()`. Because everything (`Simplifier`,
  `NumericStepEvaluator`, `PolynomialCoefficients`) walks the tree
  generically via `children()`/`withChildren()`, most consumers need no
  changes.

## Running tests

```sh
composer install    # or: composer dump-autoload
phpunit              # PHPUnit 9.6 via `apt install phpunit` if Packagist is unreachable
```

As of this writing: 153 tests, 205 assertions, all passing.
