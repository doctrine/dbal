<?php

namespace Doctrine\Tests\DBAL\Exception;

use Doctrine\DBAL\Exception\InvalidArgumentException;

/**
 * Tests for {@see \Doctrine\DBAL\Exception\InvalidArgumentException}
 *
 * @covers \Doctrine\DBAL\Exception\InvalidArgumentException
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class InvalidArgumentExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testFromEmptyCriteria()
    {
        $exception = InvalidArgumentException::fromEmptyCriteria();

        self::assertInstanceOf('Doctrine\DBAL\Exception\InvalidArgumentException', $exception);
        self::assertSame('Empty criteria was used, expected non-empty criteria', $exception->getMessage());
    }
}
