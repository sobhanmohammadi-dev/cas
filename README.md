# Sobhanmohammadi\CAS

A modern, exact-arithmetic Computer Algebra System for PHP 8.2+, with
step-by-step, localizable explanations and zero runtime dependencies
(besides `ext-gmp`).

- **Exact by default.** Rational arithmetic backed by GMP; no float drift
  unless you ask for it.
- **Immutable AST.** Every node and value object is immutable.
- **Step-by-step everything.** Simplification, numeric evaluation, and
  equation solving can all produce a fully narrated `StepDocument`.
- **Localizable, not hardcoded.** Narration is keyed (`Translatable`), so
  any language can be layered on top at runtime -- see
  `Explain\LocalizationExtractor` / `Explain\Translator`.

See `DEVELOPER_GUIDE.md` for architecture and extension points, and
`MIGRATION.md` if you're coming from the previous version of this
library. `examples/usage.php` is a runnable tour of the API.

## Install

```sh
composer install
```

## Quick start

```php
use Sobhanmohammadi\CAS\Cas;

$cas = new Cas();

$cas->simplify('2(x + 3) + 4(x - 1) - 5');        // (6 * x) - 3
$cas->evaluateExact('2/3 + 1/6');                  // 5/6
$cas->solveFor('x^2 - 5x + 6 = 0', 'x')->roots;    // [3, 2]
$cas->solveFor('2 + sqrt(9x - 5) = 11', 'x')->roots; // [86/9]

$doc = $cas->evaluateNumericWithSteps('2 + sqrt(9*2 - 2^1.8271/12) - 19/1.263');
foreach ($doc->steps as $step) {
    echo "{$step->rule->render()}: {$step->targetExpression} => {$step->result}\n";
}
```

## Tests

```sh
phpunit   # 153 tests, 205 assertions
```
