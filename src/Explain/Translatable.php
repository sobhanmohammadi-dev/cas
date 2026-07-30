<?php

declare(strict_types=1);

namespace Sobhanmohammadi\CAS\Explain;

/**
 * A single piece of user-facing narration: a stable key (for translators),
 * the default English text, and any interpolation parameters used inside
 * the text (e.g. "{a} + {b}" with params ['a' => '2', 'b' => '3']).
 *
 * Only Translatable values carry human language; every mathematical value
 * in a StepDocument (expressions, numbers, variable names) is stored as
 * plain PHP strings/objects and is never touched by translation.
 */
final class Translatable
{
    /** @param array<string,string> $params */
    public function __construct(
        public readonly string $key,
        public readonly string $defaultText,
        public readonly array $params = [],
    ) {
    }

    public static function of(string $key, string $defaultText, array $params = []): self
    {
        return new self($key, $defaultText, $params);
    }

    /** Renders defaultText with {param} placeholders substituted. */
    public function render(): string
    {
        return $this->renderWith($this->defaultText);
    }

    public function renderWith(string $template): string
    {
        $replacements = [];
        foreach ($this->params as $name => $value) {
            $replacements['{' . $name . '}'] = $value;
        }
        return strtr($template, $replacements);
    }

    public function withText(string $text): self
    {
        return new self($this->key, $text, $this->params);
    }
}
