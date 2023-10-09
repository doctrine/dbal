<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MySQL;
use Doctrine\DBAL\Platforms\MySQL\CharsetMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
use Doctrine\DBAL\Platforms\MySQL\DefaultTableOptions;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\TransactionIsolationLevel;
use Doctrine\DBAL\Types\Types;

use function array_shift;

/** @extends AbstractPlatformTestCase<AbstractMySQLPlatform> */
abstract class AbstractMySQLPlatformTestCase extends AbstractPlatformTestCase
{
    public function testModifyLimitQueryWithoutLimit(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT n FROM Foo', null, 10);
        self::assertEquals('SELECT n FROM Foo LIMIT 18446744073709551615 OFFSET 10', $sql);
    }

    public function testGenerateMixedCaseTableCreate(): void
    {
        $table = new Table('Foo');
        $table->addColumn('Bar', Types::INTEGER);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals(
            'CREATE TABLE Foo (Bar INT NOT NULL)',
            array_shift($sql),
        );
    }

    public function getGenerateTableSql(): string
    {
        return 'CREATE TABLE test (id INT AUTO_INCREMENT NOT NULL, test VARCHAR(255) DEFAULT NULL, '
            . 'PRIMARY KEY(id))';
    }

    /** @return string[] */
    public function getGenerateTableWithMultiColumnUniqueIndexSql(): array
    {
        return [
            'CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL, '
                . 'UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA (foo, bar))',
        ];
    }

    public function testGeneratesSqlSnippets(): void
    {
        self::assertEquals('RLIKE', $this->platform->getRegexpExpression());
        self::assertEquals(
            'CONCAT(column1, column2, column3)',
            $this->platform->getConcatExpression('column1', 'column2', 'column3'),
        );
    }

    public function testGeneratesTransactionsCommands(): void
    {
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED),
            '',
        );
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED),
        );
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ),
        );
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE),
        );
    }

    public function testGeneratesDDLSnippets(): void
    {
        self::assertEquals('SHOW DATABASES', $this->platform->getListDatabasesSQL());
        self::assertEquals('CREATE DATABASE foobar', $this->platform->getCreateDatabaseSQL('foobar'));
        self::assertEquals('DROP DATABASE foobar', $this->platform->getDropDatabaseSQL('foobar'));
        self::assertEquals('DROP TABLE foobar', $this->platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers(): void
    {
        self::assertEquals(
            'INT',
            $this->platform->getIntegerTypeDeclarationSQL([]),
        );
        self::assertEquals(
            'INT AUTO_INCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true]),
        );
        self::assertEquals(
            'INT AUTO_INCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true],
            ),
        );
    }

    public function testSupportsIdentityColumns(): void
    {
        self::assertTrue($this->platform->supportsIdentityColumns());
    }

    public function testDoesSupportSavePoints(): void
    {
        self::assertTrue($this->platform->supportsSavepoints());
    }

    public function getGenerateIndexSql(): string
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql(): string
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    protected function getGenerateForeignKeySql(): string
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    public function testUniquePrimaryKey(): void
    {
        $keyTable = new Table('foo');
        $keyTable->addColumn('bar', Types::INTEGER);
        $keyTable->addColumn('baz', Types::STRING, ['length' => 32]);
        $keyTable->setPrimaryKey(['bar']);
        $keyTable->addUniqueIndex(['baz']);

        $oldTable = new Table('foo');
        $oldTable->addColumn('bar', Types::INTEGER);
        $oldTable->addColumn('baz', Types::STRING, ['length' => 32]);

        $diff = $this->createComparator()
            ->compareTables($oldTable, $keyTable);

        $sql = $this->platform->getAlterTableSQL($diff);

        self::assertEquals([
            'ALTER TABLE foo ADD PRIMARY KEY (bar)',
            'CREATE UNIQUE INDEX UNIQ_8C73652178240498 ON foo (baz)',
        ], $sql);
    }

    public function testModifyLimitQuery(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset(): void
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function testGetDateTimeTypeDeclarationSql(): void
    {
        self::assertEquals('DATETIME', $this->platform->getDateTimeTypeDeclarationSQL(['version' => false]));
        self::assertEquals('TIMESTAMP', $this->platform->getDateTimeTypeDeclarationSQL(['version' => true]));
        self::assertEquals('DATETIME', $this->platform->getDateTimeTypeDeclarationSQL([]));
    }

    /** @return string[] */
    protected function getQuotedColumnInPrimaryKeySQL(): array
    {
        return ['CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, '
                . 'PRIMARY KEY(`create`))',
        ];
    }

    /** @return string[] */
    protected function getQuotedColumnInIndexSQL(): array
    {
        return [
            'CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, '
                . 'INDEX IDX_22660D028FD6E0FB (`create`))',
        ];
    }

    /** @return string[] */
    protected function getQuotedNameInIndexSQL(): array
    {
        return [
            'CREATE TABLE test (column1 VARCHAR(255) NOT NULL, '
                . 'INDEX `key` (column1))',
        ];
    }

    /** @return string[] */
    protected function getQuotedColumnInForeignKeySQL(): array
    {
        return [
            'CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, '
                . '`bar` VARCHAR(255) NOT NULL, INDEX IDX_22660D028FD6E0FB8C736521D79164E3 (`create`, foo, `bar`))',
            'ALTER TABLE `quoted` ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY (`create`, foo, `bar`)'
                . ' REFERENCES `foreign` (`create`, bar, `foo-bar`)',
            'ALTER TABLE `quoted` ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY (`create`, foo, `bar`)'
                . ' REFERENCES foo (`create`, bar, `foo-bar`)',
            'ALTER TABLE `quoted` ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY (`create`, foo, `bar`)'
                . ' REFERENCES `foo-bar` (`create`, bar, `foo-bar`)',
        ];
    }

    public function testCreateTableWithFulltextIndex(): void
    {
        $table = new Table('fulltext_table');
        $table->addOption('engine', 'MyISAM');
        $table->addColumn('text', Types::TEXT);
        $table->addIndex(['text'], 'fulltext_text');

        $index = $table->getIndex('fulltext_text');
        $index->addFlag('fulltext');

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals(
            [
                'CREATE TABLE fulltext_table (text LONGTEXT NOT NULL, '
                . 'FULLTEXT INDEX fulltext_text (text)) '
                . 'ENGINE = MyISAM',
            ],
            $sql,
        );
    }

    public function testCreateTableWithSpatialIndex(): void
    {
        $table = new Table('spatial_table');
        $table->addOption('engine', 'MyISAM');
        $table->addColumn('point', Types::TEXT); // This should be a point type
        $table->addIndex(['point'], 'spatial_text');

        $index = $table->getIndex('spatial_text');
        $index->addFlag('spatial');

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals(
            [
                'CREATE TABLE spatial_table (point LONGTEXT NOT NULL, SPATIAL INDEX spatial_text (point)) '
                . 'ENGINE = MyISAM',
            ],
            $sql,
        );
    }

    public function testClobTypeDeclarationSQL(): void
    {
        self::assertEquals('TINYTEXT', $this->platform->getClobTypeDeclarationSQL(['length' => 1]));
        self::assertEquals('TINYTEXT', $this->platform->getClobTypeDeclarationSQL(['length' => 255]));
        self::assertEquals('TEXT', $this->platform->getClobTypeDeclarationSQL(['length' => 256]));
        self::assertEquals('TEXT', $this->platform->getClobTypeDeclarationSQL(['length' => 65535]));
        self::assertEquals('MEDIUMTEXT', $this->platform->getClobTypeDeclarationSQL(['length' => 65536]));
        self::assertEquals('MEDIUMTEXT', $this->platform->getClobTypeDeclarationSQL(['length' => 16777215]));
        self::assertEquals('LONGTEXT', $this->platform->getClobTypeDeclarationSQL(['length' => 16777216]));
        self::assertEquals('LONGTEXT', $this->platform->getClobTypeDeclarationSQL([]));
    }

    public function testBlobTypeDeclarationSQL(): void
    {
        self::assertEquals('TINYBLOB', $this->platform->getBlobTypeDeclarationSQL(['length' => 1]));
        self::assertEquals('TINYBLOB', $this->platform->getBlobTypeDeclarationSQL(['length' => 255]));
        self::assertEquals('BLOB', $this->platform->getBlobTypeDeclarationSQL(['length' => 256]));
        self::assertEquals('BLOB', $this->platform->getBlobTypeDeclarationSQL(['length' => 65535]));
        self::assertEquals('MEDIUMBLOB', $this->platform->getBlobTypeDeclarationSQL(['length' => 65536]));
        self::assertEquals('MEDIUMBLOB', $this->platform->getBlobTypeDeclarationSQL(['length' => 16777215]));
        self::assertEquals('LONGBLOB', $this->platform->getBlobTypeDeclarationSQL(['length' => 16777216]));
        self::assertEquals('LONGBLOB', $this->platform->getBlobTypeDeclarationSQL([]));
    }

    public function testAlterTableAddPrimaryKey(): void
    {
        $table = new Table('alter_table_add_pk');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('foo', Types::INTEGER);
        $table->addIndex(['id'], 'idx_id');

        $diffTable = clone $table;

        $diffTable->dropIndex('idx_id');
        $diffTable->setPrimaryKey(['id']);

        $diff = $this->createComparator()
            ->compareTables($table, $diffTable);

        self::assertEquals(
            ['DROP INDEX idx_id ON alter_table_add_pk', 'ALTER TABLE alter_table_add_pk ADD PRIMARY KEY (id)'],
            $this->platform->getAlterTableSQL($diff),
        );
    }

    public function testAlterPrimaryKeyWithAutoincrementColumn(): void
    {
        $table = new Table('alter_primary_key');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('foo', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(['foo']);

        $diff = $this->createComparator()
            ->compareTables($table, $diffTable);

        self::assertEquals(
            [
                'ALTER TABLE alter_primary_key MODIFY id INT NOT NULL',
                'DROP INDEX `primary` ON alter_primary_key',
                'ALTER TABLE alter_primary_key ADD PRIMARY KEY (foo)',
            ],
            $this->platform->getAlterTableSQL($diff),
        );
    }

    public function testDropPrimaryKeyWithAutoincrementColumn(): void
    {
        $table = new Table('drop_primary_key');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('foo', Types::INTEGER);
        $table->addColumn('bar', Types::INTEGER);
        $table->setPrimaryKey(['id', 'foo']);

        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();

        $diff = $this->createComparator()
            ->compareTables($table, $diffTable);

        self::assertEquals(
            [
                'ALTER TABLE drop_primary_key MODIFY id INT NOT NULL',
                'DROP INDEX `primary` ON drop_primary_key',
            ],
            $this->platform->getAlterTableSQL($diff),
        );
    }

    public function testDropNonAutoincrementColumnFromCompositePrimaryKeyWithAutoincrementColumn(): void
    {
        $table = new Table('tbl');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('foo', Types::INTEGER);
        $table->addColumn('bar', Types::INTEGER);
        $table->setPrimaryKey(['id', 'foo']);

        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(['id']);

        $diff = $this->createComparator()
            ->compareTables($table, $diffTable);

        self::assertSame(
            [
                'ALTER TABLE tbl MODIFY id INT NOT NULL',
                'DROP INDEX `primary` ON tbl',
                'ALTER TABLE tbl ADD PRIMARY KEY (id)',
            ],
            $this->platform->getAlterTableSQL($diff),
        );
    }

    public function testAddNonAutoincrementColumnToPrimaryKeyWithAutoincrementColumn(): void
    {
        $table = new Table('tbl');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('foo', Types::INTEGER);
        $table->addColumn('bar', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $diffTable = clone $table;

        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(['id', 'foo']);

        $diff = $this->createComparator()
            ->compareTables($table, $diffTable);

        self::assertSame(
            [
                'ALTER TABLE tbl MODIFY id INT NOT NULL',
                'DROP INDEX `primary` ON tbl',
                'ALTER TABLE tbl ADD PRIMARY KEY (id, foo)',
            ],
            $this->platform->getAlterTableSQL($diff),
        );
    }

    public function testAddAutoIncrementPrimaryKey(): void
    {
        $keyTable = new Table('foo');
        $keyTable->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $keyTable->addColumn('baz', Types::STRING, ['length' => 32]);
        $keyTable->setPrimaryKey(['id']);

        $oldTable = new Table('foo');
        $oldTable->addColumn('baz', Types::STRING, ['length' => 32]);

        $diff = $this->createComparator()
            ->compareTables($oldTable, $keyTable);

        $sql = $this->platform->getAlterTableSQL($diff);

        self::assertEquals(['ALTER TABLE foo ADD id INT AUTO_INCREMENT NOT NULL, ADD PRIMARY KEY (id)'], $sql);
    }

    public function testAlterPrimaryKeyWithNewColumn(): void
    {
        $table = new Table('yolo');
        $table->addColumn('pkc1', Types::INTEGER);
        $table->addColumn('col_a', Types::INTEGER);
        $table->setPrimaryKey(['pkc1']);

        $diffTable = clone $table;

        $diffTable->addColumn('pkc2', Types::INTEGER);
        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(['pkc1', 'pkc2']);

        $diff = $this->createComparator()
            ->compareTables($table, $diffTable);

        self::assertSame(
            [
                'DROP INDEX `primary` ON yolo',
                'ALTER TABLE yolo ADD pkc2 INT NOT NULL',
                'ALTER TABLE yolo ADD PRIMARY KEY (pkc1, pkc2)',
            ],
            $this->platform->getAlterTableSQL($diff),
        );
    }

    public function testInitializesDoctrineTypeMappings(): void
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('binary'));
        self::assertSame(Types::BINARY, $this->platform->getDoctrineTypeMapping('binary'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('varbinary'));
        self::assertSame(Types::BINARY, $this->platform->getDoctrineTypeMapping('varbinary'));
    }

    public function testGetVariableLengthStringTypeDeclarationSQLNoLength(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);

        parent::testGetVariableLengthStringTypeDeclarationSQLNoLength();
    }

    public function testGetVariableLengthBinaryTypeDeclarationSQLNoLength(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);

        parent::testGetVariableLengthBinaryTypeDeclarationSQLNoLength();
    }

    /** @return string[] */
    protected function getAlterTableRenameIndexSQL(): array
    {
        return [
            'DROP INDEX idx_foo ON mytable',
            'CREATE INDEX idx_bar ON mytable (id)',
        ];
    }

    /** @return string[] */
    protected function getQuotedAlterTableRenameIndexSQL(): array
    {
        return [
            'DROP INDEX `create` ON `table`',
            'CREATE INDEX `select` ON `table` (id)',
            'DROP INDEX `foo` ON `table`',
            'CREATE INDEX `bar` ON `table` (id)',
        ];
    }

    /** @return string[] */
    protected function getAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            'DROP INDEX idx_foo ON myschema.mytable',
            'CREATE INDEX idx_bar ON myschema.mytable (id)',
        ];
    }

    /** @return string[] */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            'DROP INDEX `create` ON `schema`.`table`',
            'CREATE INDEX `select` ON `schema`.`table` (id)',
            'DROP INDEX `foo` ON `schema`.`table`',
            'CREATE INDEX `bar` ON `schema`.`table` (id)',
        ];
    }

    public function testIgnoresDifferenceInDefaultValuesForUnsupportedColumnTypes(): void
    {
        $table = new Table('text_blob_default_value');
        $table->addColumn('def_text', Types::TEXT, ['default' => 'def']);
        $table->addColumn('def_text_null', Types::TEXT, ['notnull' => false, 'default' => 'def']);
        $table->addColumn('def_blob', Types::BLOB, ['default' => 'def']);
        $table->addColumn('def_blob_null', Types::BLOB, ['notnull' => false, 'default' => 'def']);

        self::assertSame(
            [
                'CREATE TABLE text_blob_default_value (def_text LONGTEXT NOT NULL, '
                    . 'def_text_null LONGTEXT DEFAULT NULL, '
                    . 'def_blob LONGBLOB NOT NULL, '
                    . 'def_blob_null LONGBLOB DEFAULT NULL'
                    . ')',
            ],
            $this->platform->getCreateTableSQL($table),
        );

        $diffTable = clone $table;
        $diffTable->modifyColumn('def_text', ['default' => null]);
        $diffTable->modifyColumn('def_text_null', ['default' => null]);
        $diffTable->modifyColumn('def_blob', ['default' => null]);
        $diffTable->modifyColumn('def_blob_null', ['default' => null]);

        self::assertTrue(
            $this->createComparator()
                ->compareTables($table, $diffTable)
                ->isEmpty(),
        );
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        self::assertSame('CHAR(36)', $this->platform->getGuidTypeDeclarationSQL([]));
    }

    /**
     * {@inheritDoc}
     */
    protected function getCommentOnColumnSQL(): array
    {
        return [
            "COMMENT ON COLUMN foo.bar IS 'comment'",
            "COMMENT ON COLUMN `Foo`.`BAR` IS 'comment'",
            "COMMENT ON COLUMN `select`.`from` IS 'comment'",
        ];
    }

    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): string
    {
        return 'CONSTRAINT `select` UNIQUE (foo)';
    }

    protected function getQuotesReservedKeywordInIndexDeclarationSQL(): string
    {
        return 'INDEX `select` (foo)';
    }

    protected function getQuotesReservedKeywordInTruncateTableSQL(): string
    {
        return 'TRUNCATE `select`';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAlterStringToFixedStringSQL(): array
    {
        return ['ALTER TABLE mytable CHANGE name name CHAR(2) NOT NULL'];
    }

    /**
     * {@inheritDoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array
    {
        return [
            'ALTER TABLE mytable DROP FOREIGN KEY fk_foo',
            'DROP INDEX idx_foo ON mytable',
            'CREATE INDEX idx_foo_renamed ON mytable (foo)',
            'ALTER TABLE mytable ADD CONSTRAINT fk_foo FOREIGN KEY (foo) REFERENCES foreign_table (id)',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public static function getGeneratesDecimalTypeDeclarationSQL(): iterable
    {
        yield [['precision' => 10, 'scale' => 8, 'unsigned' => true], 'NUMERIC(10, 8) UNSIGNED'];

        yield from parent::getGeneratesDecimalTypeDeclarationSQL();
    }

    /**
     * {@inheritDoc}
     */
    public static function getGeneratesFloatDeclarationSQL(): iterable
    {
        return [
            [[], 'DOUBLE PRECISION'],
            [['unsigned' => true], 'DOUBLE PRECISION UNSIGNED'],
            [['unsigned' => false], 'DOUBLE PRECISION'],
            [['precision' => 5], 'DOUBLE PRECISION'],
            [['scale' => 5], 'DOUBLE PRECISION'],
            [['precision' => 8, 'scale' => 2], 'DOUBLE PRECISION'],
        ];
    }

    public function testQuotesDatabaseNameInListViewsSQL(): void
    {
        self::assertStringContainsStringIgnoringCase(
            "'Foo''Bar\\\\'",
            $this->platform->getListViewsSQL("Foo'Bar\\"),
        );
    }

    public function testColumnCharsetDeclarationSQL(): void
    {
        self::assertSame(
            'CHARACTER SET ascii',
            $this->platform->getColumnCharsetDeclarationSQL('ascii'),
        );
    }

    public function testSupportsColumnCollation(): void
    {
        self::assertTrue($this->platform->supportsColumnCollation());
    }

    public function testColumnCollationDeclarationSQL(): void
    {
        self::assertSame(
            'COLLATE `ascii_general_ci`',
            $this->platform->getColumnCollationDeclarationSQL('ascii_general_ci'),
        );
    }

    public function testGetCreateTableSQLWithColumnCollation(): void
    {
        $table = new Table('foo');
        $table->addColumn('no_collation', Types::STRING, ['length' => 255]);
        $table->addColumn('column_collation', Types::STRING, ['length' => 255])
            ->setPlatformOption('collation', 'ascii_general_ci');

        self::assertSame(
            [
                'CREATE TABLE foo (no_collation VARCHAR(255) NOT NULL, '
                    . 'column_collation VARCHAR(255) NOT NULL COLLATE `ascii_general_ci`)',
            ],
            $this->platform->getCreateTableSQL($table),
        );
    }

    public function testQuoteIdentifier(): void
    {
        self::assertEquals('`test`.`test`', $this->platform->quoteIdentifier('test.test'));
    }

    protected function createComparator(): Comparator
    {
        return new MySQL\Comparator(
            $this->platform,
            self::createStub(CharsetMetadataProvider::class),
            self::createStub(CollationMetadataProvider::class),
            new DefaultTableOptions('utf8mb4', 'utf8mb4_general_ci'),
        );
    }
}
