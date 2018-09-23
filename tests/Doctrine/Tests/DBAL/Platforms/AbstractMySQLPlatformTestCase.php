<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use function array_shift;

abstract class AbstractMySQLPlatformTestCase extends AbstractPlatformTestCase
{
    public function testModifyLimitQueryWitoutLimit()
    {
        $sql = $this->platform->modifyLimitQuery('SELECT n FROM Foo', null, 10);
        self::assertEquals('SELECT n FROM Foo LIMIT 18446744073709551615 OFFSET 10', $sql);
    }

    public function testGenerateMixedCaseTableCreate()
    {
        $table = new Table('Foo');
        $table->addColumn('Bar', 'integer');

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals('CREATE TABLE Foo (Bar INT NOT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB', array_shift($sql));
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INT AUTO_INCREMENT NOT NULL, test VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return ['CREATE TABLE test (foo VARCHAR(255) DEFAULT NULL, bar VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA (foo, bar)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'];
    }

    public function getGenerateAlterTableSql()
    {
        return ["ALTER TABLE mytable RENAME TO userlist, ADD quota INT DEFAULT NULL, DROP foo, CHANGE bar baz VARCHAR(255) DEFAULT 'def' NOT NULL, CHANGE bloo bloo TINYINT(1) DEFAULT '0' NOT NULL"];
    }

    public function testGeneratesSqlSnippets()
    {
        self::assertEquals('RLIKE', $this->platform->getRegexpExpression(), 'Regular expression operator is not correct');
        self::assertEquals('`', $this->platform->getIdentifierQuoteCharacter(), 'Quote character is not correct');
        self::assertEquals('CONCAT(column1, column2, column3)', $this->platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation function is not correct');
    }

    public function testGeneratesTransactionsCommands()
    {
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_UNCOMMITTED),
            ''
        );
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::READ_COMMITTED)
        );
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::REPEATABLE_READ)
        );
        self::assertEquals(
            'SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE',
            $this->platform->getSetTransactionIsolationSQL(TransactionIsolationLevel::SERIALIZABLE)
        );
    }


    public function testGeneratesDDLSnippets()
    {
        self::assertEquals('SHOW DATABASES', $this->platform->getListDatabasesSQL());
        self::assertEquals('CREATE DATABASE foobar', $this->platform->getCreateDatabaseSQL('foobar'));
        self::assertEquals('DROP DATABASE foobar', $this->platform->getDropDatabaseSQL('foobar'));
        self::assertEquals('DROP TABLE foobar', $this->platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        self::assertEquals(
            'INT',
            $this->platform->getIntegerTypeDeclarationSQL([])
        );
        self::assertEquals(
            'INT AUTO_INCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(['autoincrement' => true])
        );
        self::assertEquals(
            'INT AUTO_INCREMENT',
            $this->platform->getIntegerTypeDeclarationSQL(
                ['autoincrement' => true, 'primary' => true]
            )
        );
    }

    public function testGeneratesTypeDeclarationForStrings()
    {
        self::assertEquals(
            'CHAR(10)',
            $this->platform->getVarcharTypeDeclarationSQL(
                ['length' => 10, 'fixed' => true]
            )
        );
        self::assertEquals(
            'VARCHAR(50)',
            $this->platform->getVarcharTypeDeclarationSQL(['length' => 50]),
            'Variable string declaration is not correct'
        );
        self::assertEquals(
            'VARCHAR(255)',
            $this->platform->getVarcharTypeDeclarationSQL([]),
            'Long string declaration is not correct'
        );
    }

    public function testPrefersIdentityColumns()
    {
        self::assertTrue($this->platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        self::assertTrue($this->platform->supportsIdentityColumns());
    }

    public function testDoesSupportSavePoints()
    {
        self::assertTrue($this->platform->supportsSavepoints());
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2)';
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    /**
     * @group DBAL-126
     */
    public function testUniquePrimaryKey()
    {
        $keyTable = new Table('foo');
        $keyTable->addColumn('bar', 'integer');
        $keyTable->addColumn('baz', 'string');
        $keyTable->setPrimaryKey(['bar']);
        $keyTable->addUniqueIndex(['baz']);

        $oldTable = new Table('foo');
        $oldTable->addColumn('bar', 'integer');
        $oldTable->addColumn('baz', 'string');

        $c    = new Comparator();
        $diff = $c->diffTable($oldTable, $keyTable);

        $sql = $this->platform->getAlterTableSQL($diff);

        self::assertEquals([
            'ALTER TABLE foo ADD PRIMARY KEY (bar)',
            'CREATE UNIQUE INDEX UNIQ_8C73652178240498 ON foo (baz)',
        ], $sql);
    }

    public function testModifyLimitQuery()
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $sql = $this->platform->modifyLimitQuery('SELECT * FROM user', 10);
        self::assertEquals('SELECT * FROM user LIMIT 10', $sql);
    }

    /**
     * @group DDC-118
     */
    public function testGetDateTimeTypeDeclarationSql()
    {
        self::assertEquals('DATETIME', $this->platform->getDateTimeTypeDeclarationSQL(['version' => false]));
        self::assertEquals('TIMESTAMP', $this->platform->getDateTimeTypeDeclarationSQL(['version' => true]));
        self::assertEquals('DATETIME', $this->platform->getDateTimeTypeDeclarationSQL([]));
    }

    public function getCreateTableColumnCommentsSQL()
    {
        return ["CREATE TABLE test (id INT NOT NULL COMMENT 'This is a comment', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB"];
    }

    public function getAlterTableColumnCommentsSQL()
    {
        return ["ALTER TABLE mytable ADD quota INT NOT NULL COMMENT 'A comment', CHANGE foo foo VARCHAR(255) NOT NULL, CHANGE bar baz VARCHAR(255) NOT NULL COMMENT 'B comment'"];
    }

    public function getCreateTableColumnTypeCommentsSQL()
    {
        return ["CREATE TABLE test (id INT NOT NULL, data LONGTEXT NOT NULL COMMENT '(DC2Type:array)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB"];
    }

    /**
     * @group DBAL-237
     */
    public function testChangeIndexWithForeignKeys()
    {
        $index  = new Index('idx', ['col'], false);
        $unique = new Index('uniq', ['col'], true);

        $diff = new TableDiff('test', [], [], [], [$unique], [], [$index]);
        $sql  = $this->platform->getAlterTableSQL($diff);
        self::assertEquals(['ALTER TABLE test DROP INDEX idx, ADD UNIQUE INDEX uniq (col)'], $sql);

        $diff = new TableDiff('test', [], [], [], [$index], [], [$unique]);
        $sql  = $this->platform->getAlterTableSQL($diff);
        self::assertEquals(['ALTER TABLE test DROP INDEX uniq, ADD INDEX idx (col)'], $sql);
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return ['CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, PRIMARY KEY(`create`)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'];
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return ['CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, INDEX IDX_22660D028FD6E0FB (`create`)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'];
    }

    protected function getQuotedNameInIndexSQL()
    {
        return ['CREATE TABLE test (column1 VARCHAR(255) NOT NULL, INDEX `key` (column1)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'];
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return [
            'CREATE TABLE `quoted` (`create` VARCHAR(255) NOT NULL, foo VARCHAR(255) NOT NULL, `bar` VARCHAR(255) NOT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB',
            'ALTER TABLE `quoted` ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY (`create`, foo, `bar`) REFERENCES `foreign` (`create`, bar, `foo-bar`)',
            'ALTER TABLE `quoted` ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY (`create`, foo, `bar`) REFERENCES foo (`create`, bar, `foo-bar`)',
            'ALTER TABLE `quoted` ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY (`create`, foo, `bar`) REFERENCES `foo-bar` (`create`, bar, `foo-bar`)',
        ];
    }

    public function testCreateTableWithFulltextIndex()
    {
        $table = new Table('fulltext_table');
        $table->addOption('engine', 'MyISAM');
        $table->addColumn('text', 'text');
        $table->addIndex(['text'], 'fulltext_text');

        $index = $table->getIndex('fulltext_text');
        $index->addFlag('fulltext');

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals(['CREATE TABLE fulltext_table (text LONGTEXT NOT NULL, FULLTEXT INDEX fulltext_text (text)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = MyISAM'], $sql);
    }

    public function testCreateTableWithSpatialIndex()
    {
        $table = new Table('spatial_table');
        $table->addOption('engine', 'MyISAM');
        $table->addColumn('point', 'text'); // This should be a point type
        $table->addIndex(['point'], 'spatial_text');

        $index = $table->getIndex('spatial_text');
        $index->addFlag('spatial');

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals(['CREATE TABLE spatial_table (point LONGTEXT NOT NULL, SPATIAL INDEX spatial_text (point)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = MyISAM'], $sql);
    }

    public function testClobTypeDeclarationSQL()
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

    public function testBlobTypeDeclarationSQL()
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

    /**
     * @group DBAL-400
     */
    public function testAlterTableAddPrimaryKey()
    {
        $table = new Table('alter_table_add_pk');
        $table->addColumn('id', 'integer');
        $table->addColumn('foo', 'integer');
        $table->addIndex(['id'], 'idx_id');

        $comparator = new Comparator();
        $diffTable  = clone $table;

        $diffTable->dropIndex('idx_id');
        $diffTable->setPrimaryKey(['id']);

        self::assertEquals(
            ['DROP INDEX idx_id ON alter_table_add_pk', 'ALTER TABLE alter_table_add_pk ADD PRIMARY KEY (id)'],
            $this->platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-1132
     */
    public function testAlterPrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table('alter_primary_key');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');
        $table->setPrimaryKey(['id']);

        $comparator = new Comparator();
        $diffTable  = clone $table;

        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(['foo']);

        self::assertEquals(
            [
                'ALTER TABLE alter_primary_key MODIFY id INT NOT NULL',
                'ALTER TABLE alter_primary_key DROP PRIMARY KEY',
                'ALTER TABLE alter_primary_key ADD PRIMARY KEY (foo)',
            ],
            $this->platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-464
     */
    public function testDropPrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table('drop_primary_key');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'integer');
        $table->setPrimaryKey(['id', 'foo']);

        $comparator = new Comparator();
        $diffTable  = clone $table;

        $diffTable->dropPrimaryKey();

        self::assertEquals(
            [
                'ALTER TABLE drop_primary_key MODIFY id INT NOT NULL',
                'ALTER TABLE drop_primary_key DROP PRIMARY KEY',
            ],
            $this->platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-2302
     */
    public function testDropNonAutoincrementColumnFromCompositePrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table('tbl');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'integer');
        $table->setPrimaryKey(['id', 'foo']);

        $comparator = new Comparator();
        $diffTable  = clone $table;

        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(['id']);

        self::assertSame(
            [
                'ALTER TABLE tbl MODIFY id INT NOT NULL',
                'ALTER TABLE tbl DROP PRIMARY KEY',
                'ALTER TABLE tbl ADD PRIMARY KEY (id)',
            ],
            $this->platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-2302
     */
    public function testAddNonAutoincrementColumnToPrimaryKeyWithAutoincrementColumn()
    {
        $table = new Table('tbl');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('foo', 'integer');
        $table->addColumn('bar', 'integer');
        $table->setPrimaryKey(['id']);

        $comparator = new Comparator();
        $diffTable  = clone $table;

        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(['id', 'foo']);

        self::assertSame(
            [
                'ALTER TABLE tbl MODIFY id INT NOT NULL',
                'ALTER TABLE tbl DROP PRIMARY KEY',
                'ALTER TABLE tbl ADD PRIMARY KEY (id, foo)',
            ],
            $this->platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    /**
     * @group DBAL-586
     */
    public function testAddAutoIncrementPrimaryKey()
    {
        $keyTable = new Table('foo');
        $keyTable->addColumn('id', 'integer', ['autoincrement' => true]);
        $keyTable->addColumn('baz', 'string');
        $keyTable->setPrimaryKey(['id']);

        $oldTable = new Table('foo');
        $oldTable->addColumn('baz', 'string');

        $c    = new Comparator();
        $diff = $c->diffTable($oldTable, $keyTable);

        $sql = $this->platform->getAlterTableSQL($diff);

        self::assertEquals(['ALTER TABLE foo ADD id INT AUTO_INCREMENT NOT NULL, ADD PRIMARY KEY (id)'], $sql);
    }

    public function testNamedPrimaryKey()
    {
        $diff                              = new TableDiff('mytable');
        $diff->changedIndexes['foo_index'] = new Index('foo_index', ['foo'], true, true);

        $sql = $this->platform->getAlterTableSQL($diff);

        self::assertEquals([
            'ALTER TABLE mytable DROP PRIMARY KEY',
            'ALTER TABLE mytable ADD PRIMARY KEY (foo)',
        ], $sql);
    }

    public function testAlterPrimaryKeyWithNewColumn()
    {
        $table = new Table('yolo');
        $table->addColumn('pkc1', 'integer');
        $table->addColumn('col_a', 'integer');
        $table->setPrimaryKey(['pkc1']);

        $comparator = new Comparator();
        $diffTable  = clone $table;

        $diffTable->addColumn('pkc2', 'integer');
        $diffTable->dropPrimaryKey();
        $diffTable->setPrimaryKey(['pkc1', 'pkc2']);

        self::assertSame(
            [
                'ALTER TABLE yolo DROP PRIMARY KEY',
                'ALTER TABLE yolo ADD pkc2 INT NOT NULL',
                'ALTER TABLE yolo ADD PRIMARY KEY (pkc1, pkc2)',
            ],
            $this->platform->getAlterTableSQL($comparator->diffTable($table, $diffTable))
        );
    }

    public function testInitializesDoctrineTypeMappings()
    {
        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('binary'));
        self::assertSame('binary', $this->platform->getDoctrineTypeMapping('binary'));

        self::assertTrue($this->platform->hasDoctrineTypeMappingFor('varbinary'));
        self::assertSame('binary', $this->platform->getDoctrineTypeMapping('varbinary'));
    }

    protected function getBinaryMaxLength()
    {
        return 65535;
    }

    public function testReturnsBinaryTypeDeclarationSQL()
    {
        self::assertSame('VARBINARY(255)', $this->platform->getBinaryTypeDeclarationSQL([]));
        self::assertSame('VARBINARY(255)', $this->platform->getBinaryTypeDeclarationSQL(['length' => 0]));
        self::assertSame('VARBINARY(65535)', $this->platform->getBinaryTypeDeclarationSQL(['length' => 65535]));

        self::assertSame('BINARY(255)', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true]));
        self::assertSame('BINARY(255)', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 0]));
        self::assertSame('BINARY(65535)', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 65535]));
    }

    /**
     * @group legacy
     * @expectedDeprecation Binary field length 65536 is greater than supported by the platform (65535). Reduce the field length or use a BLOB field instead.
     * @expectedDeprecation Binary field length 16777215 is greater than supported by the platform (65535). Reduce the field length or use a BLOB field instead.
     * @expectedDeprecation Binary field length 16777216 is greater than supported by the platform (65535). Reduce the field length or use a BLOB field instead.
     */
    public function testReturnsBinaryTypeLongerThanMaxDeclarationSQL()
    {
        self::assertSame('MEDIUMBLOB', $this->platform->getBinaryTypeDeclarationSQL(['length' => 65536]));
        self::assertSame('MEDIUMBLOB', $this->platform->getBinaryTypeDeclarationSQL(['length' => 16777215]));
        self::assertSame('LONGBLOB', $this->platform->getBinaryTypeDeclarationSQL(['length' => 16777216]));

        self::assertSame('MEDIUMBLOB', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 65536]));
        self::assertSame('MEDIUMBLOB', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 16777215]));
        self::assertSame('LONGBLOB', $this->platform->getBinaryTypeDeclarationSQL(['fixed' => true, 'length' => 16777216]));
    }

    public function testDoesNotPropagateForeignKeyCreationForNonSupportingEngines()
    {
        $table = new Table('foreign_table');
        $table->addColumn('id', 'integer');
        $table->addColumn('fk_id', 'integer');
        $table->addForeignKeyConstraint('foreign_table', ['fk_id'], ['id']);
        $table->setPrimaryKey(['id']);
        $table->addOption('engine', 'MyISAM');

        self::assertSame(
            ['CREATE TABLE foreign_table (id INT NOT NULL, fk_id INT NOT NULL, INDEX IDX_5690FFE2A57719D0 (fk_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = MyISAM'],
            $this->platform->getCreateTableSQL(
                $table,
                AbstractPlatform::CREATE_INDEXES|AbstractPlatform::CREATE_FOREIGNKEYS
            )
        );

        $table = clone $table;
        $table->addOption('engine', 'InnoDB');

        self::assertSame(
            [
                'CREATE TABLE foreign_table (id INT NOT NULL, fk_id INT NOT NULL, INDEX IDX_5690FFE2A57719D0 (fk_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB',
                'ALTER TABLE foreign_table ADD CONSTRAINT FK_5690FFE2A57719D0 FOREIGN KEY (fk_id) REFERENCES foreign_table (id)',
            ],
            $this->platform->getCreateTableSQL(
                $table,
                AbstractPlatform::CREATE_INDEXES|AbstractPlatform::CREATE_FOREIGNKEYS
            )
        );
    }

    public function testDoesNotPropagateForeignKeyAlterationForNonSupportingEngines()
    {
        $table = new Table('foreign_table');
        $table->addColumn('id', 'integer');
        $table->addColumn('fk_id', 'integer');
        $table->addForeignKeyConstraint('foreign_table', ['fk_id'], ['id']);
        $table->setPrimaryKey(['id']);
        $table->addOption('engine', 'MyISAM');

        $addedForeignKeys   = [new ForeignKeyConstraint(['fk_id'], 'foo', ['id'], 'fk_add')];
        $changedForeignKeys = [new ForeignKeyConstraint(['fk_id'], 'bar', ['id'], 'fk_change')];
        $removedForeignKeys = [new ForeignKeyConstraint(['fk_id'], 'baz', ['id'], 'fk_remove')];

        $tableDiff                     = new TableDiff('foreign_table');
        $tableDiff->fromTable          = $table;
        $tableDiff->addedForeignKeys   = $addedForeignKeys;
        $tableDiff->changedForeignKeys = $changedForeignKeys;
        $tableDiff->removedForeignKeys = $removedForeignKeys;

        self::assertEmpty($this->platform->getAlterTableSQL($tableDiff));

        $table->addOption('engine', 'InnoDB');

        $tableDiff                     = new TableDiff('foreign_table');
        $tableDiff->fromTable          = $table;
        $tableDiff->addedForeignKeys   = $addedForeignKeys;
        $tableDiff->changedForeignKeys = $changedForeignKeys;
        $tableDiff->removedForeignKeys = $removedForeignKeys;

        self::assertSame(
            [
                'ALTER TABLE foreign_table DROP FOREIGN KEY fk_remove',
                'ALTER TABLE foreign_table DROP FOREIGN KEY fk_change',
                'ALTER TABLE foreign_table ADD CONSTRAINT fk_add FOREIGN KEY (fk_id) REFERENCES foo (id)',
                'ALTER TABLE foreign_table ADD CONSTRAINT fk_change FOREIGN KEY (fk_id) REFERENCES bar (id)',
            ],
            $this->platform->getAlterTableSQL($tableDiff)
        );
    }

    /**
     * @group DBAL-234
     */
    protected function getAlterTableRenameIndexSQL()
    {
        return [
            'DROP INDEX idx_foo ON mytable',
            'CREATE INDEX idx_bar ON mytable (id)',
        ];
    }

    /**
     * @group DBAL-234
     */
    protected function getQuotedAlterTableRenameIndexSQL()
    {
        return [
            'DROP INDEX `create` ON `table`',
            'CREATE INDEX `select` ON `table` (id)',
            'DROP INDEX `foo` ON `table`',
            'CREATE INDEX `bar` ON `table` (id)',
        ];
    }

    /**
     * @group DBAL-807
     */
    protected function getAlterTableRenameIndexInSchemaSQL()
    {
        return [
            'DROP INDEX idx_foo ON myschema.mytable',
            'CREATE INDEX idx_bar ON myschema.mytable (id)',
        ];
    }

    /**
     * @group DBAL-807
     */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL()
    {
        return [
            'DROP INDEX `create` ON `schema`.`table`',
            'CREATE INDEX `select` ON `schema`.`table` (id)',
            'DROP INDEX `foo` ON `schema`.`table`',
            'CREATE INDEX `bar` ON `schema`.`table` (id)',
        ];
    }

    protected function getQuotesDropForeignKeySQL()
    {
        return 'ALTER TABLE `table` DROP FOREIGN KEY `select`';
    }

    protected function getQuotesDropConstraintSQL()
    {
        return 'ALTER TABLE `table` DROP CONSTRAINT `select`';
    }

    public function testDoesNotPropagateDefaultValuesForUnsupportedColumnTypes()
    {
        $table = new Table('text_blob_default_value');
        $table->addColumn('def_text', 'text', ['default' => 'def']);
        $table->addColumn('def_text_null', 'text', ['notnull' => false, 'default' => 'def']);
        $table->addColumn('def_blob', 'blob', ['default' => 'def']);
        $table->addColumn('def_blob_null', 'blob', ['notnull' => false, 'default' => 'def']);

        self::assertSame(
            ['CREATE TABLE text_blob_default_value (def_text LONGTEXT NOT NULL, def_text_null LONGTEXT DEFAULT NULL, def_blob LONGBLOB NOT NULL, def_blob_null LONGBLOB DEFAULT NULL) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'],
            $this->platform->getCreateTableSQL($table)
        );

        $diffTable = clone $table;
        $diffTable->changeColumn('def_text', ['default' => null]);
        $diffTable->changeColumn('def_text_null', ['default' => null]);
        $diffTable->changeColumn('def_blob', ['default' => null]);
        $diffTable->changeColumn('def_blob_null', ['default' => null]);

        $comparator = new Comparator();

        self::assertEmpty($this->platform->getAlterTableSQL($comparator->diffTable($table, $diffTable)));
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableRenameColumnSQL()
    {
        return ['ALTER TABLE mytable ' .
            "CHANGE unquoted1 unquoted INT NOT NULL COMMENT 'Unquoted 1', " .
            "CHANGE unquoted2 `where` INT NOT NULL COMMENT 'Unquoted 2', " .
            "CHANGE unquoted3 `foo` INT NOT NULL COMMENT 'Unquoted 3', " .
            "CHANGE `create` reserved_keyword INT NOT NULL COMMENT 'Reserved keyword 1', " .
            "CHANGE `table` `from` INT NOT NULL COMMENT 'Reserved keyword 2', " .
            "CHANGE `select` `bar` INT NOT NULL COMMENT 'Reserved keyword 3', " .
            "CHANGE quoted1 quoted INT NOT NULL COMMENT 'Quoted 1', " .
            "CHANGE quoted2 `and` INT NOT NULL COMMENT 'Quoted 2', " .
            "CHANGE quoted3 `baz` INT NOT NULL COMMENT 'Quoted 3'",
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotedAlterTableChangeColumnLengthSQL()
    {
        return ['ALTER TABLE mytable ' .
            "CHANGE unquoted1 unquoted1 VARCHAR(255) NOT NULL COMMENT 'Unquoted 1', " .
            "CHANGE unquoted2 unquoted2 VARCHAR(255) NOT NULL COMMENT 'Unquoted 2', " .
            "CHANGE unquoted3 unquoted3 VARCHAR(255) NOT NULL COMMENT 'Unquoted 3', " .
            "CHANGE `create` `create` VARCHAR(255) NOT NULL COMMENT 'Reserved keyword 1', " .
            "CHANGE `table` `table` VARCHAR(255) NOT NULL COMMENT 'Reserved keyword 2', " .
            "CHANGE `select` `select` VARCHAR(255) NOT NULL COMMENT 'Reserved keyword 3'",
        ];
    }

    /**
     * @group DBAL-423
     */
    public function testReturnsGuidTypeDeclarationSQL()
    {
        self::assertSame('CHAR(36)', $this->platform->getGuidTypeDeclarationSQL([]));
    }

    /**
     * {@inheritdoc}
     */
    public function getAlterTableRenameColumnSQL()
    {
        return ["ALTER TABLE foo CHANGE bar baz INT DEFAULT 666 NOT NULL COMMENT 'rename test'"];
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesTableIdentifiersInAlterTableSQL()
    {
        return [
            'ALTER TABLE `foo` DROP FOREIGN KEY fk1',
            'ALTER TABLE `foo` DROP FOREIGN KEY fk2',
            'ALTER TABLE `foo` RENAME TO `table`, ADD bloo INT NOT NULL, DROP baz, CHANGE bar bar INT DEFAULT NULL, ' .
            'CHANGE id war INT NOT NULL',
            'ALTER TABLE `table` ADD CONSTRAINT fk_add FOREIGN KEY (fk3) REFERENCES fk_table (id)',
            'ALTER TABLE `table` ADD CONSTRAINT fk2 FOREIGN KEY (fk2) REFERENCES fk_table2 (id)',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommentOnColumnSQL()
    {
        return [
            "COMMENT ON COLUMN foo.bar IS 'comment'",
            "COMMENT ON COLUMN `Foo`.`BAR` IS 'comment'",
            "COMMENT ON COLUMN `select`.`from` IS 'comment'",
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL()
    {
        return 'CONSTRAINT `select` UNIQUE (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInIndexDeclarationSQL()
    {
        return 'INDEX `select` (foo)';
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuotesReservedKeywordInTruncateTableSQL()
    {
        return 'TRUNCATE `select`';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAlterStringToFixedStringSQL()
    {
        return ['ALTER TABLE mytable CHANGE name name CHAR(2) NOT NULL'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL()
    {
        return [
            'ALTER TABLE mytable DROP FOREIGN KEY fk_foo',
            'DROP INDEX idx_foo ON mytable',
            'CREATE INDEX idx_foo_renamed ON mytable (foo)',
            'ALTER TABLE mytable ADD CONSTRAINT fk_foo FOREIGN KEY (foo) REFERENCES foreign_table (id)',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getGeneratesDecimalTypeDeclarationSQL()
    {
        return [
            [[], 'NUMERIC(10, 0)'],
            [['unsigned' => true], 'NUMERIC(10, 0) UNSIGNED'],
            [['unsigned' => false], 'NUMERIC(10, 0)'],
            [['precision' => 5], 'NUMERIC(5, 0)'],
            [['scale' => 5], 'NUMERIC(10, 5)'],
            [['precision' => 8, 'scale' => 2], 'NUMERIC(8, 2)'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getGeneratesFloatDeclarationSQL()
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

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableIndexesSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->platform->getListTableIndexesSQL("Foo'Bar\\", 'foo_db'), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListTableIndexesSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->platform->getListTableIndexesSQL('foo_table', "Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListViewsSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->platform->getListViewsSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableForeignKeysSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->platform->getListTableForeignKeysSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListTableForeignKeysSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->platform->getListTableForeignKeysSQL('foo_table', "Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesTableNameInListTableColumnsSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->platform->getListTableColumnsSQL("Foo'Bar\\"), '', true);
    }

    /**
     * @group DBAL-2436
     */
    public function testQuotesDatabaseNameInListTableColumnsSQL()
    {
        self::assertContains("'Foo''Bar\\\\'", $this->platform->getListTableColumnsSQL('foo_table', "Foo'Bar\\"), '', true);
    }

    public function testListTableForeignKeysSQLEvaluatesDatabase()
    {
        $sql = $this->platform->getListTableForeignKeysSQL('foo');

        self::assertContains('DATABASE()', $sql);

        $sql = $this->platform->getListTableForeignKeysSQL('foo', 'bar');

        self::assertContains('bar', $sql);
        self::assertNotContains('DATABASE()', $sql);
    }

    public function testSupportsColumnCollation() : void
    {
        self::assertTrue($this->platform->supportsColumnCollation());
    }

    public function testColumnCollationDeclarationSQL() : void
    {
        self::assertSame(
            'COLLATE ascii_general_ci',
            $this->platform->getColumnCollationDeclarationSQL('ascii_general_ci')
        );
    }

    public function testGetCreateTableSQLWithColumnCollation() : void
    {
        $table = new Table('foo');
        $table->addColumn('no_collation', 'string');
        $table->addColumn('column_collation', 'string')->setPlatformOption('collation', 'ascii_general_ci');

        self::assertSame(
            ['CREATE TABLE foo (no_collation VARCHAR(255) NOT NULL, column_collation VARCHAR(255) NOT NULL COLLATE ascii_general_ci) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'],
            $this->platform->getCreateTableSQL($table),
            'Column "no_collation" will use the default collation from the table/database and "column_collation" overwrites the collation on this column'
        );
    }
}
