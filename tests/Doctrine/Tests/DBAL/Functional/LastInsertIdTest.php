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

    protected function setUp()
    {
        parent::setUp();

        $this->testConnection = TestUtil::getConnection();

        $this->createTable('last_insert_id_table');
    }

    protected function tearDown()
    {
        $this->testConnection->close();

        if ($this->_conn->getDatabasePlatform()->getName() !== 'sqlite') {
            $this->_conn->getSchemaManager()->dropTable('last_insert_id_table');
        }

        parent::tearDown();
    }

    private function createTable($tableName)
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

    public function testLastInsertIdNoInsert()
    {
        $this->assertSame('0', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdExec()
    {
        $this->assertLastInsertId($this->createExecInsertExecutor());
    }

    public function testLastInsertIdPrepare()
    {
        $this->assertLastInsertId($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdQuery()
    {
        $this->assertLastInsertId($this->createQueryInsertExecutor());
    }

    private function assertLastInsertId(callable $insertExecutor)
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterUpdateExec()
    {
        $this->assertLastInsertIdAfterUpdate($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterUpdatePrepare()
    {
        $this->assertLastInsertIdAfterUpdate($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterUpdateQuery()
    {
        $this->assertLastInsertIdAfterUpdate($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterUpdate(callable $insertExecutor)
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();
        $this->testConnection->update('last_insert_id_table', ['foo' => 2], ['id' => 1]);

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterDeleteExec()
    {
        $this->assertLastInsertIdAfterDelete($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterDeletePrepare()
    {
        $this->assertLastInsertIdAfterDelete($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterDeleteQuery()
    {
        $this->assertLastInsertIdAfterDelete($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterDelete(callable $insertExecutor)
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();
        $this->testConnection->exec('DELETE FROM last_insert_id_table');

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterTruncateExec()
    {
        $this->assertLastInsertIdAfterTruncate($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterTruncatePrepare()
    {
        $this->assertLastInsertIdAfterTruncate($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterTruncateQuery()
    {
        $this->assertLastInsertIdAfterTruncate($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterTruncate(callable $insertExecutor)
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();
        $truncateTableSql = $this->testConnection->getDatabasePlatform()->getTruncateTableSQL('last_insert_id_table');
        $this->testConnection->exec($truncateTableSql);

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterDropTableExec()
    {
        $this->assertLastInsertIdAfterDropTable($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterDropTablePrepare()
    {
        $this->assertLastInsertIdAfterDropTable($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterDropTableQuery()
    {
        $this->assertLastInsertIdAfterDropTable($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterDropTable(callable $insertExecutor)
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->createTable('last_insert_id_table_tmp');

        $insertExecutor();
        $this->testConnection->getSchemaManager()->dropTable('last_insert_id_table_tmp');

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterSelectExec()
    {
        $this->assertLastInsertIdAfterSelect($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterSelectPrepare()
    {
        $this->assertLastInsertIdAfterSelect($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterSelectQuery()
    {
        $this->assertLastInsertIdAfterSelect($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterSelect(callable $insertExecutor)
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $insertExecutor();
        $this->testConnection->executeQuery('SELECT 1 FROM last_insert_id_table');

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdInTransactionExec()
    {
        $this->assertLastInsertIdInTransaction($this->createExecInsertExecutor());
    }

    public function testLastInsertIdInTransactionPrepare()
    {
        $this->assertLastInsertIdInTransaction($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdInTransactionQuery()
    {
        $this->assertLastInsertIdInTransaction($this->createQueryInsertExecutor());
    }

    public function assertLastInsertIdInTransaction(callable $insertExecutor)
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->testConnection->beginTransaction();
        $insertExecutor();
        $this->assertSame('1', $this->testConnection->lastInsertId());
        $this->testConnection->rollBack();
    }

    public function testLastInsertIdAfterTransactionCommitExec()
    {
        $this->assertLastInsertIdAfterTransactionCommit($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterTransactionCommitPrepare()
    {
        $this->assertLastInsertIdAfterTransactionCommit($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterTransactionCommitQuery()
    {
        $this->assertLastInsertIdAfterTransactionCommit($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterTransactionCommit(callable $insertExecutor)
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->testConnection->beginTransaction();
        $insertExecutor();
        $this->testConnection->commit();

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdAfterTransactionRollbackExec()
    {
        $this->assertLastInsertIdAfterTransactionRollback($this->createExecInsertExecutor());
    }

    public function testLastInsertIdAfterTransactionRollbackPrepare()
    {
        $this->assertLastInsertIdAfterTransactionRollback($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdAfterTransactionRollbackQuery()
    {
        $this->assertLastInsertIdAfterTransactionRollback($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdAfterTransactionRollback(callable $insertExecutor)
    {
        if (! $this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms with identity columns.');
        }

        $this->testConnection->beginTransaction();
        $insertExecutor();
        $this->testConnection->rollBack();

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdInsertAfterTransactionRollbackExec()
    {
        $this->assertLastInsertIdInsertAfterTransactionRollback($this->createExecInsertExecutor());
    }

    public function testLastInsertIdInsertAfterTransactionRollbackPrepare()
    {
        $this->assertLastInsertIdInsertAfterTransactionRollback($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdInsertAfterTransactionRollbackQuery()
    {
        $this->assertLastInsertIdInsertAfterTransactionRollback($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdInsertAfterTransactionRollback(callable $insertExecutor)
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

    public function testLastInsertIdConnectionScope()
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

    public function testLastInsertIdSequence()
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

    public function testLastInsertIdSequenceEmulatedIdentityColumnExec()
    {
        $this->assertLastInsertIdSequenceEmulatedIdentityColumn($this->createExecInsertExecutor());
    }

    public function testLastInsertIdSequenceEmulatedIdentityColumnPrepare()
    {
        $this->assertLastInsertIdSequenceEmulatedIdentityColumn($this->createPrepareInsertExecutor());
    }

    public function testLastInsertIdSequenceEmulatedIdentityColumnQuery()
    {
        $this->assertLastInsertIdSequenceEmulatedIdentityColumn($this->createQueryInsertExecutor());
    }

    private function assertLastInsertIdSequenceEmulatedIdentityColumn(callable $insertExecutor)
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

    private function createExecInsertExecutor()
    {
        return function () {
            $this->testConnection->getWrappedConnection()->exec('INSERT INTO last_insert_id_table (foo) VALUES (1)');
        };
    }

    private function createPrepareInsertExecutor()
    {
        return function () {
            $stmt = $this->testConnection->getWrappedConnection()->prepare(
                'INSERT INTO last_insert_id_table (foo) VALUES (?)'
            );

            $stmt->execute([1]);
        };
    }

    private function createQueryInsertExecutor()
    {
        return function () {
            $this->testConnection->getWrappedConnection()->query('INSERT INTO last_insert_id_table (foo) VALUES (1)');
        };
    }
}
