<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Query;

use Doctrine\DBAL\Platforms\DB2111Platform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MariaDb1060Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;

final class QueryBuilderTest extends FunctionalTestCase
{
    public function testConcurrentConnectionSkipsLockedRows(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof DB2Platform && ! $platform instanceof DB2111Platform) {
            self::markTestSkipped('Skipping on IBM DB2 older than 11.1');
        }

        if ($platform instanceof MariaDBPlatform && ! $platform instanceof MariaDb1060Platform) {
            self::markTestSkipped('Skipping on MariaDB older than 10.6');
        }

        if ($platform instanceof MySQLPlatform && ! $platform instanceof MySQL80Platform) {
            self::markTestSkipped('Skipping on MySQL older than 8.0');
        }

        if ($platform instanceof PostgreSQLPlatform && ! $platform instanceof PostgreSQL100Platform) {
            self::markTestSkipped('Skipping on PostgreSQL older than 10.0');
        }

        if ($platform instanceof SqlitePlatform) {
            self::markTestSkipped('Skipping on SQLite');
        }

        if (TestUtil::isDriverOneOf('oci8')) {
            // DBAL uses oci_connect() which won't necessarily start a new session, and there is
            // no API to make it use oci_new_connect(). The feature is still covered via pdo_oci.
            self::markTestSkipped('Skipping on oci8');
        }

        $tableName = 'users';
        $table     = new Table($tableName);
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('nickname', Types::STRING);
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);
        $this->connection->insert($tableName, ['nickname' => 'aaa']);
        $this->connection->insert($tableName, ['nickname' => 'bbb']);

        $connection1 = $this->connection;
        $qb1         = new QueryBuilder($connection1);
        $qb1->select('id')
            ->from('users')
            ->where('id = 1')
            ->lockForUpdate();

        self::assertFalse($connection1->isTransactionActive(), 'A transaction should not be active on connection 1');
        $connection1->beginTransaction();
        self::assertTrue($connection1->isTransactionActive(), 'A transaction should be active on connection 1');

        self::assertEquals([1], $qb1->fetchFirstColumn());

        $connection2 = TestUtil::getConnection();
        self::assertFalse($connection2->isTransactionActive(), 'A transaction should not be active on connection 2');

        $qb2 = new QueryBuilder($connection2);
        $qb2->select('id')
            ->from('users')
            ->orderBy('id')
            ->lockForUpdate()
            ->skipLocked();

        self::assertTrue($connection1->isTransactionActive(), 'A transaction should still be active on connection 1');
        self::assertEquals([2], $qb2->fetchFirstColumn());

        $connection1->commit();
        self::assertFalse(
            $connection1->isTransactionActive(),
            'A transaction should not be active anymore on connection 1',
        );
        self::assertEquals([1, 2], $qb2->fetchFirstColumn());
    }
}
