<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Query;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Types;

final class QueryBuilderBoolTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('for_update');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('b1', Types::BOOLEAN, ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('for_update', ['id' => 1, 'b1' => true]);
        $this->connection->insert('for_update', ['id' => 2, 'b1' => false]);
    }

    protected function tearDown(): void
    {
        if (! $this->connection->isTransactionActive()) {
            return;
        }

        $this->connection->rollBack();
    }

    public function testDeleteBooleanTrue(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped('Skipping on SQLite');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->delete('for_update')
            ->where($qb1->expr()->eq('b1', $qb1->createNamedParameter(true, ParameterType::BOOLEAN)))
            ->executeStatement();

        $qb2 = $this->connection->createQueryBuilder();
        $qb2->select('id')
            ->from('for_update');

        self::assertEquals([2], $qb2->fetchFirstColumn());
    }

    public function testDeleteBooleanTrueWithWrongType(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped('Skipping on SQLite');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->delete('for_update')
            ->where($qb1->expr()->eq('b1', $qb1->createNamedParameter(true, Types::BOOLEAN)))
            ->executeStatement();

        $qb2 = $this->connection->createQueryBuilder();
        $qb2->select('id')
            ->from('for_update');

        self::assertEquals([2], $qb2->fetchFirstColumn());
    }

    public function testDeleteBooleanFalse(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped('Skipping on SQLite');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->delete('for_update')
            ->where($qb1->expr()->eq('b1', $qb1->createNamedParameter(false, ParameterType::BOOLEAN)))
            ->executeStatement();

        $qb2 = $this->connection->createQueryBuilder();
        $qb2->select('id')
            ->from('for_update');

        self::assertEquals([1], $qb2->fetchFirstColumn());
    }

    public function testDeleteBooleanFalseWithWrongType(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped('Skipping on SQLite');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->delete('for_update')
            ->where($qb1->expr()->eq('b1', $qb1->createNamedParameter(false, Types::BOOLEAN)))
            ->executeStatement();

        $qb2 = $this->connection->createQueryBuilder();
        $qb2->select('id')
            ->from('for_update');

        self::assertEquals([1], $qb2->fetchFirstColumn());
    }
}
