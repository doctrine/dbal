<?php

namespace Doctrine\Tests\DBAL\Exception;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use PHPUnit_Framework_TestCase;

/**
 * Tests for {@see \Doctrine\DBAL\Exception\InvalidArgumentException}
 *
 * @covers \Doctrine\DBAL\Exception\InvalidArgumentException
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
class InvalidArgumentExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testFromEmptyCriteria()
    {
        $exception = InvalidArgumentException::fromEmptyCriteria();

        $this->assertInstanceOf('Doctrine\DBAL\Exception\InvalidArgumentException', $exception);
        $this->assertSame('Empty criteria was used, expected non-empty criteria', $exception->getMessage());
    }
}
