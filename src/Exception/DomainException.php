<?php
namespace Sobhanmohammadi\CAS\Exception;

/**
 * A value fell outside the mathematical domain of the operation being
 * applied to it — e.g. sqrt() of a negative number under an even root,
 * asin()/acos() of a value outside [-1, 1], 0^(negative), atan2(0, 0).
 *
 * Named to mirror the built-in \DomainException but kept inside this
 * library's own namespace/hierarchy so callers can catch it alongside
 * every other typed CAS exception via CasException, without also catching
 * unrelated \DomainException instances thrown by other libraries.
 */
class DomainException extends CasException
{
}
