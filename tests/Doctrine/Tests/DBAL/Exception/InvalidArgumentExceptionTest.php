<?php

namespace Doctrine\Tests\DBAL\Exception;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see \Doctrine\DBAL\Exception\InvalidArgumentException}
 *
 * @covers \Doctrine\DBAL\Exception\InvalidArgumentException
 */
class InvalidArgumentExceptionTest extends TestCase
{
    public function testFromEmptyCriteria() : void
    {
        $exception = InvalidArgumentException::fromEmptyCriteria();

        self::assertInstanceOf(InvalidArgumentException::class, $exception);
        self::assertSame('Empty criteria was used, expected non-empty criteria', $exception->getMessage());
    }
}
