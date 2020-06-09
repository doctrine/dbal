<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use SQLite3;

use function array_map;
use function dirname;
use function extension_loaded;
use function version_compare;

class SqliteSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    /**
     * SQLITE does not support databases.
     */
    public function testListDatabases(): void
    {
        $this->expectException(DBALException::class);

        $this->schemaManager->listDatabases();
    }

    public function testCreateAndDropDatabase(): void
    {
        $path = dirname(__FILE__) . '/test_create_and_drop_sqlite_database.sqlite';

        $this->schemaManager->createDatabase($path);
        self::assertFileExists($path);
        $this->schemaManager->dropDatabase($path);
        self::assertFileDoesNotExist($path);
    }

    /**
     * @group DBAL-1220
     */
    public function testDropsDatabaseWithActiveConnections(): void
    {
        $this->schemaManager->dropAndCreateDatabase('test_drop_database');

        self::assertFileExists('test_drop_database');

        $params           = $this->connection->getParams();
        $params['dbname'] = 'test_drop_database';

        $user     = $params['user'] ?? '';
        $password = $params['password'] ?? '';

        $connection = $this->connection->getDriver()->connect($params, $user, $password);

        $this->schemaManager->dropDatabase('test_drop_database');

        self::assertFileDoesNotExist('test_drop_database');

        unset($connection);
    }

    public function testRenameTable(): void
    {
        $this->createTestTable('oldname');
        $this->schemaManager->renameTable('oldname', 'newname');

        $tables = $this->schemaManager->listTableNames();
        self::assertContains('newname', $tables);
        self::assertNotContains('oldname', $tables);
    }

    public function createListTableColumns(): Table
    {
        $table = parent::createListTableColumns();
        $table->getColumn('id')->setAutoincrement(true);

        return $table;
    }

    public function testListForeignKeysFromExistingDatabase(): void
    {
        $this->connection->exec(<<<EOS
CREATE TABLE user (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page INTEGER CONSTRAINT FK_1 REFERENCES page (key) DEFERRABLE INITIALLY DEFERRED,
    parent INTEGER REFERENCES user(id) ON DELETE CASCADE,
    log INTEGER,
    CONSTRAINT FK_3 FOREIGN KEY (log) REFERENCES log ON UPDATE SET NULL NOT DEFERRABLE
)
EOS
        );

        $expected = [
            new Schema\ForeignKeyConstraint(
                ['log'],
                'log',
                [''],
                'FK_3',
                ['onUpdate' => 'SET NULL', 'onDelete' => 'NO ACTION', 'deferrable' => false, 'deferred' => false]
            ),
            new Schema\ForeignKeyConstraint(
                ['parent'],
                'user',
                ['id'],
                '1',
                ['onUpdate' => 'NO ACTION', 'onDelete' => 'CASCADE', 'deferrable' => false, 'deferred' => false]
            ),
            new Schema\ForeignKeyConstraint(
                ['page'],
                'page',
                ['key'],
                'FK_1',
                ['onUpdate' => 'NO ACTION', 'onDelete' => 'NO ACTION', 'deferrable' => true, 'deferred' => true]
            ),
        ];

        self::assertEquals($expected, $this->schemaManager->listTableForeignKeys('user'));
    }

    public function testColumnCollation(): void
    {
        $table = new Schema\Table('test_collation');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->addColumn('foo', 'text')->setPlatformOption('collation', 'BINARY');
        $table->addColumn('bar', 'text')->setPlatformOption('collation', 'NOCASE');
        $this->schemaManager->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_collation');

        self::assertArrayNotHasKey('collation', $columns['id']->getPlatformOptions());
        self::assertEquals('BINARY', $columns['text']->getPlatformOption('collation'));
        self::assertEquals('BINARY', $columns['foo']->getPlatformOption('collation'));
        self::assertEquals('NOCASE', $columns['bar']->getPlatformOption('collation'));
    }

    public function testListTableWithBinary(): void
    {
        $tableName = 'test_binary_table';

        $table = new Table($tableName);
        $table->addColumn('id', 'integer');
        $table->addColumn('column_varbinary', 'binary', []);
        $table->addColumn('column_binary', 'binary', ['fixed' => true]);
        $table->setPrimaryKey(['id']);

        $this->schemaManager->createTable($table);

        $table = $this->schemaManager->listTableDetails($tableName);

        self::assertInstanceOf(BlobType::class, $table->getColumn('column_varbinary')->getType());
        self::assertFalse($table->getColumn('column_varbinary')->getFixed());

        self::assertInstanceOf(BlobType::class, $table->getColumn('column_binary')->getType());
        self::assertFalse($table->getColumn('column_binary')->getFixed());
    }

    public function testNonDefaultPKOrder(): void
    {
        if (! extension_loaded('sqlite3')) {
            self::markTestSkipped('This test requires the SQLite3 extension.');
        }

        $version = SQLite3::version();
        if (version_compare($version['versionString'], '3.7.16', '<')) {
            self::markTestSkipped('This version of sqlite doesn\'t return the order of the Primary Key.');
        }

        $this->connection->exec(<<<EOS
CREATE TABLE non_default_pk_order (
    id INTEGER,
    other_id INTEGER,
    PRIMARY KEY(other_id, id)
)
EOS
        );

        $tableIndexes = $this->schemaManager->listTableIndexes('non_default_pk_order');

         self::assertCount(1, $tableIndexes);

        self::assertArrayHasKey('primary', $tableIndexes, 'listTableIndexes() has to return a "primary" array key.');
        self::assertEquals(['other_id', 'id'], array_map('strtolower', $tableIndexes['primary']->getColumns()));
    }

    /**
     * @group DBAL-1779
     */
    public function testListTableColumnsWithWhitespacesInTypeDeclarations(): void
    {
        $sql = <<<SQL
CREATE TABLE dbal_1779 (
    foo VARCHAR (64) ,
    bar TEXT (100)
)
SQL;

        $this->connection->exec($sql);

        $columns = $this->schemaManager->listTableColumns('dbal_1779');

        self::assertCount(2, $columns);

        self::assertArrayHasKey('foo', $columns);
        self::assertArrayHasKey('bar', $columns);

        self::assertSame(Type::getType(Types::STRING), $columns['foo']->getType());
        self::assertSame(Type::getType(Types::TEXT), $columns['bar']->getType());

        self::assertSame(64, $columns['foo']->getLength());
        self::assertSame(100, $columns['bar']->getLength());
    }

    /**
     * @dataProvider getDiffListIntegerAutoincrementTableColumnsData
     * @group DBAL-924
     */
    public function testDiffListIntegerAutoincrementTableColumns(string $integerType, bool $unsigned, bool $expectedComparatorDiff): void
    {
        $tableName = 'test_int_autoincrement_table';

        $offlineTable = new Table($tableName);
        $offlineTable->addColumn('id', $integerType, ['autoincrement' => true, 'unsigned' => $unsigned]);
        $offlineTable->setPrimaryKey(['id']);

        $this->schemaManager->dropAndCreateTable($offlineTable);

        $onlineTable = $this->schemaManager->listTableDetails($tableName);
        $comparator  = new Schema\Comparator();
        $diff        = $comparator->diffTable($offlineTable, $onlineTable);

        if ($expectedComparatorDiff) {
            self::assertNotNull($diff);

            self::assertEmpty($this->schemaManager->getDatabasePlatform()->getAlterTableSQL($diff));
        } else {
            self::assertNull($diff);
        }
    }

    /**
     * @return mixed[][]
     */
    public static function getDiffListIntegerAutoincrementTableColumnsData(): iterable
    {
        return [
            ['smallint', false, true],
            ['smallint', true, true],
            ['integer', false, false],
            ['integer', true, true],
            ['bigint', false, true],
            ['bigint', true, true],
        ];
    }

    /**
     * @group DBAL-2921
     */
    public function testPrimaryKeyNoAutoIncrement(): void
    {
        $table = new Schema\Table('test_pk_auto_increment');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->setPrimaryKey(['id']);
        $this->schemaManager->dropAndCreateTable($table);

        $this->connection->insert('test_pk_auto_increment', ['text' => '1']);

        $this->connection->query('DELETE FROM test_pk_auto_increment');

        $this->connection->insert('test_pk_auto_increment', ['text' => '2']);

        $result = $this->connection->query('SELECT id FROM test_pk_auto_increment WHERE text = "2"');

        $lastUsedIdAfterDelete = (int) $result->fetchOne();

        // with an empty table, non autoincrement rowid is always 1
        self::assertEquals(1, $lastUsedIdAfterDelete);
    }

    public function testOnlyOwnCommentIsParsed(): void
    {
        $table = new Table('own_column_comment');
        $table->addColumn('col1', 'string', ['length' => 16]);
        $table->addColumn('col2', 'string', ['length' => 16, 'comment' => 'Column #2']);
        $table->addColumn('col3', 'string', ['length' => 16]);

        $sm = $this->connection->getSchemaManager();
        $sm->createTable($table);

        self::assertSame('', $sm->listTableDetails('own_column_comment')
            ->getColumn('col1')
            ->getComment());
    }
}
