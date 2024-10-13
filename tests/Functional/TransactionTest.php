<?php

namespace Doctrine\DBAL\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use PDOException;

use function sleep;

class TransactionTest extends FunctionalTestCase
{
    public function testCommitFalse(): void
    {
        if (! $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->markTestSkipped('Restricted to MySQL.');
        }

        $this->connection->executeStatement('SET SESSION wait_timeout=1');

        self::assertTrue($this->connection->beginTransaction());

        sleep(2); // during the sleep mysql will close the connection

        try {
            self::assertFalse(@$this->connection->commit()); // we will ignore `MySQL server has gone away` warnings
        } catch (PDOException $e) {
            self::assertInstanceOf(DriverException::class, $e);

            /* For PDO, we are using ERRMODE EXCEPTION, so this catch should be
             * necessary as the equivalent of the error control operator above.
             * This seems to be the case only since PHP 8 */
        } finally {
            $this->connection->close();
        }
    }

    public function testNestedTransactionWalkthrough(): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof OraclePlatform) {
            $query = 'SELECT 1 FROM DUAL';
        } elseif ($platform instanceof DB2Platform) {
            $query = 'SELECT 1 FROM sysibm.sysdummy1';
        } else {
            $query = 'SELECT 1';
        }

        $result = $this->connection->transactional(
            static fn (Connection $connection) => $connection->transactional(
                static fn (Connection $connection) => $connection->fetchOne($query),
            ),
        );

        self::assertSame('1', (string) $result);
    }
}
