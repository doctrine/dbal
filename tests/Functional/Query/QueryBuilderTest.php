<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Query;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MariaDb1043Platform;
use Doctrine\DBAL\Platforms\MariaDb1052Platform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;
use Exception;
use Throwable;

use function get_class;
use function sprintf;

final class QueryBuilderTest extends FunctionalTestCase
{
    private function platformSupportsLocks(AbstractPlatform $platform): bool
    {
        return ! $platform instanceof DB2Platform
            && ! $platform instanceof MariaDb1027Platform
            && ! $platform instanceof MariaDb1043Platform
            && ! $platform instanceof MariaDb1052Platform
            && ! $platform instanceof MySQL57Platform
            && ! $platform instanceof PostgreSQL94Platform
            && ! $platform instanceof OraclePlatform
            && ! $platform instanceof SqlitePlatform;
    }

    public function testConcurrentConnectionSkipsLockedRows(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (! $this->platformSupportsLocks($platform)) {
            self::markTestSkipped(
                sprintf('Skipping, because platform %s does not support locks', get_class($platform)),
            );
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
        $qb1->select('u.id')
            ->from('users', 'u')
            ->orderBy('id', 'ASC')
            ->setMaxResults(1)
            ->lockForUpdate()
            ->skipLocked();

        self::assertFalse($connection1->isTransactionActive(), 'A transaction should not be active on connection 1');
        $connection1->beginTransaction();
        self::assertTrue($connection1->isTransactionActive(), 'A transaction should be active on connection 1');
        try {
            $result = $connection1->executeQuery($qb1->getSQL());
        } catch (Throwable $t) {
            throw new Exception(
                'Failed SQL with SKIP LOCKED - platform: ' . get_class($connection1->getDatabasePlatform())
                . ' DB version:' . $connection1->getDatabasePlatformVersion(),
                0,
                $t,
            );
        }

        $resultList = $result->fetchAllAssociative();
        self::assertCount(1, $resultList);
        self::assertEquals(1, $resultList[0]['id']);

        $connection2 = TestUtil::getConnection();
        self::assertTrue(
            $connection1 !== $connection2,
            "The two competing connections must be different, but they are the same so we can't run this test with it.",
        );
        self::assertFalse($connection2->isTransactionActive(), 'A transaction should not be active on connection 2');

        $qb2 = new QueryBuilder($connection2);
        $qb2->select('u.id')
            ->from('users', 'u')
            ->orderBy('id', 'ASC')
            ->setMaxResults(1)
            ->lockForUpdate()
            ->skipLocked();

        self::assertTrue($connection1->isTransactionActive(), 'A transaction should still be active on connection 1');
        $result     = $connection2->executeQuery($qb2->getSQL());
        $resultList = $result->fetchAllAssociative();
        self::assertCount(1, $resultList);
        self::assertEquals(2, $resultList[0]['id']);

        $connection1->commit();
        self::assertFalse(
            $connection1->isTransactionActive(),
            'A transaction should not be active anymore on connection 1',
        );
        $result     = $connection2->executeQuery($qb2->getSQL());
        $resultList = $result->fetchAllAssociative();
        self::assertCount(1, $resultList);
        self::assertEquals(1, $resultList[0]['id']);
    }
}
