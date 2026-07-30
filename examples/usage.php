<?php

/**
 * A tour of the library's main capabilities.
 * Run: php examples/usage.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Sobhanmohammadi\CAS\Cas;

$cas = new Cas();

function section(string $title): void
{
    echo "\n=== {$title} ===\n";
}

// --- Simplification -------------------------------------------------------
section('Simplify (expansion + like-term collection)');
echo (string) $cas->simplify('2(x + 3) + 4(x - 1) - 5') . "\n"; // (6 * x) - 3

section('Simplify with step-by-step narration');
$trace = $cas->simplifyWithSteps('2(x + 3) + 4(x - 1) - 5');
foreach ($trace->steps as $step) {
    echo "  {$step->rule->render()}: {$step->currentExpression} => {$step->updatedExpression}\n";
}
echo "  Result: {$trace->result}\n";

// --- Exact and numeric evaluation -----------------------------------------
section('Exact evaluation (rational arithmetic)');
echo (string) $cas->evaluateExact('2/3 + 1/6') . "\n"; // 5/6

section('Numeric evaluation with step-by-step narration');
$doc = $cas->evaluateNumericWithSteps('2 + sqrt(9 * 2 - (2^(0.8271 + 1) / 12)) - 19 / 1.263');
foreach ($doc->steps as $step) {
    echo "  {$step->rule->render()}: {$step->targetExpression} => {$step->result}\n";
}
echo "  Final result: {$doc->finalResult->expression}\n";

// --- Equation solving -------------------------------------------------------
section('Solve a quadratic equation, with steps');
$solved = $cas->solveForWithSteps('x^2 - 5x + 6 = 0', 'x');
foreach ($solved->steps as $step) {
    echo "  {$step->rule->render()}: {$step->updatedExpression}\n";
}
echo '  Roots: ' . implode(', ', array_map(strval(...), $solved->solution->roots)) . "\n";

section('Solve a radical equation, with steps and verification');
$solvedRadical = $cas->solveForWithSteps('2 + sqrt(9x - 5) = 11', 'x');
foreach ($solvedRadical->steps as $step) {
    echo "  {$step->rule->render()}: {$step->updatedExpression}\n";
}
echo '  Roots: ' . implode(', ', array_map(strval(...), $solvedRadical->solution->roots)) . "\n";

// --- Localization -----------------------------------------------------------
section('Localization: extract catalog, then apply a Persian language pack');
$catalog = $cas->extractLocalizationCatalog($doc);
echo '  Extracted ' . count($catalog) . " translatable keys, e.g.:\n";
foreach (array_slice($catalog, 0, 3, preserve_keys: true) as $key => $englishText) {
    echo "    {$key} => \"{$englishText}\"\n";
}

// A language pack only needs to cover the keys you want translated; any
// key you omit falls back to the English default automatically.
$persianPack = [
    'rule.addition' => 'جمع',
    'rule.subtraction' => 'تفریق',
    'rule.multiplication' => 'ضرب',
    'rule.division' => 'تقسیم',
    'rule.square_root' => 'جذر',
    'rule.exponentiation' => 'توان‌رسانی',
    'doc.title.numeric_evaluation' => 'محاسبه گام به گام یک عبارت ریاضی',
    'doc.goal.numeric_evaluation' => 'ارزیابی عبارت',
];
$translated = $cas->translate($doc, $persianPack);
echo "  Translated title: {$translated->title->render()}\n";
foreach ($translated->steps as $step) {
    echo "    {$step->rule->render()}: {$step->targetExpression} => {$step->result}\n";
}

echo "\nDone.\n";
