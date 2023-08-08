<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_shift;

class SqliteSchemaManagerTest extends SchemaManagerFunctionalTestCase
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

    public function testListForeignKeysFromExistingDatabase(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS user');
        $this->connection->executeStatement(<<<'EOS'
CREATE TABLE user (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page INTEGER CONSTRAINT FK_1 REFERENCES page (key) DEFERRABLE INITIALLY DEFERRED,
    parent INTEGER REFERENCES user(id) ON DELETE CASCADE,
    log INTEGER,
    CONSTRAINT FK_3 FOREIGN KEY (log) REFERENCES log ON UPDATE SET NULL NOT DEFERRABLE
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
            new ForeignKeyConstraint(
                ['log'],
                'log',
                [],
                'FK_3',
                ['onUpdate' => 'SET NULL', 'onDelete' => 'NO ACTION', 'deferrable' => false, 'deferred' => false],
            ),
        ];

        self::assertEquals($expected, $this->schemaManager->listTableForeignKeys('user'));
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
        $table->setPrimaryKey(['id']);
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
        $table2->addIndex(['name'], 'idx_name');

        $comparator = $schemaManager->createComparator();
        $diff       = $comparator->compareTables($table1, $table2);

        $schemaManager->alterTable($diff);

        $table = $schemaManager->introspectTable('nodes');
        $index = $table->getIndex('idx_name');
        self::assertSame(['name'], $index->getColumns());
    }

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

        $song        = $schemaManager->introspectTable('song');
        $foreignKeys = $song->getForeignKeys();
        self::assertCount(2, $foreignKeys);

        $foreignKey1 = array_shift($foreignKeys);
        self::assertNotNull($foreignKey1);
        self::assertEmpty($foreignKey1->getName());

        self::assertSame(['album_id'], $foreignKey1->getLocalColumns());
        self::assertSame(['id'], $foreignKey1->getForeignColumns());

        $foreignKey2 = array_shift($foreignKeys);
        self::assertNotNull($foreignKey2);
        self::assertEmpty($foreignKey2->getName());

        self::assertSame(['artist_id'], $foreignKey2->getLocalColumns());
        self::assertSame(['id'], $foreignKey2->getForeignColumns());
    }

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

        $foreignKeys = $notes->getForeignKeys();
        self::assertCount(1, $foreignKeys);

        $foreignKey = array_shift($foreignKeys);
        self::assertNotNull($foreignKey);
        self::assertSame(['created_by'], $foreignKey->getLocalColumns());
        self::assertSame('users', $foreignKey->getForeignTableName());
        self::assertSame(['id'], $foreignKey->getForeignColumns());
    }

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

        $song        = $schemaManager->introspectTable('track');
        $foreignKeys = $song->getForeignKeys();
        self::assertCount(1, $foreignKeys);

        $foreignKey1 = array_shift($foreignKeys);
        self::assertNotNull($foreignKey1);
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
}
