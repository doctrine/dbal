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
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

class SQLiteSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    protected function supportsPlatform(AbstractPlatform $platform): bool
    {
        return $platform instanceof SQLitePlatform;
    }

    /**
     * SQLITE does not support databases.
     */
    public function testIntrospectDatabaseNames(): void
    {
        $this->expectException(Exception::class);

        $this->schemaManager->introspectDatabaseNames();
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
                ->setUnquotedName('FK_1')
                ->setUnquotedReferencingColumnNames('page')
                ->setUnquotedReferencedTableName('page')
                ->setUnquotedReferencedColumnNames('key')
                ->setDeferrability(Deferrability::DEFERRED)
                ->create(),
            ForeignKeyConstraint::editor()
                ->setUnquotedReferencingColumnNames('parent')
                ->setUnquotedReferencedTableName('user')
                ->setUnquotedReferencedColumnNames('id')
                ->setOnDeleteAction(ReferentialAction::CASCADE)
                ->create(),
        ];

        $this->assertForeignKeyConstraintListEquals(
            $expected,
            $this->schemaManager->introspectTableForeignKeyConstraintsByUnquotedName('user'),
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

        $this->schemaManager->introspectTableForeignKeyConstraintsByUnquotedName('t1');
    }

    public function testColumnCollation(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_collation')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('text')
                    ->setTypeName(Types::TEXT)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('foo')
                    ->setTypeName(Types::TEXT)
                    ->setCollation('BINARY')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('bar')
                    ->setTypeName(Types::TEXT)
                    ->setCollation('NOCASE')
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        [$id, $text, $foo, $bar] = $this->schemaManager->introspectTableColumnsByUnquotedName('test_collation');

        self::assertNull($id->getCollation());
        self::assertEquals('BINARY', $text->getCollation());
        self::assertEquals('BINARY', $foo->getCollation());
        self::assertEquals('NOCASE', $bar->getCollation());
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

        $columns = $this->schemaManager->introspectTableColumnsByUnquotedName('dbal_1779');

        self::assertCount(2, $columns);
        [$foo, $bar] = $columns;

        self::assertSame(Type::getType(Types::STRING), $foo->getType());
        self::assertSame(Type::getType(Types::TEXT), $bar->getType());

        self::assertSame(64, $foo->getLength());
        self::assertSame(100, $bar->getLength());
    }

    public function testListTableColumnsWithMixedCaseInTypeDeclarations(): void
    {
        $sql = <<<'SQL'
CREATE TABLE dbal_mixed (
    foo VarChar (64),
    bar Text (100)
)
SQL;

        $this->connection->executeStatement($sql);

        $columns = $this->schemaManager->introspectTableColumnsByUnquotedName('dbal_mixed');

        self::assertCount(2, $columns);
        [$foo, $bar] = $columns;

        self::assertSame(Type::getType(Types::STRING), $foo->getType());
        self::assertSame(Type::getType(Types::TEXT), $bar->getType());
    }

    public function testPrimaryKeyAutoIncrement(): void
    {
        $table = Table::editor()
            ->setUnquotedName('test_pk_auto_increment')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('text')
                    ->setTypeName(Types::TEXT)
                    ->create(),
            )
            ->setPrimaryKeyConstraint(
                PrimaryKeyConstraint::editor()
                    ->setUnquotedColumnNames('id')
                    ->create(),
            )
            ->create();

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
        $table = Table::editor()
            ->setUnquotedName('own_column_comment')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('col1')
                    ->setTypeName(Types::STRING)
                    ->setLength(16)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col2')
                    ->setTypeName(Types::STRING)
                    ->setLength(16)
                    ->setComment('Column #2')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('col3')
                    ->setTypeName(Types::STRING)
                    ->setLength(16)
                    ->create(),
            )
            ->create();

        $sm = $this->connection->createSchemaManager();
        $sm->createTable($table);

        self::assertSame('', $sm->introspectTableByUnquotedName('own_column_comment')
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

        $table1 = $schemaManager->introspectTableByUnquotedName('nodes');
        $table2 = $table1->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_node_name')
                    ->setUnquotedColumnNames('name')
                    ->create(),
            )
            ->create();

        $comparator = $schemaManager->createComparator();
        $diff       = $comparator->compareTables($table1, $table2);

        $schemaManager->alterTable($diff);

        $table = $schemaManager->introspectTableByUnquotedName('nodes');

        $this->assertIndexEquals(
            $table2->getIndex('idx_node_name'),
            $table->getIndex('idx_node_name'),
        );
    }

    public function testAlterTableWithSchema(): void
    {
        $this->dropTableIfExists('t');

        $table = Table::editor()
            ->setUnquotedName('t', 'main')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('a')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->create();

        $this->schemaManager->createTable($table);

        [$column] = $this->schemaManager->introspectTableColumnsByUnquotedName('t');

        self::assertUnqualifiedNameEquals(UnqualifiedName::unquoted('a'), $column->getObjectName());

        $tableDiff = new TableDiff($table, changedColumns: [
            'a' => new ColumnDiff(
                Column::editor()
                    ->setUnquotedName('a')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
                Column::editor()
                    ->setUnquotedName('b')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            ),
        ]);
        $this->schemaManager->alterTable($tableDiff);

        [$column] = $this->schemaManager->introspectTableColumnsByUnquotedName('t');

        self::assertUnqualifiedNameEquals(UnqualifiedName::unquoted('b'), $column->getObjectName());
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

        $song = $schemaManager->introspectTableByUnquotedName('song');

        /** @var list<ForeignKeyConstraint> $foreignKeys */
        $foreignKeys = $song->getForeignKeys();
        self::assertCount(2, $foreignKeys);

        $foreignKey1 = $foreignKeys[0];
        self::assertNull($foreignKey1->getObjectName());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('album_id'),
        ], $foreignKey1->getReferencingColumnNames());

        $this->assertUnqualifiedNameListEquals([
            UnqualifiedName::unquoted('id'),
        ], $foreignKey1->getReferencedColumnNames());

        $foreignKey2 = $foreignKeys[1];
        self::assertNull($foreignKey2->getObjectName());

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
        $notes = $this->schemaManager->introspectTableByUnquotedName('notes');

        /** @var list<ForeignKeyConstraint> $foreignKeys */
        $foreignKeys = $notes->getForeignKeys();
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

        $song = $schemaManager->introspectTableByUnquotedName('track');

        /** @var list<ForeignKeyConstraint> $foreignKeys */
        $foreignKeys = $song->getForeignKeys();
        self::assertCount(1, $foreignKeys);

        $foreignKey1 = $foreignKeys[0];
        self::assertNull($foreignKey1->getObjectName());

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

    #[DataProvider('getDataColumnCollation')]
    public function testParseColumnCollation(string $ddl, string $columnName, ?string $expectedCollation): void
    {
        $this->dropTableIfExists('test_collation');
        $this->connection->executeStatement($ddl);

        $schemaManager = $this->connection->createSchemaManager();
        $table         = $schemaManager->introspectTableByUnquotedName('test_collation');

        self::assertSame($expectedCollation, $table->getColumn(
            $this->connection->quoteSingleIdentifier($columnName),
        )->getCollation());
    }

    /** @return iterable<array{string, string, string}> */
    public static function getDataColumnCollation(): iterable
    {
        yield [
            <<<'DDL'
            CREATE TABLE "test_collation" ("a" text DEFAULT 'aa' COLLATE "RTRIM" NOT NULL)
            DDL,
            'a',
            'RTRIM',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE "test_collation" (
                "b" text UNIQUE NOT NULL COLLATE NOCASE,
                "a" text DEFAULT 'aa' COLLATE "RTRIM" NOT NULL
            )
            DDL,
            'a',
            'RTRIM',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE "test_collation" (
                "a" TEXT DEFAULT (LOWER(LTRIM(' a') || RTRIM('a '))) CHECK ("a") NOT NULL COLLATE NOCASE UNIQUE,
                "b" TEXT COLLATE RTRIM
            )
            DDL,
            'a',
            'NOCASE',
        ];

        yield [
            'CREATE TABLE "test_collation" ("a" text CHECK ("a") NOT NULL, "b" text COLLATE RTRIM)',
            'a',
            'BINARY',
        ];

        yield [
            'CREATE TABLE "test_collation" ("a""b" text COLLATE RTRIM)',
            'a"b',
            'RTRIM',
        ];

        yield [
            'CREATE TABLE "test_collation" (bb TEXT COLLATE RTRIM, b VARCHAR(42) NOT NULL COLLATE BINARY)',
            'b',
            'BINARY',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE "test_collation" (
                bbb TEXT COLLATE NOCASE,
                bb TEXT COLLATE RTRIM,
                b VARCHAR(42) NOT NULL COLLATE BINARY
            )
            DDL,
            'b',
            'BINARY',
        ];

        yield [
            'CREATE TABLE "test_collation" (b VARCHAR(42) NOT NULL COLLATE BINARY, bb TEXT COLLATE RTRIM)',
            'b',
            'BINARY',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE test_collation (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar#" VARCHAR(255) COLLATE "NOCASE" NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            )
            DDL,
            'bar#',
            'NOCASE',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE test_collation (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar#" VARCHAR(255) NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            )
            DDL,
            'bar#',
            'BINARY',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE test_collation (id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar#" INTEGER NOT NULL,
                baz VARCHAR(255) COLLATE "RTRIM" NOT NULL,
                PRIMARY KEY (id)
            )
            DDL,
            'baz',
            'RTRIM',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE test_collation (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar#" INTEGER NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            )
            DDL,
            'baz',
            'BINARY',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE test_collation (id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar/" VARCHAR(255) COLLATE "RTRIM" NOT NULL,
                baz VARCHAR(255) COLLATE "RTRIM" NOT NULL,
                PRIMARY KEY (id)
            )
            DDL,
            'bar/',
            'RTRIM',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE test_collation (id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar/" VARCHAR(255) NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            )
            DDL,
            'bar/',
            'BINARY',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE test_collation (id INTEGER NOT NULL,
                foo VARCHAR(255) COLLATE "RTRIM" NOT NULL,
                "bar/" INTEGER NOT NULL,
                baz VARCHAR(255) COLLATE "RTRIM" NOT NULL,
                PRIMARY KEY (id)
            )
            DDL,
            'baz',
            'RTRIM',
        ];

        yield [
            <<<'DDL'
            CREATE TABLE test_collation (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar/" INTEGER NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            )
            DDL,
            'baz',
            'BINARY',
        ];
    }

    #[DataProvider('columnCommentProvider')]
    public function testParseColumnCommentFromSQL(string $ddl, string $columnName, string $expectedComment): void
    {
        $this->dropTableIfExists('test_comment');
        $this->connection->executeStatement($ddl);

        $schemaManager = $this->connection->createSchemaManager();
        $table         = $schemaManager->introspectTableByUnquotedName('test_comment');

        self::assertSame($expectedComment, $table->getColumn($columnName)->getComment());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function columnCommentProvider(): iterable
    {
        yield 'Single column with no comment' => [
            <<<'SQL'
            CREATE TABLE "test_comment" ("id" TEXT DEFAULT 'a' COLLATE RTRIM)
            SQL,
            'id',
            '',
        ];

        yield 'Single column with type comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" ("a" CLOB DEFAULT NULL COLLATE BINARY --Column a comment
            )
            DDL,
            'a',
            'Column a comment',
        ];

        yield 'Multiple similar columns with type comment 1' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                a TEXT COLLATE RTRIM,
                "b" TEXT DEFAULT 'a' COLLATE RTRIM,
                "bb" CLOB DEFAULT NULL COLLATE BINARY --Column bb comment
            )
            DDL,
            'b',
            '',
        ];

        yield 'Multiple similar columns with type comment 2' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                a TEXT COLLATE RTRIM, "bb" TEXT DEFAULT 'a' COLLATE RTRIM,
                "b" CLOB DEFAULT NULL COLLATE BINARY --Column b comment
            ) 
            DDL,
            'b',
            'Column b comment',
        ];

        yield 'Multiple similar columns on different lines, with type comment 1' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                a TEXT COLLATE RTRIM,
                "b" CLOB DEFAULT NULL COLLATE BINARY, --Column b comment
                "bb" TEXT DEFAULT 'a' COLLATE RTRIM
            )
            DDL,
            'bb',
            '',
        ];

        yield 'Multiple similar columns on different lines, with type comment 2' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                a TEXT COLLATE RTRIM,
                "bb" CLOB DEFAULT NULL COLLATE BINARY, --Column bb comment
                "b" TEXT DEFAULT 'a' COLLATE RTRIM
            )
            DDL,
            'bb',
            'Column bb comment',
        ];

        yield 'Column with numeric but no comment 1' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                "a" NUMERIC(10, 0) NOT NULL,
                "b" CLOB NOT NULL, --Column b comment
                "c" CHAR(36) NOT NULL --Column c comment
            )
            DDL,
            'a',
            '',
        ];

        yield 'Column with numeric but no comment 2' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                "a" NUMERIC(10, 0) NOT NULL,
                "b" CLOB NOT NULL, --Column b comment
                "c" CHAR(36) NOT NULL --Column c comment
            )
            DDL,
            'b',
            'Column b comment',
        ];

        yield 'Column with numeric but no comment 3' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                "a" NUMERIC(10, 0) NOT NULL,
                "b" CLOB NOT NULL, --Column b comment
                "c" CHAR(36) NOT NULL --Column c comment
            )
            DDL,
            'c',
            'Column c comment',
        ];

        yield 'Column "bar", select "bar" with no comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar" VARCHAR(255) NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )
            DDL,
            'bar',
            '',
        ];

        yield 'Column "bar", select "bar" with type comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar" VARCHAR(255) NOT NULL, --Column bar comment
                baz VARCHAR(255) NOT NULL, --Column baz comment
                PRIMARY KEY(id)
            )
            DDL,
            'bar',
            'Column bar comment',
        ];

        yield 'Column "bar", select "baz" with no comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar" INTEGER NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )
            DDL,
            'baz',
            '',
        ];

        yield 'Column "bar", select "baz" with type comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar" INTEGER NOT NULL, --Column bar comment
                baz VARCHAR(255) NOT NULL, --Column baz comment
                PRIMARY KEY(id)
            )
            DDL,
            'baz',
            'Column baz comment',
        ];

        yield 'Column "bar#", select "bar#" with no comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar#" VARCHAR(255) NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )
            DDL,
            'bar#',
            '',
        ];

        yield 'Column "bar#", select "bar#" with type comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar#" VARCHAR(255) NOT NULL, --Column bar comment
                baz VARCHAR(255) NOT NULL, --Column baz comment
                PRIMARY KEY(id)
            )
            DDL,
            'bar#',
            'Column bar comment',
        ];

        yield 'Column "bar#", select "baz" with no comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar#" INTEGER NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )
            DDL,
            'baz',
            '',
        ];

        yield 'Column "bar#", select "baz" with type comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar#" INTEGER NOT NULL, --Column bar comment
                baz VARCHAR(255) NOT NULL, --Column baz comment
                PRIMARY KEY(id)
            )
            DDL,
            'baz',
            'Column baz comment',
        ];

        yield 'Column "bar/", select "bar/" with no comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar/" VARCHAR(255) NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
                )
            DDL,
            'bar/',
            '',
        ];

        yield 'Column "bar/", select "bar/" with type comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar/" VARCHAR(255) NOT NULL, --Column bar comment
                baz VARCHAR(255) NOT NULL, --Column baz comment
                PRIMARY KEY(id)
            )
            DDL,
            'bar/',
            'Column bar comment',
        ];

        yield 'Column "bar/", select "baz" with no comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar/" INTEGER NOT NULL,
                baz VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )
            DDL,
            'baz',
            '',
        ];

        yield 'Column "bar/", select "baz" with type comment' => [
            <<<'DDL'
            CREATE TABLE "test_comment" (
                id INTEGER NOT NULL,
                foo VARCHAR(255) NOT NULL,
                "bar/" INTEGER NOT NULL, --Column bar comment
                baz VARCHAR(255) NOT NULL, --Column baz comment
                PRIMARY KEY(id)
            )
            DDL,
            'baz',
            'Column baz comment',
        ];
    }
}
