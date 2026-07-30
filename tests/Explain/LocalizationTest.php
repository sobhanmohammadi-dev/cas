<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Tests\Explain;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Explain\FinalResult;
use Sobhanmohammadi\CAS\Explain\LocalizationExtractor;
use Sobhanmohammadi\CAS\Explain\Step;
use Sobhanmohammadi\CAS\Explain\StepDocument;
use Sobhanmohammadi\CAS\Explain\Translatable;
use Sobhanmohammadi\CAS\Explain\Translator;

final class LocalizationTest extends TestCase
{
    private function sampleDocument(): StepDocument
    {
        $step = new Step(
            title: Translatable::of('step.expand', 'Expand the Parentheses'),
            currentExpression: '2(x + 3)',
            rule: Translatable::of('rule.distributive', 'Distributive Property'),
            result: '2x + 6',
            updatedExpression: '2x + 6',
            targetExpression: '2(x + 3)',
            formula: Translatable::of('formula.distributive', 'a({b} + {c}) = a{b} + a{c}', ['b' => 'x', 'c' => '3']),
        );

        return new StepDocument(
            Translatable::of('doc.title.simplify', 'Step-by-Step Symbolic Simplification'),
            '2(x + 3) - 5',
            Translatable::of('doc.goal.simplify', 'Simplify the expression'),
            [Translatable::of('order.expand', 'Expand parentheses')],
            [$step],
            new FinalResult(expression: '2x + 1'),
        );
    }

    public function testTranslatableRendersWithParams(): void
    {
        $t = Translatable::of('formula.add', '{a} + {b}', ['a' => '2', 'b' => '3']);
        self::assertSame('2 + 3', $t->render());
    }

    public function testToArrayProducesPlainStructure(): void
    {
        $array = $this->sampleDocument()->toArray();
        self::assertSame('Step-by-Step Symbolic Simplification', $array['title']);
        self::assertSame('Distributive Property', $array['steps'][0]['rule']);
        self::assertSame('a(x + 3) = ax + a3', $array['steps'][0]['formula']);
    }

    public function testExtractorCollectsAllKeys(): void
    {
        $catalog = (new LocalizationExtractor())->extract($this->sampleDocument());

        self::assertSame([
            'doc.title.simplify' => 'Step-by-Step Symbolic Simplification',
            'doc.goal.simplify' => 'Simplify the expression',
            'order.expand' => 'Expand parentheses',
            'step.expand' => 'Expand the Parentheses',
            'rule.distributive' => 'Distributive Property',
            'formula.distributive' => 'a({b} + {c}) = a{b} + a{c}',
        ], $catalog);
    }

    public function testTranslatorAppliesLanguagePack(): void
    {
        $translated = (new Translator())->translate($this->sampleDocument(), [
            'step.expand' => 'باز کردن پرانتز',
            'rule.distributive' => 'خاصیت پخشی',
        ]);

        self::assertSame('باز کردن پرانتز', $translated->steps[0]->title->render());
        self::assertSame('خاصیت پخشی', $translated->steps[0]->rule->render());
    }

    public function testTranslatorLeavesUnknownKeysAsDefaultEnglish(): void
    {
        $translated = (new Translator())->translate($this->sampleDocument(), [
            'step.expand' => 'باز کردن پرانتز',
        ]);

        // rule.distributive was not in the pack, so it stays English.
        self::assertSame('Distributive Property', $translated->steps[0]->rule->render());
    }

    public function testTranslatorNeverTouchesMathematicalContent(): void
    {
        $translated = (new Translator())->translate($this->sampleDocument(), [
            'step.expand' => 'باز کردن پرانتز',
            'rule.distributive' => 'خاصیت پخشی',
        ]);

        self::assertSame('2(x + 3)', $translated->steps[0]->currentExpression);
        self::assertSame('2x + 6', $translated->steps[0]->result);
        self::assertSame('2x + 1', $translated->finalResult->expression);
    }

    public function testTranslatorSubstitutesParamsEvenWithoutTranslation(): void
    {
        $translated = (new Translator())->translate($this->sampleDocument(), []);
        self::assertSame('a(x + 3) = ax + a3', $translated->steps[0]->formula->render());
    }
}
