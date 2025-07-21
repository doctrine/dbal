<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;
use function array_map;
use function array_shift;
use function array_values;
use function assert;

class SQLiteSchemaManagerTest extends SchemaManagerFunctionalTestCase
{
    use VerifyDeprecations;

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
            new ForeignKeyConstraint(
                ['page'],
                'page',
                ['key'],
                'FK_1',
                ['onUpdate' => 'NO ACTION', 'onDelete' => 'NO ACTION', 'deferrable' => true, 'deferred' => true],
            ),
            new ForeignKeyConstraint(
                ['parent'],
                'user',
                ['id'],
                '',
                ['onUpdate' => 'NO ACTION', 'onDelete' => 'CASCADE', 'deferrable' => false, 'deferred' => false],
            ),
        ];

        $this->expectNoDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6701');

        self::assertEquals($expected, $this->schemaManager->listTableForeignKeys('user'));
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

        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/dbal/pull/6701');

        $expected = [
            new ForeignKeyConstraint(
                ['t2_id'],
                't2',
                [], // @phpstan-ignore argument.type
                '',
                ['onUpdate' => 'NO ACTION', 'onDelete' => 'NO ACTION', 'deferrable' => false, 'deferred' => false],
            ),
        ];

        self::assertEquals($expected, $this->schemaManager->listTableForeignKeys('t1'));
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

        $columns = $this->schemaManager->listTableColumns('test_collation');

        self::assertNull($columns['id']->getCollation());
        self::assertEquals('BINARY', $columns['text']->getCollation());
        self::assertEquals('BINARY', $columns['foo']->getCollation());
        self::assertEquals('NOCASE', $columns['bar']->getCollation());
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

    public function testListTableColumnsWithMixedCaseInTypeDeclarations(): void
    {
        $sql = <<<'SQL'
CREATE TABLE dbal_mixed (
    foo VarChar (64),
    bar Text (100)
)
SQL;

        $this->connection->executeStatement($sql);

        $columns = $this->schemaManager->listTableColumns('dbal_mixed');

        self::assertCount(2, $columns);

        self::assertArrayHasKey('foo', $columns);
        self::assertArrayHasKey('bar', $columns);

        self::assertSame(Type::getType(Types::STRING), $columns['foo']->getType());
        self::assertSame(Type::getType(Types::TEXT), $columns['bar']->getType());
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
        $table2 = $table1->edit()
            ->addIndex(
                Index::editor()
                    ->setUnquotedName('idx_name')
                    ->setUnquotedColumnNames('name')
                    ->create(),
            )
            ->create();

        $comparator = $schemaManager->createComparator();
        $diff       = $comparator->compareTables($table1, $table2);

        $schemaManager->alterTable($diff);

        $table = $schemaManager->introspectTable('nodes');
        $index = $table->getIndex('idx_name');
        self::assertSame(['name'], $index->getColumns());
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

        self::assertSame(['a'], array_keys($this->schemaManager->listTableColumns('t')));

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
          PRIMARY KEY(id)
        );

        CREATE TABLE album(
          id INTEGER,
          name TEXT,
          PRIMARY KEY(id)
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

        self::assertSame(['album_id'], $foreignKey1->getLocalColumns());
        self::assertSame(['id'], $foreignKey1->getForeignColumns());

        $foreignKey2 = $foreignKeys[1];
        self::assertEmpty($foreignKey2->getName());

        self::assertSame(['artist_id'], $foreignKey2->getLocalColumns());
        self::assertSame(['id'], $foreignKey2->getForeignColumns());
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

        self::assertSame(['created_by'], $foreignKey->getLocalColumns());
        self::assertSame('users', $foreignKey->getForeignTableName());
        self::assertSame(['id'], $foreignKey->getForeignColumns());
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

        self::assertSame(['trackartist'], $foreignKey1->getLocalColumns());
        self::assertSame(['artistid'], $foreignKey1->getForeignColumns());
    }

    public function testShorthandInForeignKeyReferenceWithMultipleColumns(): void
    {
        $this->dropTableIfExists('artist');
        $this->dropTableIfExists('track');

        $ddl = <<<'DDL'
        CREATE TABLE artist(
            artistid INTEGER,
            isrc TEXT,
            artistname TEXT,
            PRIMARY KEY (artistid, isrc)
        );

        CREATE TABLE track(
            trackid INTEGER,
            trackname TEXT,
            trackartist INTEGER REFERENCES artist
        );
        DDL;

        $this->connection->executeStatement($ddl);

        $schemaManager = $this->connection->createSchemaManager();

        $track       = $schemaManager->introspectTable('track');
        $foreignKeys = $track->getForeignKeys();
        self::assertCount(1, $foreignKeys);

        $foreignKey1 = array_shift($foreignKeys);
        self::assertNotNull($foreignKey1);
        self::assertEmpty($foreignKey1->getName());

        self::assertSame(['trackartist'], $foreignKey1->getLocalColumns());
        self::assertSame(['artistid', 'isrc'], $foreignKey1->getForeignColumns());

        $createTableTrackSql = $this->connection->getDatabasePlatform()->getCreateTableSQL($track);

        self::assertSame(
            [
                'CREATE TABLE track (trackid INTEGER DEFAULT NULL, trackname CLOB DEFAULT NULL COLLATE "BINARY",'
                . ' trackartist INTEGER DEFAULT NULL, FOREIGN KEY (trackartist) REFERENCES artist (artistid, isrc) ON'
                . ' UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)',
                'CREATE INDEX IDX_D6E3F8A6FB96D8BC ON track (trackartist)',
            ],
            $createTableTrackSql,
        );
    }

    public function testListTableNoSchemaEmulation(): void
    {
        $databasePlatform = $this->connection->getDatabasePlatform();
        assert($databasePlatform instanceof SQLitePlatform);

        $this->dropTableIfExists('`list_table_no_schema_emulation.test`');

        $this->connection->executeStatement(<<<'DDL'
            CREATE TABLE `list_table_no_schema_emulation.test` (
                id INTEGER,
                parent_id INTEGER,
                PRIMARY KEY (id),
                FOREIGN KEY (parent_id) REFERENCES `list_table_no_schema_emulation.test` (id)
            );
            DDL);

        $this->connection->executeStatement(<<<'DDL'
            CREATE INDEX i ON `list_table_no_schema_emulation.test` (parent_id);
            DDL);

        $customSQLiteSchemaManager = new class ($this->connection, $databasePlatform) extends SQLiteSchemaManager {
            /** @return list<array<string, mixed>> */
            public function selectTableColumnsWithSchema(): array
            {
                return $this->selectTableColumns('main', 'list_table_no_schema_emulation.test')
                    ->fetchAllAssociative();
            }

            /** @return list<array<string, mixed>> */
            public function selectIndexColumnsWithSchema(): array
            {
                return $this->selectIndexColumns('main', 'list_table_no_schema_emulation.test')
                    ->fetchAllAssociative();
            }

            /** @return list<array<string, mixed>> */
            public function selectForeignKeyColumnsWithSchema(): array
            {
                return $this->selectForeignKeyColumns('main', 'list_table_no_schema_emulation.test')
                    ->fetchAllAssociative();
            }
        };

        self::assertSame(
            [
                ['list_table_no_schema_emulation.test', 'id'],
                ['list_table_no_schema_emulation.test', 'parent_id'],
            ],
            array_map(
                static fn (array $row) => [$row['table_name'], $row['name']],
                $customSQLiteSchemaManager->selectTableColumnsWithSchema(),
            ),
        );

        self::assertSame(
            [
                ['list_table_no_schema_emulation.test', 'i'],
            ],
            array_map(
                static fn (array $row) => [$row['table_name'], $row['name']],
                $customSQLiteSchemaManager->selectIndexColumnsWithSchema(),
            ),
        );

        self::assertSame(
            [
                ['list_table_no_schema_emulation.test', 'parent_id', 'id'],
            ],
            array_map(
                static fn (array $row) => [$row['table_name'], $row['from'], $row['to']],
                $customSQLiteSchemaManager->selectForeignKeyColumnsWithSchema(),
            ),
        );
    }

    /**
     * This test duplicates {@see parent::testCommentInTable()} with the only difference that the name of the table
     * being created is quoted. It is only meant to cover the logic of parsing the SQLite CREATE TABLE statement
     * when the table name is quoted.
     *
     * Running the same test for all platforms, on the one hand, won't produce additional coverage, and on the other,
     * is not feasible due to the differences in case sensitivity depending on whether the name is quoted.
     *
     * Once all identifiers are quoted by default, this test can be removed.
     */
    public function testCommentInQuotedTable(): void
    {
        $table = Table::editor()
            ->setQuotedName('table_with_comment')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('id')
                    ->setTypeName(Types::INTEGER)
                    ->create(),
            )
            ->setComment('This is a comment')
            ->create();

        $this->dropAndCreateTable($table);

        $table = $this->schemaManager->introspectTable('table_with_comment');
        self::assertSame('This is a comment', $table->getComment());
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
        $table         = $schemaManager->introspectTable('test_collation');

        self::assertSame($expectedCollation, $table->getColumn($columnName)->getCollation());
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
        $table         = $schemaManager->introspectTable('test_comment');

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
