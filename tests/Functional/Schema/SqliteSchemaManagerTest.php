<?php

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function dirname;

class SqliteSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof SqlitePlatform;
    }

    /**
     * SQLITE does not support databases.
     */
    public function testListDatabases(): void
    {
        $this->expectException(Exception::class);

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
        $this->connection->executeStatement(<<<EOS
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
            new ForeignKeyConstraint(
                ['log'],
                'log',
                [],
                'FK_3',
                ['onUpdate' => 'SET NULL', 'onDelete' => 'NO ACTION', 'deferrable' => false, 'deferred' => false]
            ),
            new ForeignKeyConstraint(
                ['parent'],
                'user',
                ['id'],
                '1',
                ['onUpdate' => 'NO ACTION', 'onDelete' => 'CASCADE', 'deferrable' => false, 'deferred' => false]
            ),
            new ForeignKeyConstraint(
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
        $table = new Table('test_collation');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->addColumn('foo', 'text')->setPlatformOption('collation', 'BINARY');
        $table->addColumn('bar', 'text')->setPlatformOption('collation', 'NOCASE');
        $this->dropAndCreateTable($table);

        $columns = $this->schemaManager->listTableColumns('test_collation');

        self::assertArrayNotHasKey('collation', $columns['id']->getPlatformOptions());
        self::assertEquals('BINARY', $columns['text']->getPlatformOption('collation'));
        self::assertEquals('BINARY', $columns['foo']->getPlatformOption('collation'));
        self::assertEquals('NOCASE', $columns['bar']->getPlatformOption('collation'));
    }

    /**
     * SQLite stores BINARY columns as BLOB
     */
    protected function assertBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        self::assertInstanceOf(BlobType::class, $table->getColumn($columnName)->getType());
    }

    /**
     * SQLite stores VARBINARY columns as BLOB
     */
    protected function assertVarBinaryColumnIsValid(Table $table, string $columnName, int $expectedLength): void
    {
        self::assertInstanceOf(BlobType::class, $table->getColumn($columnName)->getType());
    }

    public function testListTableColumnsWithWhitespacesInTypeDeclarations(): void
    {
        $sql = <<<SQL
CREATE TABLE dbal_1779 (
    foo VARCHAR (64) ,
    bar TEXT (100)
)
SQL;

        $this->connection->executeStatement($sql);

        $columns = $this->schemaManager->listTableColumns('dbal_1779');

        self::assertCount(2, $columns);

        self::assertArrayHasKey('foo', $columns);
        self::assertArrayHasKey('bar', $columns);

        self::assertSame(Type::getType(Types::STRING), $columns['foo']->getType());
        self::assertSame(Type::getType(Types::TEXT), $columns['bar']->getType());

        self::assertSame(64, $columns['foo']->getLength());
        self::assertSame(100, $columns['bar']->getLength());
    }

    public function testPrimaryKeyNoAutoIncrement(): void
    {
        $table = new Table('test_pk_auto_increment');
        $table->addColumn('id', 'integer');
        $table->addColumn('text', 'text');
        $table->setPrimaryKey(['id']);
        $this->dropAndCreateTable($table);

        $this->connection->insert('test_pk_auto_increment', ['text' => '1']);

        $this->connection->executeStatement('DELETE FROM test_pk_auto_increment');

        $this->connection->insert('test_pk_auto_increment', ['text' => '2']);

        $lastUsedIdAfterDelete = (int) $this->connection->fetchOne(
            'SELECT id FROM test_pk_auto_increment WHERE text = "2"'
        );

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

        self::assertNull($sm->listTableDetails('own_column_comment')
            ->getColumn('col1')
            ->getComment());
    }
}
