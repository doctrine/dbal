<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Exception\UnsupportedSchema;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_keys;
use function array_values;

class SQLiteSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof SQLitePlatform;
    }

    /**
     * SQLITE does not support databases.
     */
    public function testListDatabases(): void
    {
        $this->expectException(Exception::class);

        $this->schemaManager->listDatabases();
    }

    public function createListTableColumns(): Table
    {
        $table = parent::createListTableColumns();
        $table->getColumn('id')->setAutoincrement(true);

        return $table;
    }

    /** @throws Exception */
    public function testListForeignKeysFromExistingDatabase(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS user');
        $this->connection->executeStatement(<<<'EOS'
CREATE TABLE user (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page INTEGER CONSTRAINT FK_1 REFERENCES page (key) DEFERRABLE INITIALLY DEFERRED,
    parent INTEGER REFERENCES user(id) ON DELETE CASCADE
)
EOS);

        $expected = [
            ForeignKeyConstraint::editor()
                ->setName(UnqualifiedName::unquoted('FK_1'))
                ->setReferencingColumnNames(UnqualifiedName::unquoted('page'))
                ->setReferencedTableName(OptionallyQualifiedName::unquoted('page'))
                ->setReferencedColumnNames(UnqualifiedName::unquoted('key'))
                ->setDeferrability(Deferrability::DEFERRED)
                ->create(),
            ForeignKeyConstraint::editor()
                ->setReferencingColumnNames(UnqualifiedName::unquoted('parent'))
                ->setReferencedTableName(OptionallyQualifiedName::unquoted('user'))
                ->setReferencedColumnNames(UnqualifiedName::unquoted('id'))
                ->setOnDeleteAction(ReferentialAction::CASCADE)
                ->create(),
        ];

        $this->assertForeignKeyConstraintListEquals(
            $expected,
            array_values($this->schemaManager->listTableForeignKeys('user')),
        );
    }

    public function testListForeignKeysWithImplicitColumnsFromIncompleteSchema(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS t1');
        $this->connection->executeStatement(<<<'EOS'
CREATE TABLE t1 (
    id INTEGER,
    t2_id INTEGER,
    FOREIGN KEY (t2_id) REFERENCES t2
)
EOS);

        $this->expectException(UnsupportedSchema::class);

        $this->schemaManager->listTableForeignKeys('t1');
    }

    public function testColumnCollation(): void
    {
        $table = new Table('test_collation');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('text', Types::TEXT);
        $table->addColumn('foo', Types::TEXT)->setPlatformOption('collation', 'BINARY');
        $table->addColumn('bar', Types::TEXT)->setPlatformOption('collation', 'NOCASE');
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
        $sql = <<<'SQL'
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
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('text', Types::TEXT);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setColumnNames(
                    UnqualifiedName::unquoted('id'),
                )
                ->create(),
        );
        $this->dropAndCreateTable($table);

        $this->connection->insert('test_pk_auto_increment', ['text' => '1']);

        $this->connection->executeStatement('DELETE FROM test_pk_auto_increment');

        $this->connection->insert('test_pk_auto_increment', ['text' => '2']);

        $lastUsedIdAfterDelete = (int) $this->connection->fetchOne(
            'SELECT id FROM test_pk_auto_increment WHERE text = "2"',
        );

        // with an empty table, non autoincrement rowid is always 1
        self::assertEquals(1, $lastUsedIdAfterDelete);
    }

    public function testOnlyOwnCommentIsParsed(): void
    {
        $table = new Table('own_column_comment');
        $table->addColumn('col1', Types::STRING, ['length' => 16]);
        $table->addColumn('col2', Types::STRING, ['length' => 16, 'comment' => 'Column #2']);
        $table->addColumn('col3', Types::STRING, ['length' => 16]);

        $sm = $this->connection->createSchemaManager();
        $sm->createTable($table);

        self::assertSame('', $sm->introspectTable('own_column_comment')
            ->getColumn('col1')
            ->getComment());
    }

    public function testNonSimpleAlterTableCreatedFromDDL(): void
    {
        $this->dropTableIfExists('nodes');

        $ddl = <<<'DDL'
        CREATE TABLE nodes (
            id        INTEGER NOT NULL,
            parent_id INTEGER,
            name      TEXT,
            PRIMARY KEY (id),
            FOREIGN KEY (parent_id) REFERENCES nodes (id)
        )
        DDL;

        $this->connection->executeStatement($ddl);

        $schemaManager = $this->connection->createSchemaManager();

        $table1 = $schemaManager->introspectTable('nodes');
        $table2 = clone $table1;
        $table2->addIndex(['name'], 'idx_node_name');

        $comparator = $schemaManager->createComparator();
        $diff       = $comparator->compareTables($table1, $table2);

        $schemaManager->alterTable($diff);

        $table = $schemaManager->introspectTable('nodes');
        $index = $table->getIndex('idx_node_name');
        self::assertSame(['name'], $index->getUnquotedColumns());
    }

    public function testAlterTableWithSchema(): void
    {
        $this->dropTableIfExists('t');

        $table = new Table('main.t');
        $table->addColumn('a', Types::INTEGER);
        $this->schemaManager->createTable($table);

        self::assertSame(['a'], array_keys($this->schemaManager->listTableColumns('t')));

        $tableDiff = new TableDiff($table, changedColumns: [
            'a' => new ColumnDiff(
                new Column('a', Type::getType(Types::INTEGER)),
                new Column('b', Type::getType(Types::INTEGER)),
            ),
        ]);
        $this->schemaManager->alterTable($tableDiff);

        self::assertSame(['b'], array_keys($this->schemaManager->listTableColumns('t')));
    }

    /** @throws Exception */
    public function testIntrospectMultipleAnonymousForeignKeyConstraints(): void
    {
        $this->dropTableIfExists('album');
        $this->dropTableIfExists('song');

        $ddl = <<<'DDL'
        CREATE TABLE artist(
          id INTEGER,
          name TEXT,
          PRIMARY KEY (id)
        );

        CREATE TABLE album(
          id INTEGER,
          name TEXT,
          PRIMARY KEY (id)
        );

        CREATE TABLE song(
          id     INTEGER,
          album_id INTEGER,
          artist_id INTEGER,
          FOREIGN KEY(album_id) REFERENCES album(id),
          FOREIGN KEY(artist_id) REFERENCES artist(id)
        );
        DDL;

        $this->connection->executeStatement($ddl);

        $schemaManager = $this->connection->createSchemaManager();

        $song = $schemaManager->introspectTable('song');

        /** @var list<ForeignKeyConstraint> $foreignKeys */
        $foreignKeys = array_values($song->getForeignKeys());
        self::assertCount(2, $foreignKeys);

        $foreignKey1 = $foreignKeys[0];
        self::assertEmpty($foreignKey1->getName());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('album_id'),
        ], $foreignKey1->getReferencingColumnNames());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('id'),
        ], $foreignKey1->getReferencedColumnNames());

        $foreignKey2 = $foreignKeys[1];
        self::assertEmpty($foreignKey2->getName());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('artist_id'),
        ], $foreignKey2->getReferencingColumnNames());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('id'),
        ], $foreignKey2->getReferencedColumnNames());
    }

    /** @throws Exception */
    public function testNoWhitespaceInForeignKeyReference(): void
    {
        $this->dropTableIfExists('notes');
        $this->dropTableIfExists('users');

        $ddl = <<<'DDL'
        CREATE TABLE "users" (
            "id" INTEGER
        );

        CREATE TABLE "notes" (
            "id" INTEGER,
            "created_by" INTEGER,
            FOREIGN KEY("created_by") REFERENCES "users"("id"));
        DDL;

        $this->connection->executeStatement($ddl);
        $notes = $this->schemaManager->introspectTable('notes');

        /** @var list<ForeignKeyConstraint> $foreignKeys */
        $foreignKeys = array_values($notes->getForeignKeys());
        self::assertCount(1, $foreignKeys);

        $foreignKey = $foreignKeys[0];

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('created_by'),
        ], $foreignKey->getReferencingColumnNames());

        $this->assertOptionallyQualifiedNameEquals(
            OptionallyQualifiedName::unquoted('users'),
            $foreignKey->getReferencedTableName(),
        );

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('id'),
        ], $foreignKey->getReferencedColumnNames());
    }

    /** @throws Exception */
    public function testShorthandInForeignKeyReference(): void
    {
        $this->dropTableIfExists('artist');
        $this->dropTableIfExists('track');

        $ddl = <<<'DDL'
        CREATE TABLE artist(
            artistid INTEGER PRIMARY KEY,
            artistname TEXT
        );

        CREATE TABLE track(
            trackid INTEGER,
            trackname TEXT,
            trackartist INTEGER REFERENCES artist
        );
        DDL;

        $this->connection->executeStatement($ddl);

        $schemaManager = $this->connection->createSchemaManager();

        $song = $schemaManager->introspectTable('track');

        /** @var list<ForeignKeyConstraint> $foreignKeys */
        $foreignKeys = array_values($song->getForeignKeys());
        self::assertCount(1, $foreignKeys);

        $foreignKey1 = $foreignKeys[0];
        self::assertEmpty($foreignKey1->getName());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('trackartist'),
        ], $foreignKey1->getReferencingColumnNames());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('artistid'),
        ], $foreignKey1->getReferencedColumnNames());
    }

    public function getExpectedDefaultSchemaName(): ?string
    {
        return null;
    }
}
