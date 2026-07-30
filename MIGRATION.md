# Migration Guide: old CAS -> rewritten CAS

This is a ground-up rewrite. The public API is **not** backward
compatible. This guide maps the old classes/concepts to their new
equivalents.

## Namespace

Unchanged: `Sobhanmohammadi\CAS\...`. Sub-namespaces have changed (see
below), so `use` statements will need updating.

## Nodes

The old design had one class per operator/function under `Nodes\`. The
new design consolidates these into a handful of node types plus enums,
under `Node\` (singular):

| Old class(es)                                                        | New equivalent |
|-----------------------------------------------------------------------|----------------|
| `IntegerNode`, `RationalNode`, `NumericNode`                          | `Node\NumberNode` wrapping `Number\Rational` |
| `PlusNode`, `MinusNode`, `MultiplyNode`, `DivideNode`, `PowerNode`, `BinaryOperatorNode` | `Node\BinaryNode` + `Node\BinaryOperator` enum |
| `UnaryNode`                                                            | `Node\NegateNode` |
| `VariableNode`                                                        | `Node\VariableNode` (same name, new namespace) |
| `PiNode`                                                               | `Node\ConstantNode` + `Node\ConstantKind::Pi` |
| `SinNode`, `CosNode`, `TanNode`, `AsinNode`, `AtanNode`, `Atan2Node`, `TrigFunctionNode`, `SqrtNode`, `RootNode` | `Node\FunctionNode` + `Node\FunctionKind` enum |
| `EquationNode`                                                         | `Node\EquationNode` (same name, new namespace) |
| `ComplexNode`                                                          | Not carried forward -- the new library is real-valued only |
| `AssignmentNode`                                                       | Removed (was dead code in the old library) |
| `MathNode` (base class)                                                | `Node\Node` (abstract base) |

All new nodes are **immutable**: transformations return new nodes rather
than mutating in place.

## Parsing

| Old | New |
|-----|-----|
| `Parser\Lexer`, `Parser\Token` | `Lexing\Lexer`, `Lexing\Token`, `Lexing\TokenType` |
| `Parser\Parser` | `Parsing\Parser` |

Usage is similar: `(new Parser())->parse($expression)` returns a `Node`
(possibly an `EquationNode` if the input contains `=`).

## Evaluation

| Old | New |
|-----|-----|
| `Services\NumericEvaluator` | `Evaluation\NumericEvaluator` (float-based, same role) |
| `Services\SymbolicEvaluator` | `Evaluation\ExactEvaluator` (exact-rational; throws `UnsupportedOperationException` for anything transcendental instead of falling back silently) |
| `Services\SymbolTable` | `Evaluation\SymbolTable` (now immutable: `with($name, $value)` returns a new table) |
| *(none)* | `Evaluation\NumericStepEvaluator` -- new: narrated, one-operation-at-a-time evaluation |

## Simplification

| Old | New |
|-----|-----|
| `Services\Simplifier` | `Simplification\Simplifier` |
| `Services\SimplifierObserver` | `Simplification\SimplificationRule` interface, implementations in `Simplification\Rules\` |

The old `SimplifierObserver` duplication is gone; each rule is now an
independent, individually testable class (`ConstantFoldingRule`,
`IdentityRule`, `DistributiveRule`, `LikeTermsRule`). The last two
(algebraic expansion and like-term collection) are new capabilities that
did not exist in the old library.

## Solving

| Old | New |
|-----|-----|
| `Services\SymbolicSolver`, `Services\NumericSolver`, `Services\LinearSolverTrait`, `Services\QuadraticSolverTrait`, `Services\QuadraticRoot` | `Solving\EquationSolver` (linear + quadratic), `Solving\PolynomialCoefficients` (shared coefficient-extraction logic, replacing the old trait duplication), `Solving\Solution` |
| *(none)* | `Solving\RadicalEquationSolver` -- new: solves equations with a single square-root term, with automatic verification by substitution |

`Solution` no longer represents complex roots (the library dropped complex
number support); `hasNoRealSolution` and `isIdentity` flags replace
whatever ad hoc signaling the old solver used.

## Step-by-step explanations

This is the area with the biggest conceptual change.

| Old | New |
|-----|-----|
| `StepExplainer\StepExplainer`, `StepExplainer\StepSolver`, `StepExplainer\SymbolicStepSolver` (now merged), `StepExplainer\SymbolicStepEvaluator`, `StepExplainer\StepEvaluator` | `Simplifier::simplifyWithSteps()`, `EquationSolver::solveWithSteps()`, `NumericStepEvaluator::evaluateWithSteps()` |
| `StepExplainer\MathFormatter` | `Explain\StepDocument` (+ `toArray()` for a plain-array rendering) |
| `StepExplainer\StepRecorder` (mutable, `spl_object_id`-keyed state tracking) | Not needed -- since the tree is immutable, a step is simply `(tree before, tree after)`; no external state tracking required |
| `StepExplainer\StepText`, `StepExplainer\Texts` (hardcoded English/Persian strings) | `Explain\Translatable` (key + English default + params) plus `Explain\LocalizationExtractor` / `Explain\Translator` for applying any language pack at runtime -- Persian is no longer hardcoded into the library; you supply a language pack |

### Old MathFormatter-style usage:

```php
$formatter = new MathFormatter($symbolTable, 'fa'); // language baked in
$result = $formatter->format($expression);           // returns array, Persian strings baked in
```

### New equivalent:

```php
$cas = new Cas();
$doc = $cas->evaluateNumericWithSteps($expression, $symbolTable); // English by default
$array = $doc->toArray();                                          // if you want a plain array

// To localize:
$catalog = $cas->extractLocalizationCatalog($doc);   // key => English text
// ... obtain a Persian translation of $catalog from wherever you like ...
$translatedDoc = $cas->translate($doc, $persianPack);
```

This decouples the library from any specific language and lets you swap
in new languages without code changes.

## Exceptions

Exception class names and the `Exception\CasException` base type are
unchanged in spirit, just re-namespaced consistently under `Exception\`.
All old exception types (`DivisionByZeroException`, `DomainException`,
`MathParseException`, `InvalidExpressionException`,
`UnsupportedOperationException`, `UnboundVariableException`,
`SimplifyException`) are still present with the same names.

## Frontend integration (`calculate.php` or similar)

The uploaded project did not include a `calculate.php`; if you have one,
update it to:

1. Point the namespace at `Sobhanmohammadi\CAS\` (only casing, unchanged).
2. Replace direct `StepExplainer`/`MathFormatter` usage with the `Cas`
   facade's `*WithSteps()` methods, or the underlying
   `Simplifier`/`EquationSolver`/`NumericStepEvaluator` classes directly.
3. If you serialize step output as JSON for a frontend, call
   `$document->toArray()` before `json_encode()`.

See `examples/usage.php` for a complete runnable tour of the new API.

## What was intentionally dropped

- Complex number support (`ComplexNode`) -- the rewrite is real-valued
  only; irrational/complex quadratic roots now raise
  `UnsupportedOperationException` rather than being represented
  approximately.
- `AssignmentNode` and other dead code identified during the original
  audit.
- Hardcoded Persian strings inside the library -- replaced by the
  translation-key system described above, which supports any language.
