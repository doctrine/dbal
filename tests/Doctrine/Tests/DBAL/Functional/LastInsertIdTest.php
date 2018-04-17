<?php

namespace Doctrine\Tests\DBAL\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
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

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertId(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $insertExecutor($this->testConnection->getWrappedConnection());

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdAfterUpdate(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $insertExecutor($this->testConnection->getWrappedConnection());
        $this->testConnection->update('last_insert_id_table', ['foo' => 2], ['id' => 1]);

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdAfterDelete(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $insertExecutor($this->testConnection->getWrappedConnection());
        $this->testConnection->exec('DELETE FROM last_insert_id_table');

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdAfterTruncate(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $insertExecutor($this->testConnection->getWrappedConnection());
        $truncateTableSql = $this->testConnection->getDatabasePlatform()->getTruncateTableSQL('last_insert_id_table');
        $this->testConnection->exec($truncateTableSql);

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdAfterDropTable(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $this->createTable('last_insert_id_table_tmp');

        $insertExecutor($this->testConnection->getWrappedConnection());
        $this->testConnection->getSchemaManager()->dropTable('last_insert_id_table_tmp');

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdAfterSelect(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $insertExecutor($this->testConnection->getWrappedConnection());
        $this->testConnection->executeQuery('SELECT 1 FROM last_insert_id_table');

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdInTransaction(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $this->testConnection->beginTransaction();
        $insertExecutor($this->testConnection->getWrappedConnection());
        $this->assertSame('1', $this->testConnection->lastInsertId());
        $this->testConnection->rollBack();
    }

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdAfterTransactionCommit(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $this->testConnection->beginTransaction();
        $insertExecutor($this->testConnection->getWrappedConnection());
        $this->testConnection->commit();

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdAfterTransactionRollback(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $this->testConnection->beginTransaction();
        $insertExecutor($this->testConnection->getWrappedConnection());
        $this->testConnection->rollBack();

        $this->assertSame('1', $this->testConnection->lastInsertId());
    }

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdInsertAfterTransactionRollback(callable $insertExecutor) : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $this->testConnection->beginTransaction();
        $insertExecutor($this->testConnection->getWrappedConnection());
        $this->testConnection->rollBack();
        $insertExecutor($this->testConnection->getWrappedConnection());

        $expected = $this->testConnection->getDatabasePlatform()->getName() === 'sqlite'
            // SQLite has a different transaction concept, that reuses rolled back IDs
            // See: http://sqlite.1065341.n5.nabble.com/Autoincrement-with-rollback-td79154.html
            ? '1'
            : '2';

        $this->assertSame($expected, $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdReusePreparedStatementPrepare() : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

        $statement = $this->testConnection->prepare('INSERT INTO last_insert_id_table (foo) VALUES (1)');

        $statement->execute();
        $statement->execute();

        $this->assertSame('2', $this->testConnection->lastInsertId());
    }

    public function testLastInsertIdReusePreparedStatementQuery() : void
    {
        $this->ensurePlatformSupportsIdentityColumns();

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

        $this->ensurePlatformSupportsIdentityColumns();

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

    /**
     * @dataProvider executorProvider
     */
    public function testLastInsertIdSequenceEmulatedIdentityColumn(callable $insertExecutor) : void
    {
        $platform = $this->_conn->getDatabasePlatform();

        if ($platform->supportsIdentityColumns() || ! $platform->usesSequenceEmulatedIdentityColumns()) {
            $this->markTestSkipped('Test only works on platforms that emulates identity columns through sequences.');
        }

        $sequenceName = $platform->getIdentitySequenceName('last_insert_id_table', 'id');

        $this->assertSame('0', $this->_conn->lastInsertId($sequenceName));

        $insertExecutor($this->testConnection->getWrappedConnection());

        $this->assertSame('1', $this->_conn->lastInsertId($sequenceName));
    }

    private function ensurePlatformSupportsIdentityColumns() : void
    {
        if ($this->_conn->getDatabasePlatform()->supportsIdentityColumns()) {
            return;
        }

        $this->markTestSkipped('Test only works on platforms with identity columns.');
    }

    public static function executorProvider()
    {
        foreach (self::getExecutors() as $name => $executor) {
            yield $name => [$executor];
        }
    }

    private static function getExecutors()
    {
        return [
            'exec' => function (DriverConnection $connection) {
                $connection->exec(
                    'INSERT INTO last_insert_id_table (foo) VALUES (1)'
                );
            },
            'prepare-insert' => function (DriverConnection $connection) {
                $connection->prepare(
                    'INSERT INTO last_insert_id_table (foo) VALUES (?)'
                )->execute([1]);
            },
            'query-insert' => function (DriverConnection $connection) {
                $connection->query(
                    'INSERT INTO last_insert_id_table (foo) VALUES (1)'
                );
            },
        ];
    }
}
