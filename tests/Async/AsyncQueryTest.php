<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Async;

use Doctrine\DBAL\Async\AsyncQuery;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
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

    public function testFromQueryBuilder(): void
    {
        $connection = $this->createConnectionMock();

        $qb = new QueryBuilder($connection);
        $qb->select('id', 'name')
           ->from('users', 'u')
           ->where('u.id = :id')
           ->andWhere('u.status = :status')
           ->setParameter('id', 42, ParameterType::INTEGER)
           ->setParameter('status', 'active', ParameterType::STRING);

        $asyncQuery = AsyncQuery::fromQueryBuilder($qb);

        self::assertSame($qb->getSQL(), $asyncQuery->getSQL());
        self::assertSame($qb->getParameters(), $asyncQuery->getParams());
        self::assertSame($qb->getParameterTypes(), $asyncQuery->getTypes());
    }

    public function testFromQueryBuilderWithPositionalParams(): void
    {
        $connection = $this->createConnectionMock();

        $qb = new QueryBuilder($connection);
        $qb->select('*')
           ->from('products')
           ->where('price > ?')
           ->setParameter(0, 100, ParameterType::INTEGER);

        $asyncQuery = AsyncQuery::fromQueryBuilder($qb);

        self::assertSame([0 => 100], $asyncQuery->getParams());
        self::assertSame([0 => ParameterType::INTEGER], $asyncQuery->getTypes());
    }

    public function testFromQueryBuilderWithNoParams(): void
    {
        $connection = $this->createConnectionMock();

        $qb = new QueryBuilder($connection);
        $qb->select('COUNT(*)')
           ->from('users');

        $asyncQuery = AsyncQuery::fromQueryBuilder($qb);

        self::assertStringContainsString('SELECT', $asyncQuery->getSQL());
        self::assertSame([], $asyncQuery->getParams());
        self::assertSame([], $asyncQuery->getTypes());
    }

    private function createConnectionMock(): Connection
    {
        $platform = new \Doctrine\DBAL\Platforms\MySQLPlatform();

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return $connection;
    }
}

