<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Tests\DbalFunctionalTestCase;
use Doctrine\Tests\TestUtil;

class LastInsertIdTest extends DbalFunctionalTestCase
{
    /** @var Connection */
    private $testConnection;

    protected function setUp() : void
    {
        parent::setUp();

        $this->testConnection = TestUtil::getConnection();

        $this->createTable('last_insert_id_table');
    }

    protected function tearDown() : void
    {
        $this->testConnection->close();

        if ($this->_conn->getDatabasePlatform()->getName() !== 'sqlite') {
            $this->_conn->getSchemaManager()->dropTable('last_insert_id_table');
        }

        parent::tearDown();
    }

    private function createTable(string $tableName) : void
    {
        $table = new Table($tableName);
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer', ['notnull' => false]);
        $table->setPrimaryKey(['id']);

        $connection = $this->_conn->getDatabasePlatform()->getName() === 'sqlite'
            ? $this->testConnection
            : $this->_conn;

        $connection->getSchemaManager()->createTable($table);
    }

    public function testLastInsertIdNoInsert() : void
    {
        $this->assertSame('0', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdExec() : void
    {
        $this->assertLastInsertId($this->createExecInsertExecutor());
    }

    public function testLastInsertIdPrepare() : void
    {
        $this->assertLastInsertId($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdQuery() : void
    {
        $this->assertLastInsertId($this->createQueryInsertExecutor());
    }

    private function assertLastInsertId(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterUpdateExec() : void
    {
        $this->assertLastInsertIdAfterUpdate($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterUpdatePrepare() : void
    {
        $this->assertLastInsertIdAfterUpdate($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterUpdateQuery() : void
    {
        $this->assertLastInsertIdAfterUpdate($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterUpdate(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();
        $this->testConnection->update('last_insert_id_table', ['foo' => 2], ['id' => 1]);

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterDeleteExec() : void
    {
        $this->assertLastInsertIdAfterDelete($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterDeletePrepare() : void
    {
        $this->assertLastInsertIdAfterDelete($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterDeleteQuery() : void
    {
        $this->assertLastInsertIdAfterDelete($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterDelete(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();
        $this->testConnection->exec('DELETE FROM last_insert_id_table');

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterTruncateExec() : void
    {
        $this->assertLastInsertIdAfterTruncate($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterTruncatePrepare() : void
    {
        $this->assertLastInsertIdAfterTruncate($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterTruncateQuery() : void
    {
        $this->assertLastInsertIdAfterTruncate($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterTruncate(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();
        $truncateTableSql = $this->testConnection->getDatabasePlatform()->getTruncateTableSQL('last_insert_id_table');
        $this->testConnection->exec($truncateTableSql);

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterDropTableExec() : void
    {
        $this->assertLastInsertIdAfterDropTable($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterDropTablePrepare() : void
    {
        $this->assertLastInsertIdAfterDropTable($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterDropTableQuery() : void
    {
        $this->assertLastInsertIdAfterDropTable($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterDropTable(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->createTable('last_insert_id_table_tmp');

        $insertExecutor();
        $this->testConnection->getSchemaManager()->dropTable('last_insert_id_table_tmp');

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterSelectExec() : void
    {
        $this->assertLastInsertIdAfterSelect($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterSelectPrepare() : void
    {
        $this->assertLastInsertIdAfterSelect($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterSelectQuery() : void
    {
        $this->assertLastInsertIdAfterSelect($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterSelect(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();
        $this->testConnection->executeQuery('SELECT 1 FROM last_insert_id_table');

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdInTransactionExec() : void
    {
        $this->assertLastInsertIdInTransaction($this->createExecInsertExecutor());
    }

    public function testLastInsertIdInTransactionPrepare() : void
    {
        $this->assertLastInsertIdInTransaction($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdInTransactionQuery() : void
    {
        $this->assertLastInsertIdInTransaction($this->createQueryInsertExecutor());
    }

    public function assertLastInsertIdInTransaction(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->testConnection->beginTransaction();
        $insertExecutor();
        $this->assertSame('1', $this->testConnection->lastInsertId());
        $this->testConnection->rollBack();
    }

    public function testLastInsertIdAfterTransactionCommitExec() : void
    {
        $this->assertLastInsertIdAfterTransactionCommit($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterTransactionCommitPrepare() : void
    {
        $this->assertLastInsertIdAfterTransactionCommit($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterTransactionCommitQuery() : void
    {
        $this->assertLastInsertIdAfterTransactionCommit($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterTransactionCommit(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->testConnection->beginTransaction();
        $insertExecutor();
        $this->testConnection->commit();

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterTransactionRollbackExec() : void
    {
        $this->assertLastInsertIdAfterTransactionRollback($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterTransactionRollbackPrepare() : void
    {
        $this->assertLastInsertIdAfterTransactionRollback($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterTransactionRollbackQuery() : void
    {
        $this->assertLastInsertIdAfterTransactionRollback($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterTransactionRollback(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->testConnection->beginTransaction();
        $insertExecutor();
        $this->testConnection->rollBack();

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdInsertAfterTransactionRollbackExec() : void
    {
        $this->assertLastInsertIdInsertAfterTransactionRollback($this->createExecInsertExecutor());
    }

    public function testLastInsertIdInsertAfterTransactionRollbackPrepare() : void
    {
        $this->assertLastInsertIdInsertAfterTransactionRollback($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdInsertAfterTransactionRollbackQuery() : void
    {
        $this->assertLastInsertIdInsertAfterTransactionRollback($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdInsertAfterTransactionRollback(callable $insertExecutor) : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->testConnection->beginTransaction();
        $insertExecutor();
        $this->testConnection->rollBack();
        $insertExecutor();

        $expected = $this->testConnection->getDatabasePlatform()->getName() === 'sqlite'
            // SQLite has a different transaction concept, that reuses rolled back IDs
            // See: http://sqlite.1065341.n5.nabble.com/Autoincrement-with-rollback-td79154.html
            ? '1'
            : '2';

        $this->assertSame($expected, $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdReusePreparedStatementPrepare() : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $statement = $this->testConnection->prepare('INSERT INTO last_insert_id_table (foo) VALUES (1)');

        $statement->execute();
        $statement->execute();

        $this->assertSame('2', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdReusePreparedStatementQuery() : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $statement = $this->testConnection->query('INSERT INTO last_insert_id_table (foo) VALUES (1)');

        $statement->execute();

        $this->assertSame('2', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdConnectionScope() : void
    {
        $platform = $this->_conn->getDatabasePlatform();

        if ($platform->getName() === 'sqlite') {
            $this->markTestSkipped('Test does not work on sqlite as connections do not share memory.');
        }

        if (! $platform->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $connection1 = TestUtil::getConnection();
        $connection2 = TestUtil::getConnection();

        $connection1->insert('last_insert_id_table', ['foo' => 1]);

        $this->assertNotSame('1', $connection2->lastInsertId());

        $connection2->insert('last_insert_id_table', ['foo' => 2]);

        $this->assertSame('1', $connection1->lastInsertId());
        $this->assertSame('2', $connection2->lastInsertId());

        $connection1->close();
        $connection2->close();
    }

    public function testLastInsertIdSequence() : void
    {
        if (! $this->_conn->getDatabasePlatform()->supportsSequences()) {
            $this->markTestSkipped('Test only works on platforms with sequences.');
        }

        $sequence = new Sequence('last_insert_id_seq');

        $this->_conn->getSchemaManager()->createSequence($sequence);

        $nextSequenceValueSql = $this->_conn->getDatabasePlatform()->getSequenceNextValSQL('last_insert_id_seq');
        $nextSequenceValue    = $this->_conn->fetchColumn($nextSequenceValueSql);
        $lastInsertId         = $this->_conn->lastInsertId('last_insert_id_seq');

        $this->assertEquals($lastInsertId, $nextSequenceValue);
    }

    public function testLastInsertIdSequenceEmulatedIdentityColumnExec() : void
    {
        $this->assertLastInsertIdSequenceEmulatedIdentityColumn($this->createExecInsertExecutor());
    }

    public function testLastInsertIdSequenceEmulatedIdentityColumnPrepare() : void
    {
        $this->assertLastInsertIdSequenceEmulatedIdentityColumn($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdSequenceEmulatedIdentityColumnQuery() : void
    {
        $this->assertLastInsertIdSequenceEmulatedIdentityColumn($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdSequenceEmulatedIdentityColumn(callable $insertExecutor) : void
    {
        $platform = $this->_conn->getDatabasePlatform();

        if ($platform->supportsIdentityColumns() || ! $platform->usesSequenceEmulatedIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms that emulates identity columns through sequences.');
        }

        $sequenceName = $platform->getIdentitySequenceName('last_insert_id_table', 'id');

        $this->assertSame('0', $this->_conn->lastInsertId($sequenceName));

        $insertExecutor();

        $this->assertSame('1', $this->_conn->lastInsertId($sequenceName));
    }

    private function createExecInsertExecutor() : callable
    {
        return function () {
            $this->testConnection->getWrappedConnection()->exec('INSERT INTO last_insert_id_table (foo) VALUES (1)');
        };
    }

    private function createPrepareInsertExecutor() : callable
    {
        return function () {
            $stmt = $this->testConnection->getWrappedConnection()->prepare(
                'INSERT INTO last_insert_id_table (foo) VALUES (?)'
            );

            $stmt->execute([1]);
        };
    }

    private function createQueryInsertExecutor() : callable
    {
        return function () {
            $this->testConnection->getWrappedConnection()->query('INSERT INTO last_insert_id_table (foo) VALUES (1)');
        };
    }
}
