<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use Generator;
use PHPUnit\Framework\TestCase;

class StatementTest extends TestCase
{
    /** @dataProvider providerBindValue */
    public function testBindValue(mixed $expectedValue, mixed $value, ParameterType $type): void
    {
        $wrappedStatement = $this->createMock(DriverStatement::class);
        $statement        = new Statement(
            $this->createMock(Connection::class),
            $wrappedStatement,
            '',
        );

        $param = 'myparam';

        $wrappedStatement->expects(self::once())
            ->method('bindValue')
            ->with($param, $expectedValue, $type);

        $statement->bindValue($param, $value, $type);
    }

    /** @return Generator<string, array{mixed, mixed, ParameterType}> */
    public function providerBindValue(): Generator
    {
        yield 'string' => ['myvalue', 'myvalue', ParameterType::STRING];
        yield 'Enum' => ['foo', StringBackedEnum::FOO, ParameterType::STRING];
    }
}
