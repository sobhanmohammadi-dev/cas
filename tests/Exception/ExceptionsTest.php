<?php
namespace Sobhanmohammadi\CAS\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Sobhanmohammadi\CAS\Exception\MathParseException;
use Sobhanmohammadi\CAS\Exception\SimplifyException;

final class ExceptionsTest extends TestCase
{
    public function testMathParseExceptionIsRuntimeException(): void
    {
        $e = new MathParseException('bad token');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertSame('bad token', $e->getMessage());
    }

    public function testSimplifyExceptionIsRuntimeException(): void
    {
        $e = new SimplifyException('did not converge');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertSame('did not converge', $e->getMessage());
    }
}
