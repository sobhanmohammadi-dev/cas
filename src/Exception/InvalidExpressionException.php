<?php
namespace Sobhanmohammadi\CAS\Exception;

/**
 * A syntactically or structurally invalid expression: malformed input that
 * isn't specifically a lexer/parser failure (those still throw
 * MathParseException) but is nonetheless not a valid, evaluable expression
 * for the operation being attempted — e.g. an equation with no unknown
 * present, or a node type an equation solver has no rule for.
 */
class InvalidExpressionException extends CasException
{
}
