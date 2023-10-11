<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\LockMode;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;

class NoneTest extends FunctionalTestCase
{
    private Connection $connection2;

    public function setUp(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLServerPlatform) {
            // Use row versioning instead of locking on SQL Server (if we don't, the second connection will block when
            // attempting to read the row created by the first connection, instead of reading the previous version);
            // for some reason we cannot set READ_COMMITTED_SNAPSHOT ON when not running this test in isolation,
            // there may be another connection active at this point; temporarily forcing to SINGLE_USER does the trick.
            $db = $this->connection->getDatabase();
            $this->connection->executeStatement('ALTER DATABASE ' . $db . ' SET SINGLE_USER WITH ROLLBACK IMMEDIATE');
            $this->connection->executeStatement('ALTER DATABASE ' . $db . ' SET READ_COMMITTED_SNAPSHOT ON');
            $this->connection->executeStatement('ALTER DATABASE ' . $db . ' SET MULTI_USER');
        }

        $table = new Table('users');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $params = TestUtil::getConnectionParams();

        if (TestUtil::isDriverOneOf('oci8')) {
            $params['driverOptions']['exclusive'] = true;
        }

        $this->connection2 = DriverManager::getConnection($params);

        if ($this->connection2->getSchemaManager()->tablesExist('users')) {
            return;
        }

        if ($this->connection2->getDatabasePlatform() instanceof SqlitePlatform) {
            self::markTestSkipped('This test cannot run on SQLite using an in-memory database');
        }

        self::fail('Separate connections do not seem to talk to the same database');
    }

    protected function tearDown(): void
    {
        $this->connection2->close();

        if (! $this->connection->getDatabasePlatform() instanceof SQLServerPlatform) {
            return;
        }

        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $db = $this->connection->getDatabase();
        $this->connection->executeStatement('ALTER DATABASE ' . $db . ' SET READ_COMMITTED_SNAPSHOT OFF');
    }

    public function testLockModeNoneDoesNotBreakTransactionIsolation(): void
    {
        try {
            $this->connection->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);
            $this->connection2->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);
        } catch (Exception $e) {
            self::markTestSkipped('This test must be able to set a transaction isolation level');
        }

        $this->connection->beginTransaction();
        $this->connection2->beginTransaction();

        $this->connection->insert('users', ['id' => 1]);

        $query = 'SELECT id FROM users';
        $query = $this->connection2->getDatabasePlatform()->appendLockHint($query, LockMode::NONE);

        self::assertFalse($this->connection2->fetchOne($query));
    }
}
