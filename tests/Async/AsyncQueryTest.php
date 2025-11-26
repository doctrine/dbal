<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Async;

use Doctrine\DBAL\Async\AsyncQuery;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

class AsyncQueryTest extends TestCase
{
    public function testConstructorWithSQLOnly(): void
    {
        $query = new AsyncQuery('SELECT * FROM users');

        self::assertSame('SELECT * FROM users', $query->getSQL());
        self::assertSame([], $query->getParams());
        self::assertSame([], $query->getTypes());
    }

    public function testConstructorWithPositionalParams(): void
    {
        $query = new AsyncQuery(
            'SELECT * FROM users WHERE id = ? AND status = ?',
            [1, 'active']
        );

        self::assertSame('SELECT * FROM users WHERE id = ? AND status = ?', $query->getSQL());
        self::assertSame([1, 'active'], $query->getParams());
        self::assertSame([], $query->getTypes());
    }

    public function testConstructorWithNamedParams(): void
    {
        $query = new AsyncQuery(
            'SELECT * FROM users WHERE id = :id AND status = :status',
            ['id' => 1, 'status' => 'active']
        );

        self::assertSame('SELECT * FROM users WHERE id = :id AND status = :status', $query->getSQL());
        self::assertSame(['id' => 1, 'status' => 'active'], $query->getParams());
    }

    public function testConstructorWithTypes(): void
    {
        $query = new AsyncQuery(
            'SELECT * FROM users WHERE id = ?',
            [1],
            [ParameterType::INTEGER]
        );

        self::assertSame([ParameterType::INTEGER], $query->getTypes());
    }

    public function testImmutability(): void
    {
        $params = [1, 'test'];
        $types  = [ParameterType::INTEGER, ParameterType::STRING];

        $query = new AsyncQuery('SELECT 1', $params, $types);

        // Modifying original arrays should not affect the query
        $params[0] = 999;
        $types[0]  = ParameterType::BINARY;

        self::assertSame([1, 'test'], $query->getParams());
        self::assertSame([ParameterType::INTEGER, ParameterType::STRING], $query->getTypes());
    }
}

