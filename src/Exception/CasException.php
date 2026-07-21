<?php
namespace Sobhanmohammadi\CAS\Exception;

/**
 * Base type for every typed exception raised by this library.
 *
 * Extends \RuntimeException so any existing call site that catches the
 * plain \RuntimeException (the convention used throughout this codebase
 * before this hierarchy existed) keeps working unmodified. New call sites
 * should throw the most specific subclass below instead of a bare
 * \RuntimeException so callers can reliably classify failures without
 * parsing error message strings.
 */
abstract class CasException extends \RuntimeException
{
}
