<?php
/**
 * MathFormatter usage example.
 * Run: php example_formatter.php
 */
require __DIR__ . '/vendor/autoload.php';

use Sobhanmohammadi\CAS\Services\SymbolTable;
use Sobhanmohammadi\CAS\StepExplainer\MathFormatter;

$expressions = [
    ['expr' => '((2^3 * sqrt(36)) / 4) + 5^2', 'lang' => 'en'],
    ['expr' => '(3^2 + sqrt(49)) * 4 / 2',     'lang' => 'en'],
    ['expr' => '(6^2 / 3) + sqrt(64) - 5',     'lang' => 'en'],
    ['expr' => '(3^2 + sqrt(49)) * 4 / 2',     'lang' => 'fa'],
    ['expr' => '(6^2 / 3) + sqrt(64) - 5',     'lang' => 'fa'],
    ['expr' => '((2^3 * sqrt(36)) / 4) + 5^2', 'lang' => 'fa'],
];

foreach ($expressions as $i => $item) {
    $sym    = new SymbolTable();
    $fmt    = new MathFormatter($sym, $item['lang']);
    $result = $fmt->format($item['expr']);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
}
