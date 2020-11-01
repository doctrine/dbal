<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\LockMode;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\OCI8;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\Tests\DbalFunctionalTestCase;
use Doctrine\Tests\TestUtil;

class NoneTest extends DbalFunctionalTestCase
{
    /** @var Connection */
    private $connection2;

    public function setUp(): void
    {
        parent::setUp();

        if ($this->connection->getDriver() instanceof OCI8\Driver) {
            // https://github.com/doctrine/dbal/issues/4417
            self::markTestSkipped('This test fails on OCI8 for a currently unknown reason');
        }

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
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        $this->connection->getSchemaManager()->dropAndCreateTable($table);

        $this->connection2 = TestUtil::getConnection();

        if ($this->connection2->getSchemaManager()->tablesExist('users')) {
            return;
        }

        if ($this->connection2->getDatabasePlatform() instanceof SqlitePlatform) {
            self::markTestSkipped('This test cannot run on SQLite using an in-memory database');
        }

        self::fail('Separate connections do not seem to talk to the same database');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        if ($this->connection2->isTransactionActive()) {
            $this->connection2->rollBack();
        }

        $this->connection2->close();

        $this->connection->getSchemaManager()->dropTable('users');

        if (! $this->connection->getDatabasePlatform() instanceof SQLServerPlatform) {
            return;
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
