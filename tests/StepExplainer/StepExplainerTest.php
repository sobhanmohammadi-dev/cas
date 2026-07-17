<?php
namespace Sobhanmohammadi\CAS\Tests\StepExplainer;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\StepExplainer\{StepExplainer, StepText, Texts};

final class StepExplainerTest extends TestCase
{
    public function testTextsMessagesIsNonEmptyArray(): void
    {
        $this->assertNotEmpty(Texts::$messages);
        foreach (Texts::$messages as $key => $entry) {
            $this->assertArrayHasKey('en', $entry, "Missing 'en' for key {$key}");
            $this->assertArrayHasKey('fa', $entry, "Missing 'fa' for key {$key}");
        }
    }

    public function testExpressionStartBuildsStepText(): void
    {
        $t = StepExplainer::expressionStart('2 + 2');
        $this->assertInstanceOf(StepText::class, $t);
        $this->assertStringContainsString('2 + 2', $t->getEn());
    }

    public function testUnknownMessageKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Use reflection to call the private build() method with a bogus key.
        $ref = new \ReflectionClass(StepExplainer::class);
        $method = $ref->getMethod('build');
        $method->setAccessible(true);
        $method->invoke(null, 'this_key_does_not_exist', []);
    }

    public function testPlaceholderReplacement(): void
    {
        $t = StepExplainer::variableSubstitution('x', '5');
        $this->assertStringContainsString('x', $t->getEn());
        $this->assertStringContainsString('5', $t->getEn());
        // No leftover unreplaced placeholders.
        $this->assertStringNotContainsString('{varName}', $t->getEn());
        $this->assertStringNotContainsString('{valFmt}', $t->getEn());
    }

    public function testAlgebraicRuleAppliedFormatsBeforeAndAfter(): void
    {
        $t = StepExplainer::algebraicRuleApplied('identity', 'x + 0', 'x');
        $this->assertStringContainsString('x + 0', $t->getEn() . $t->getFormula() . $t->getCalculation());
    }

    public function testErrorDivisionByZero(): void
    {
        $t = StepExplainer::errorDivisionByZero();
        $this->assertInstanceOf(StepText::class, $t);
        $this->assertNotEmpty($t->getEn());
    }

    public function testFinalExpressionResult(): void
    {
        $t = StepExplainer::finalExpressionResult('42');
        $this->assertStringContainsString('42', $t->getEn());
    }
}
