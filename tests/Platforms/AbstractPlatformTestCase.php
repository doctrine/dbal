<?php

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\InvalidLockMode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function get_class;
use function implode;
use function sprintf;
use function str_repeat;

/** @template T of AbstractPlatform */
abstract class AbstractPlatformTestCase extends TestCase
{
    /** @var T */
    protected AbstractPlatform $platform;

    private ?Type $backedUpType = null;

    /** @return T */
    abstract public function createPlatform(): AbstractPlatform;

    protected function setUp(): void
    {
        $this->platform = $this->createPlatform();
    }

    public function testQuoteIdentifier(): void
    {
        if ($this->platform instanceof SQLServerPlatform) {
            self::markTestSkipped('Not working this way on mssql.');
        }

        $c = $this->platform->getIdentifierQuoteCharacter();
        self::assertEquals($c . 'test' . $c, $this->platform->quoteIdentifier('test'));
        self::assertEquals($c . 'test' . $c . '.' . $c . 'test' . $c, $this->platform->quoteIdentifier('test.test'));
        self::assertEquals(str_repeat($c, 4), $this->platform->quoteIdentifier($c));
    }

    public function testQuoteSingleIdentifier(): void
    {
        if ($this->platform instanceof SQLServerPlatform) {
            self::markTestSkipped('Not working this way on mssql.');
        }

        $c = $this->platform->getIdentifierQuoteCharacter();
        self::assertEquals($c . 'test' . $c, $this->platform->quoteSingleIdentifier('test'));
        self::assertEquals($c . 'test.test' . $c, $this->platform->quoteSingleIdentifier('test.test'));
        self::assertEquals(str_repeat($c, 4), $this->platform->quoteSingleIdentifier($c));
    }

    /** @dataProvider getReturnsForeignKeyReferentialActionSQL */
    public function testReturnsForeignKeyReferentialActionSQL(string $action, string $expectedSQL): void
    {
        self::assertSame($expectedSQL, $this->platform->getForeignKeyReferentialActionSQL($action));
    }

    /** @return mixed[][] */
    public static function getReturnsForeignKeyReferentialActionSQL(): iterable
    {
        return [
            ['CASCADE', 'CASCADE'],
            ['SET NULL', 'SET NULL'],
            ['NO ACTION', 'NO ACTION'],
            ['RESTRICT', 'RESTRICT'],
            ['SET DEFAULT', 'SET DEFAULT'],
            ['CaScAdE', 'CASCADE'],
        ];
    }

    public function testGetInvalidForeignKeyReferentialActionSQL(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->platform->getForeignKeyReferentialActionSQL('unknown');
    }

    public function testGetUnknownDoctrineMappingType(): void
    {
        $this->expectException(Exception::class);
        $this->platform->getDoctrineTypeMapping('foobar');
    }

    public function testRegisterDoctrineMappingType(): void
    {
        $this->platform->registerDoctrineTypeMapping('foo', Types::INTEGER);
        self::assertEquals(Types::INTEGER, $this->platform->getDoctrineTypeMapping('foo'));
    }

    public function testCaseInsensitiveDoctrineTypeMappingFromType(): void
    {
        $type = new class () extends Type {
            /**
             * {@inheritDoc}
             */
            public function getMappedDatabaseTypes(AbstractPlatform $platform): array
            {
                return ['TESTTYPE'];
            }

            public function getName(): string
            {
                return 'testtype';
            }

            /**
             * {@inheritDoc}
             */
            public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
            {
                return $platform->getDecimalTypeDeclarationSQL($column);
            }
        };

        if (Type::hasType($type->getName())) {
            Type::overrideType($type->getName(), get_class($type));
        } else {
            Type::addType($type->getName(), get_class($type));
        }

        self::assertSame($type->getName(), $this->platform->getDoctrineTypeMapping('TeStTyPe'));
    }

    public function testRegisterUnknownDoctrineMappingType(): void
    {
        $this->expectException(Exception::class);
        $this->platform->registerDoctrineTypeMapping('foo', 'bar');
    }

    /** @psalm-suppress DeprecatedConstant */
    public function testRegistersCommentedDoctrineMappingTypeImplicitly(): void
    {
        $type = Type::getType(Types::ARRAY);
        $this->platform->registerDoctrineTypeMapping('foo', Types::ARRAY);

        self::assertTrue($this->platform->isCommentedDoctrineType($type));
    }

    /** @dataProvider getIsCommentedDoctrineType */
    public function testIsCommentedDoctrineType(Type $type, bool $commented): void
    {
        self::assertSame($commented, $this->platform->isCommentedDoctrineType($type));
    }

    /** @return mixed[] */
    public function getIsCommentedDoctrineType(): iterable
    {
        $this->setUp();

        $data = [];

        foreach (Type::getTypesMap() as $typeName => $className) {
            $type = Type::getType($typeName);

            $data[$typeName] = [
                $type,
                $type->requiresSQLCommentHint($this->platform),
            ];
        }

        return $data;
    }

    public function testCreateWithNoColumns(): void
    {
        $table = new Table('test');

        $this->expectException(Exception::class);
        $this->platform->getCreateTableSQL($table);
    }

    public function testGeneratesTableCreationSql(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER, ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('test', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->setPrimaryKey(['id']);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getGenerateTableSql(), $sql[0]);
    }

    abstract public function getGenerateTableSql(): string;

    public function testGenerateTableWithMultiColumnUniqueIndex(): void
    {
        $table = new Table('test');
        $table->addColumn('foo', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('bar', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addUniqueIndex(['foo', 'bar']);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getGenerateTableWithMultiColumnUniqueIndexSql(), $sql);
    }

    /** @return string[] */
    abstract public function getGenerateTableWithMultiColumnUniqueIndexSql(): array;

    public function testGeneratesIndexCreationSql(): void
    {
        $indexDef = new Index('my_idx', ['user_name', 'last_login']);

        self::assertEquals(
            $this->getGenerateIndexSql(),
            $this->platform->getCreateIndexSQL($indexDef, 'mytable'),
        );
    }

    abstract public function getGenerateIndexSql(): string;

    public function testGeneratesUniqueIndexCreationSql(): void
    {
        $indexDef = new Index('index_name', ['test', 'test2'], true);

        $sql = $this->platform->getCreateIndexSQL($indexDef, 'test');
        self::assertEquals($this->getGenerateUniqueIndexSql(), $sql);
    }

    abstract public function getGenerateUniqueIndexSql(): string;

    public function testGeneratesPartialIndexesSqlOnlyWhenSupportingPartialIndexes(): void
    {
        $where            = 'test IS NULL AND test2 IS NOT NULL';
        $indexDef         = new Index('name', ['test', 'test2'], false, false, [], ['where' => $where]);
        $uniqueConstraint = new UniqueConstraint('name', ['test', 'test2'], [], []);

        $expected = ' WHERE ' . $where;

        $indexes = [];

        if ($this->supportsInlineIndexDeclaration()) {
            $indexes[] = $this->platform->getIndexDeclarationSQL('name', $indexDef);
        }

        $uniqueConstraintSQL = $this->platform->getUniqueConstraintDeclarationSQL('name', $uniqueConstraint);
        $this->assertStringEndsNotWith($expected, $uniqueConstraintSQL, 'WHERE clause should NOT be present');

        $indexes[] = $this->platform->getCreateIndexSQL($indexDef, 'table');

        foreach ($indexes as $index) {
            if ($this->platform->supportsPartialIndexes()) {
                self::assertStringEndsWith($expected, $index, 'WHERE clause should be present');
            } else {
                self::assertStringEndsNotWith($expected, $index, 'WHERE clause should NOT be present');
            }
        }
    }

    public function testGeneratesForeignKeyCreationSql(): void
    {
        $fk = new ForeignKeyConstraint(['fk_name_id'], 'other_table', ['id'], '');

        $sql = $this->platform->getCreateForeignKeySQL($fk, 'test');
        self::assertEquals($sql, $this->getGenerateForeignKeySql());
    }

    abstract protected function getGenerateForeignKeySql(): string;

    public function testGeneratesConstraintCreationSql(): void
    {
        $idx = new Index('constraint_name', ['test'], true, false);
        $sql = $this->platform->getCreateConstraintSQL($idx, 'test');
        self::assertEquals($this->getGenerateConstraintUniqueIndexSql(), $sql);

        $pk  = new Index('constraint_name', ['test'], true, true);
        $sql = $this->platform->getCreateConstraintSQL($pk, 'test');
        self::assertEquals($this->getGenerateConstraintPrimaryIndexSql(), $sql);

        $uc  = new UniqueConstraint('constraint_name', ['test']);
        $sql = $this->platform->getCreateConstraintSQL($uc, 'test');
        self::assertEquals($this->getGenerateConstraintUniqueIndexSql(), $sql);

        $fk  = new ForeignKeyConstraint(['fk_name'], 'foreign', ['id'], 'constraint_fk');
        $sql = $this->platform->getCreateConstraintSQL($fk, 'test');
        self::assertEquals($this->getGenerateConstraintForeignKeySql($fk), $sql);
    }

    protected function getBitAndComparisonExpressionSql(string $value1, string $value2): string
    {
        return '(' . $value1 . ' & ' . $value2 . ')';
    }

    public function testGeneratesBitAndComparisonExpressionSql(): void
    {
        $sql = $this->platform->getBitAndComparisonExpression(2, 4);
        self::assertEquals($this->getBitAndComparisonExpressionSql(2, 4), $sql);
    }

    protected function getBitOrComparisonExpressionSql(string $value1, string $value2): string
    {
        return '(' . $value1 . ' | ' . $value2 . ')';
    }

    public function testGeneratesBitOrComparisonExpressionSql(): void
    {
        $sql = $this->platform->getBitOrComparisonExpression(2, 4);
        self::assertEquals($this->getBitOrComparisonExpressionSql(2, 4), $sql);
    }

    public function getGenerateConstraintUniqueIndexSql(): string
    {
        return 'ALTER TABLE test ADD CONSTRAINT constraint_name UNIQUE (test)';
    }

    public function getGenerateConstraintPrimaryIndexSql(): string
    {
        return 'ALTER TABLE test ADD CONSTRAINT constraint_name PRIMARY KEY (test)';
    }

    public function getGenerateConstraintForeignKeySql(ForeignKeyConstraint $fk): string
    {
        $quotedForeignTable = $fk->getQuotedForeignTableName($this->platform);

        return sprintf(
            'ALTER TABLE test ADD CONSTRAINT constraint_fk FOREIGN KEY (fk_name) REFERENCES %s (id)',
            $quotedForeignTable,
        );
    }

    public function testGetCustomColumnDeclarationSql(): void
    {
        self::assertEquals(
            'foo MEDIUMINT(6) UNSIGNED',
            $this->platform->getColumnDeclarationSQL('foo', ['columnDefinition' => 'MEDIUMINT(6) UNSIGNED']),
        );
    }

    public function testGetCreateTableSqlDispatchEvent(): void
    {
        $listenerMock = $this->createMock(GetCreateTableSqlDispatchEventListener::class);
        $listenerMock
            ->expects(self::once())
            ->method('onSchemaCreateTable');
        $listenerMock
            ->expects(self::exactly(2))
            ->method('onSchemaCreateTableColumn');

        $eventManager = new EventManager();
        $eventManager->addEventListener([
            Events::onSchemaCreateTable,
            Events::onSchemaCreateTableColumn,
        ], $listenerMock);

        $this->platform->setEventManager($eventManager);

        $table = new Table('test');
        $table->addColumn('foo', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('bar', Types::STRING, ['notnull' => false, 'length' => 255]);

        $this->platform->getCreateTableSQL($table);
    }

    public function testGetDropTableSqlDispatchEvent(): void
    {
        $listenerMock = $this->createMock(GetDropTableSqlDispatchEventListener::class);
        $listenerMock
            ->expects(self::once())
            ->method('onSchemaDropTable');

        $eventManager = new EventManager();
        $eventManager->addEventListener([Events::onSchemaDropTable], $listenerMock);

        $this->platform->setEventManager($eventManager);

        $this->platform->getDropTableSQL('TABLE');
    }

    public function testGetAlterTableSqlDispatchEvent(): void
    {
        $listenerMock = $this->createMock(GetAlterTableSqlDispatchEventListener::class);
        $listenerMock
            ->expects(self::once())
            ->method('onSchemaAlterTable');
        $listenerMock
            ->expects(self::once())
            ->method('onSchemaAlterTableAddColumn');
        $listenerMock
            ->expects(self::once())
            ->method('onSchemaAlterTableRemoveColumn');
        $listenerMock
            ->expects(self::once())
            ->method('onSchemaAlterTableChangeColumn');
        $listenerMock
            ->expects(self::once())
            ->method('onSchemaAlterTableRenameColumn');

        $eventManager = new EventManager();
        $events       = [
            Events::onSchemaAlterTable,
            Events::onSchemaAlterTableAddColumn,
            Events::onSchemaAlterTableRemoveColumn,
            Events::onSchemaAlterTableChangeColumn,
            Events::onSchemaAlterTableRenameColumn,
        ];
        $eventManager->addEventListener($events, $listenerMock);

        $this->platform->setEventManager($eventManager);

        $table = new Table('mytable');
        $table->addColumn('removed', Types::INTEGER);
        $table->addColumn('changed', Types::INTEGER);
        $table->addColumn('renamed', Types::INTEGER);

        $tableDiff                            = new TableDiff('mytable');
        $tableDiff->fromTable                 = $table;
        $tableDiff->addedColumns['added']     = new Column('added', Type::getType(Types::INTEGER), []);
        $tableDiff->removedColumns['removed'] = new Column('removed', Type::getType(Types::INTEGER), []);
        $tableDiff->changedColumns['changed'] = new ColumnDiff(
            'changed',
            new Column(
                'changed2',
                Type::getType(Types::STRING),
                [],
            ),
            [],
        );
        $tableDiff->renamedColumns['renamed'] = new Column('renamed2', Type::getType(Types::INTEGER), []);

        $this->platform->getAlterTableSQL($tableDiff);
    }

    public function testCreateTableColumnComments(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER, ['comment' => 'This is a comment']);
        $table->setPrimaryKey(['id']);

        self::assertEquals($this->getCreateTableColumnCommentsSQL(), $this->platform->getCreateTableSQL($table));
    }

    public function testAlterTableColumnComments(): void
    {
        $tableDiff                        = new TableDiff('mytable');
        $tableDiff->addedColumns['quota'] = new Column(
            'quota',
            Type::getType(Types::INTEGER),
            ['comment' => 'A comment'],
        );
        $tableDiff->changedColumns['foo'] = new ColumnDiff(
            'foo',
            new Column(
                'foo',
                Type::getType(Types::STRING),
            ),
            ['comment'],
        );
        $tableDiff->changedColumns['bar'] = new ColumnDiff(
            'bar',
            new Column(
                'baz',
                Type::getType(Types::STRING),
                ['comment' => 'B comment'],
            ),
            ['comment'],
        );

        self::assertEquals($this->getAlterTableColumnCommentsSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    public function testCreateTableColumnTypeComments(): void
    {
        $table = new Table('test');
        $table->addColumn('id', Types::INTEGER);
        /** @psalm-suppress DeprecatedConstant */
        $table->addColumn('data', Types::ARRAY);
        $table->setPrimaryKey(['id']);

        self::assertEquals($this->getCreateTableColumnTypeCommentsSQL(), $this->platform->getCreateTableSQL($table));
    }

    /** @return string[] */
    public function getCreateTableColumnCommentsSQL(): array
    {
        self::markTestSkipped('Platform does not support Column comments.');
    }

    /** @return string[] */
    public function getAlterTableColumnCommentsSQL(): array
    {
        self::markTestSkipped('Platform does not support Column comments.');
    }

    /** @return string[] */
    public function getCreateTableColumnTypeCommentsSQL(): array
    {
        self::markTestSkipped('Platform does not support Column comments.');
    }

    public function testGetDefaultValueDeclarationSQL(): void
    {
        // non-timestamp value will get single quotes
        self::assertEquals(" DEFAULT 'non_timestamp'", $this->platform->getDefaultValueDeclarationSQL([
            'type' => Type::getType(Types::STRING),
            'default' => 'non_timestamp',
        ]));
    }

    public function testGetDefaultValueDeclarationSQLDateTime(): void
    {
        $types = [
            Types::DATETIME_MUTABLE,
            Types::DATETIMETZ_MUTABLE,
            Types::DATETIME_IMMUTABLE,
            Types::DATETIMETZ_IMMUTABLE,
        ];
        // timestamps on datetime types should not be quoted
        foreach ($types as $type) {
            self::assertSame(
                ' DEFAULT ' . $this->platform->getCurrentTimestampSQL(),
                $this->platform->getDefaultValueDeclarationSQL([
                    'type'    => Type::getType($type),
                    'default' => $this->platform->getCurrentTimestampSQL(),
                ]),
            );
        }
    }

    public function testGetDefaultValueDeclarationSQLForIntegerTypes(): void
    {
        foreach ([Types::BIGINT, Types::INTEGER, Types::SMALLINT] as $type) {
            self::assertEquals(
                ' DEFAULT 1',
                $this->platform->getDefaultValueDeclarationSQL([
                    'type'    => Type::getType($type),
                    'default' => 1,
                ]),
            );
        }
    }

    public function testGetDefaultValueDeclarationSQLForDateType(): void
    {
        $currentDateSql = $this->platform->getCurrentDateSQL();
        foreach ([Types::DATE_MUTABLE, Types::DATE_IMMUTABLE] as $type) {
            self::assertSame(
                ' DEFAULT ' . $currentDateSql,
                $this->platform->getDefaultValueDeclarationSQL([
                    'type'    => Type::getType($type),
                    'default' => $currentDateSql,
                ]),
            );
        }
    }

    public function testKeywordList(): void
    {
        $keywordList = $this->platform->getReservedKeywordsList();
        self::assertInstanceOf(KeywordList::class, $keywordList);

        self::assertTrue($keywordList->isKeyword('table'));
    }

    public function testQuotedColumnInPrimaryKeyPropagation(): void
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', Types::STRING);
        $table->setPrimaryKey(['create']);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedColumnInPrimaryKeySQL(), $sql);
    }

    /** @return string[] */
    abstract protected function getQuotedColumnInPrimaryKeySQL(): array;

    /** @return string[] */
    abstract protected function getQuotedColumnInIndexSQL(): array;

    /** @return string[] */
    abstract protected function getQuotedNameInIndexSQL(): array;

    /** @return string[] */
    abstract protected function getQuotedColumnInForeignKeySQL(): array;

    public function testQuotedColumnInIndexPropagation(): void
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', Types::STRING);
        $table->addIndex(['create']);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedColumnInIndexSQL(), $sql);
    }

    public function testQuotedNameInIndexSQL(): void
    {
        $table = new Table('test');
        $table->addColumn('column1', Types::STRING);
        $table->addIndex(['column1'], '`key`');

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedNameInIndexSQL(), $sql);
    }

    public function testQuotedColumnInForeignKeyPropagation(): void
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', Types::STRING);
        $table->addColumn('foo', Types::STRING);
        $table->addColumn('`bar`', Types::STRING);

        // Foreign table with reserved keyword as name (needs quotation).
        $foreignTable = new Table('foreign');

        // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('create', Types::STRING);

        // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('bar', Types::STRING);

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable->addColumn('`foo-bar`', Types::STRING);

        $table->addForeignKeyConstraint(
            $foreignTable,
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_RESERVED_KEYWORD',
        );

        // Foreign table with non-reserved keyword as name (does not need quotation).
        $foreignTable = new Table('foo');

        // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('create', Types::STRING);

        // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('bar', Types::STRING);

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable->addColumn('`foo-bar`', Types::STRING);

        $table->addForeignKeyConstraint(
            $foreignTable,
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_NON_RESERVED_KEYWORD',
        );

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable = new Table('`foo-bar`');

        // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('create', Types::STRING);

        // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('bar', Types::STRING);

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable->addColumn('`foo-bar`', Types::STRING);

        $table->addForeignKeyConstraint(
            $foreignTable,
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_INTENDED_QUOTATION',
        );

        $sql = $this->platform->getCreateTableSQL(
            $table,
            AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS,
        );
        self::assertEquals($this->getQuotedColumnInForeignKeySQL(), $sql);
    }

    public function testQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): void
    {
        $constraint = new UniqueConstraint('select', ['foo'], [], []);

        self::assertSame(
            $this->getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(),
            $this->platform->getUniqueConstraintDeclarationSQL('select', $constraint),
        );
    }

    abstract protected function getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): string;

    public function testQuotesReservedKeywordInTruncateTableSQL(): void
    {
        self::assertSame(
            $this->getQuotesReservedKeywordInTruncateTableSQL(),
            $this->platform->getTruncateTableSQL('select'),
        );
    }

    abstract protected function getQuotesReservedKeywordInTruncateTableSQL(): string;

    public function testQuotesReservedKeywordInIndexDeclarationSQL(): void
    {
        $index = new Index('select', ['foo']);

        if (! $this->supportsInlineIndexDeclaration()) {
            $this->expectException(Exception::class);
        }

        self::assertSame(
            $this->getQuotesReservedKeywordInIndexDeclarationSQL(),
            $this->platform->getIndexDeclarationSQL('select', $index),
        );
    }

    abstract protected function getQuotesReservedKeywordInIndexDeclarationSQL(): string;

    protected function supportsInlineIndexDeclaration(): bool
    {
        return true;
    }

    public function testSupportsCommentOnStatement(): void
    {
        self::assertSame($this->supportsCommentOnStatement(), $this->platform->supportsCommentOnStatement());
    }

    protected function supportsCommentOnStatement(): bool
    {
        return false;
    }

    public function testGetCreateSchemaSQL(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getCreateSchemaSQL('schema');
    }

    public function testAlterTableChangeQuotedColumn(): void
    {
        $table = new Table('mytable');
        $table->addColumn('select', Types::INTEGER);

        $tableDiff                           = new TableDiff('mytable');
        $tableDiff->fromTable                = $table;
        $tableDiff->changedColumns['select'] = new ColumnDiff(
            'select',
            new Column(
                'select',
                Type::getType(Types::STRING),
            ),
            ['type'],
        );

        self::assertStringContainsString(
            $this->platform->quoteIdentifier('select'),
            implode(';', $this->platform->getAlterTableSQL($tableDiff)),
        );
    }

    public function testUsesSequenceEmulatedIdentityColumns(): void
    {
        self::assertFalse($this->platform->usesSequenceEmulatedIdentityColumns());
    }

    /** @group DBAL-563 */
    public function testReturnsIdentitySequenceName(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getIdentitySequenceName('mytable', 'mycolumn');
    }

    public function testReturnsBinaryDefaultLength(): void
    {
        self::assertSame($this->getBinaryDefaultLength(), $this->platform->getBinaryDefaultLength());
    }

    protected function getBinaryDefaultLength(): int
    {
        return 255;
    }

    public function testReturnsBinaryMaxLength(): void
    {
        self::assertSame($this->getBinaryMaxLength(), $this->platform->getBinaryMaxLength());
    }

    protected function getBinaryMaxLength(): int
    {
        return 4000;
    }

    public function testReturnsBinaryTypeDeclarationSQL(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getBinaryTypeDeclarationSQL([]);
    }

    public function testReturnsBinaryTypeLongerThanMaxDeclarationSQL(): void
    {
        $this->markTestSkipped('Not applicable to the platform');
    }

    public function hasNativeJsonType(): void
    {
        self::assertFalse($this->platform->hasNativeJsonType());
    }

    public function testReturnsJsonTypeDeclarationSQL(): void
    {
        $column = [
            'length'  => 666,
            'notnull' => true,
            'type'    => Type::getType(Types::JSON),
        ];

        self::assertSame(
            $this->platform->getClobTypeDeclarationSQL($column),
            $this->platform->getJsonTypeDeclarationSQL($column),
        );
    }

    public function testAlterTableRenameIndex(): void
    {
        $tableDiff            = new TableDiff('mytable');
        $tableDiff->fromTable = new Table('mytable');
        $tableDiff->fromTable->addColumn('id', Types::INTEGER);
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'idx_foo' => new Index('idx_bar', ['id']),
        ];

        self::assertSame(
            $this->getAlterTableRenameIndexSQL(),
            $this->platform->getAlterTableSQL($tableDiff),
        );
    }

    /** @return string[] */
    protected function getAlterTableRenameIndexSQL(): array
    {
        return [
            'DROP INDEX idx_foo',
            'CREATE INDEX idx_bar ON mytable (id)',
        ];
    }

    public function testQuotesAlterTableRenameIndex(): void
    {
        $tableDiff            = new TableDiff('table');
        $tableDiff->fromTable = new Table('table');
        $tableDiff->fromTable->addColumn('id', Types::INTEGER);
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'create' => new Index('select', ['id']),
            '`foo`'  => new Index('`bar`', ['id']),
        ];

        self::assertSame(
            $this->getQuotedAlterTableRenameIndexSQL(),
            $this->platform->getAlterTableSQL($tableDiff),
        );
    }

    /** @return string[] */
    protected function getQuotedAlterTableRenameIndexSQL(): array
    {
        return [
            'DROP INDEX "create"',
            'CREATE INDEX "select" ON "table" (id)',
            'DROP INDEX "foo"',
            'CREATE INDEX "bar" ON "table" (id)',
        ];
    }

    public function testQuotesAlterTableRenameColumn(): void
    {
        $fromTable = new Table('mytable');

        $fromTable->addColumn('unquoted1', Types::INTEGER, ['comment' => 'Unquoted 1']);
        $fromTable->addColumn('unquoted2', Types::INTEGER, ['comment' => 'Unquoted 2']);
        $fromTable->addColumn('unquoted3', Types::INTEGER, ['comment' => 'Unquoted 3']);

        $fromTable->addColumn('create', Types::INTEGER, ['comment' => 'Reserved keyword 1']);
        $fromTable->addColumn('table', Types::INTEGER, ['comment' => 'Reserved keyword 2']);
        $fromTable->addColumn('select', Types::INTEGER, ['comment' => 'Reserved keyword 3']);

        $fromTable->addColumn('`quoted1`', Types::INTEGER, ['comment' => 'Quoted 1']);
        $fromTable->addColumn('`quoted2`', Types::INTEGER, ['comment' => 'Quoted 2']);
        $fromTable->addColumn('`quoted3`', Types::INTEGER, ['comment' => 'Quoted 3']);

        $toTable = new Table('mytable');

        // unquoted -> unquoted
        $toTable->addColumn('unquoted', Types::INTEGER, ['comment' => 'Unquoted 1']);

        // unquoted -> reserved keyword
        $toTable->addColumn('where', Types::INTEGER, ['comment' => 'Unquoted 2']);

        // unquoted -> quoted
        $toTable->addColumn('`foo`', Types::INTEGER, ['comment' => 'Unquoted 3']);

        // reserved keyword -> unquoted
        $toTable->addColumn('reserved_keyword', Types::INTEGER, ['comment' => 'Reserved keyword 1']);

        // reserved keyword -> reserved keyword
        $toTable->addColumn('from', Types::INTEGER, ['comment' => 'Reserved keyword 2']);

        // reserved keyword -> quoted
        $toTable->addColumn('`bar`', Types::INTEGER, ['comment' => 'Reserved keyword 3']);

        // quoted -> unquoted
        $toTable->addColumn('quoted', Types::INTEGER, ['comment' => 'Quoted 1']);

        // quoted -> reserved keyword
        $toTable->addColumn('and', Types::INTEGER, ['comment' => 'Quoted 2']);

        // quoted -> quoted
        $toTable->addColumn('`baz`', Types::INTEGER, ['comment' => 'Quoted 3']);

        $diff = (new Comparator())->diffTable($fromTable, $toTable);
        self::assertNotFalse($diff);

        self::assertEquals(
            $this->getQuotedAlterTableRenameColumnSQL(),
            $this->platform->getAlterTableSQL($diff),
        );
    }

    /**
     * Returns SQL statements for {@link testQuotesAlterTableRenameColumn}.
     *
     * @return string[]
     */
    abstract protected function getQuotedAlterTableRenameColumnSQL(): array;

    public function testQuotesAlterTableChangeColumnLength(): void
    {
        $fromTable = new Table('mytable');

        $fromTable->addColumn('unquoted1', Types::STRING, ['comment' => 'Unquoted 1', 'length' => 10]);
        $fromTable->addColumn('unquoted2', Types::STRING, ['comment' => 'Unquoted 2', 'length' => 10]);
        $fromTable->addColumn('unquoted3', Types::STRING, ['comment' => 'Unquoted 3', 'length' => 10]);

        $fromTable->addColumn('create', Types::STRING, ['comment' => 'Reserved keyword 1', 'length' => 10]);
        $fromTable->addColumn('table', Types::STRING, ['comment' => 'Reserved keyword 2', 'length' => 10]);
        $fromTable->addColumn('select', Types::STRING, ['comment' => 'Reserved keyword 3', 'length' => 10]);

        $toTable = new Table('mytable');

        $toTable->addColumn('unquoted1', Types::STRING, ['comment' => 'Unquoted 1', 'length' => 255]);
        $toTable->addColumn('unquoted2', Types::STRING, ['comment' => 'Unquoted 2', 'length' => 255]);
        $toTable->addColumn('unquoted3', Types::STRING, ['comment' => 'Unquoted 3', 'length' => 255]);

        $toTable->addColumn('create', Types::STRING, ['comment' => 'Reserved keyword 1', 'length' => 255]);
        $toTable->addColumn('table', Types::STRING, ['comment' => 'Reserved keyword 2', 'length' => 255]);
        $toTable->addColumn('select', Types::STRING, ['comment' => 'Reserved keyword 3', 'length' => 255]);

        $diff = (new Comparator())->diffTable($fromTable, $toTable);
        self::assertNotFalse($diff);

        self::assertEquals(
            $this->getQuotedAlterTableChangeColumnLengthSQL(),
            $this->platform->getAlterTableSQL($diff),
        );
    }

    /**
     * Returns SQL statements for {@link testQuotesAlterTableChangeColumnLength}.
     *
     * @return string[]
     */
    abstract protected function getQuotedAlterTableChangeColumnLengthSQL(): array;

    public function testAlterTableRenameIndexInSchema(): void
    {
        $tableDiff            = new TableDiff('myschema.mytable');
        $tableDiff->fromTable = new Table('myschema.mytable');
        $tableDiff->fromTable->addColumn('id', Types::INTEGER);
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'idx_foo' => new Index('idx_bar', ['id']),
        ];

        self::assertSame(
            $this->getAlterTableRenameIndexInSchemaSQL(),
            $this->platform->getAlterTableSQL($tableDiff),
        );
    }

    /** @return string[] */
    protected function getAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            'DROP INDEX idx_foo',
            'CREATE INDEX idx_bar ON myschema.mytable (id)',
        ];
    }

    public function testQuotesAlterTableRenameIndexInSchema(): void
    {
        $tableDiff            = new TableDiff('`schema`.table');
        $tableDiff->fromTable = new Table('`schema`.table');
        $tableDiff->fromTable->addColumn('id', Types::INTEGER);
        $tableDiff->fromTable->setPrimaryKey(['id']);
        $tableDiff->renamedIndexes = [
            'create' => new Index('select', ['id']),
            '`foo`'  => new Index('`bar`', ['id']),
        ];

        self::assertSame(
            $this->getQuotedAlterTableRenameIndexInSchemaSQL(),
            $this->platform->getAlterTableSQL($tableDiff),
        );
    }

    /** @return string[] */
    protected function getQuotedAlterTableRenameIndexInSchemaSQL(): array
    {
        return [
            'DROP INDEX "schema"."create"',
            'CREATE INDEX "select" ON "schema"."table" (id)',
            'DROP INDEX "schema"."foo"',
            'CREATE INDEX "bar" ON "schema"."table" (id)',
        ];
    }

    public function testQuotesDropForeignKeySQL(): void
    {
        $tableName      = 'table';
        $table          = new Table($tableName);
        $foreignKeyName = 'select';
        $foreignKey     = new ForeignKeyConstraint([], 'foo', [], 'select');
        $expectedSql    = $this->getQuotesDropForeignKeySQL();

        self::assertSame($expectedSql, $this->platform->getDropForeignKeySQL($foreignKeyName, $tableName));
        self::assertSame($expectedSql, $this->platform->getDropForeignKeySQL($foreignKey, $table));
    }

    protected function getQuotesDropForeignKeySQL(): string
    {
        return 'ALTER TABLE "table" DROP FOREIGN KEY "select"';
    }

    public function testQuotesDropConstraintSQL(): void
    {
        $tableName      = 'table';
        $table          = new Table($tableName);
        $constraintName = 'select';
        $constraint     = new ForeignKeyConstraint([], 'foo', [], 'select');
        $expectedSql    = $this->getQuotesDropConstraintSQL();

        self::assertSame($expectedSql, $this->platform->getDropConstraintSQL($constraintName, $tableName));
        self::assertSame($expectedSql, $this->platform->getDropConstraintSQL($constraint, $table));
    }

    protected function getQuotesDropConstraintSQL(): string
    {
        return 'ALTER TABLE "table" DROP CONSTRAINT "select"';
    }

    protected function getStringLiteralQuoteCharacter(): string
    {
        return "'";
    }

    public function testGetStringLiteralQuoteCharacter(): void
    {
        self::assertSame($this->getStringLiteralQuoteCharacter(), $this->platform->getStringLiteralQuoteCharacter());
    }

    protected function getQuotedCommentOnColumnSQLWithoutQuoteCharacter(): string
    {
        return "COMMENT ON COLUMN mytable.id IS 'This is a comment'";
    }

    public function testGetCommentOnColumnSQLWithoutQuoteCharacter(): void
    {
        self::assertEquals(
            $this->getQuotedCommentOnColumnSQLWithoutQuoteCharacter(),
            $this->platform->getCommentOnColumnSQL('mytable', 'id', 'This is a comment'),
        );
    }

    protected function getQuotedCommentOnColumnSQLWithQuoteCharacter(): string
    {
        return "COMMENT ON COLUMN mytable.id IS 'It''s a quote !'";
    }

    public function testGetCommentOnColumnSQLWithQuoteCharacter(): void
    {
        $c = $this->getStringLiteralQuoteCharacter();

        self::assertEquals(
            $this->getQuotedCommentOnColumnSQLWithQuoteCharacter(),
            $this->platform->getCommentOnColumnSQL('mytable', 'id', 'It' . $c . 's a quote !'),
        );
    }

    /**
     * @see testGetCommentOnColumnSQL
     *
     * @return string[]
     */
    abstract protected function getCommentOnColumnSQL(): array;

    public function testGetCommentOnColumnSQL(): void
    {
        self::assertSame(
            $this->getCommentOnColumnSQL(),
            [
                $this->platform->getCommentOnColumnSQL('foo', 'bar', 'comment'), // regular identifiers
                $this->platform->getCommentOnColumnSQL('`Foo`', '`BAR`', 'comment'), // explicitly quoted identifiers
                $this->platform->getCommentOnColumnSQL('select', 'from', 'comment'), // reserved keyword identifiers
            ],
        );
    }

    /** @dataProvider getGeneratesInlineColumnCommentSQL */
    public function testGeneratesInlineColumnCommentSQL(string $comment, string $expectedSql): void
    {
        if (! $this->platform->supportsInlineColumnComments()) {
            self::markTestSkipped(sprintf('%s does not support inline column comments.', get_class($this->platform)));
        }

        self::assertSame($expectedSql, $this->platform->getInlineColumnCommentSQL($comment));
    }

    /** @return mixed[][] */
    public static function getGeneratesInlineColumnCommentSQL(): iterable
    {
        return [
            'regular comment' => ['Regular comment', static::getInlineColumnRegularCommentSQL()],
            'comment requiring escaping' => [
                sprintf(
                    'Using inline comment delimiter %s works',
                    static::getInlineColumnCommentDelimiter(),
                ),
                static::getInlineColumnCommentRequiringEscapingSQL(),
            ],
            'empty comment' => ['', static::getInlineColumnEmptyCommentSQL()],
        ];
    }

    protected static function getInlineColumnCommentDelimiter(): string
    {
        return "'";
    }

    protected static function getInlineColumnRegularCommentSQL(): string
    {
        return "COMMENT 'Regular comment'";
    }

    protected static function getInlineColumnCommentRequiringEscapingSQL(): string
    {
        return "COMMENT 'Using inline comment delimiter '' works'";
    }

    protected static function getInlineColumnEmptyCommentSQL(): string
    {
        return "COMMENT ''";
    }

    protected function getQuotedStringLiteralWithoutQuoteCharacter(): string
    {
        return "'No quote'";
    }

    protected function getQuotedStringLiteralWithQuoteCharacter(): string
    {
        return "'It''s a quote'";
    }

    protected function getQuotedStringLiteralQuoteCharacter(): string
    {
        return "''''";
    }

    public function testThrowsExceptionOnGeneratingInlineColumnCommentSQLIfUnsupported(): void
    {
        if ($this->platform->supportsInlineColumnComments()) {
            self::markTestSkipped(sprintf('%s supports inline column comments.', get_class($this->platform)));
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Operation '" . AbstractPlatform::class . "::getInlineColumnCommentSQL' is not supported by platform.",
        );
        $this->expectExceptionCode(0);

        $this->platform->getInlineColumnCommentSQL('unsupported');
    }

    public function testQuoteStringLiteral(): void
    {
        $c = $this->getStringLiteralQuoteCharacter();

        self::assertEquals(
            $this->getQuotedStringLiteralWithoutQuoteCharacter(),
            $this->platform->quoteStringLiteral('No quote'),
        );
        self::assertEquals(
            $this->getQuotedStringLiteralWithQuoteCharacter(),
            $this->platform->quoteStringLiteral('It' . $c . 's a quote'),
        );
        self::assertEquals(
            $this->getQuotedStringLiteralQuoteCharacter(),
            $this->platform->quoteStringLiteral($c),
        );
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getGuidTypeDeclarationSQL([]);
    }

    public function testGeneratesAlterTableRenameColumnSQL(): void
    {
        $table = new Table('foo');
        $table->addColumn(
            'bar',
            Types::INTEGER,
            ['notnull' => true, 'default' => 666, 'comment' => 'rename test'],
        );

        $tableDiff                        = new TableDiff('foo');
        $tableDiff->fromTable             = $table;
        $tableDiff->renamedColumns['bar'] = new Column(
            'baz',
            Type::getType(Types::INTEGER),
            ['notnull' => true, 'default' => 666, 'comment' => 'rename test'],
        );

        self::assertSame($this->getAlterTableRenameColumnSQL(), $this->platform->getAlterTableSQL($tableDiff));
    }

    /** @return string[] */
    abstract public function getAlterTableRenameColumnSQL(): array;

    public function testAlterStringToFixedString(): void
    {
        $table = new Table('mytable');
        $table->addColumn('name', Types::STRING, ['length' => 2]);

        $tableDiff            = new TableDiff('mytable');
        $tableDiff->fromTable = $table;

        $tableDiff->changedColumns['name'] = new ColumnDiff(
            'name',
            new Column(
                'name',
                Type::getType(Types::STRING),
                ['fixed' => true, 'length' => 2],
            ),
            ['fixed'],
        );

        $sql = $this->platform->getAlterTableSQL($tableDiff);

        $expectedSql = $this->getAlterStringToFixedStringSQL();

        self::assertEquals($expectedSql, $sql);
    }

    /** @return string[] */
    abstract protected function getAlterStringToFixedStringSQL(): array;

    public function testGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): void
    {
        $foreignTable = new Table('foreign_table');
        $foreignTable->addColumn('id', Types::INTEGER);
        $foreignTable->setPrimaryKey(['id']);

        $primaryTable = new Table('mytable');
        $primaryTable->addColumn('foo', Types::INTEGER);
        $primaryTable->addColumn('bar', Types::INTEGER);
        $primaryTable->addColumn('baz', Types::INTEGER);
        $primaryTable->addIndex(['foo'], 'idx_foo');
        $primaryTable->addIndex(['bar'], 'idx_bar');
        $primaryTable->addForeignKeyConstraint($foreignTable, ['foo'], ['id'], [], 'fk_foo');
        $primaryTable->addForeignKeyConstraint($foreignTable, ['bar'], ['id'], [], 'fk_bar');

        $tableDiff                            = new TableDiff('mytable');
        $tableDiff->fromTable                 = $primaryTable;
        $tableDiff->renamedIndexes['idx_foo'] = new Index('idx_foo_renamed', ['foo']);

        self::assertSame(
            $this->getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(),
            $this->platform->getAlterTableSQL($tableDiff),
        );
    }

    /** @return string[] */
    abstract protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array;

    /**
     * @param mixed[] $column
     *
     * @dataProvider getGeneratesDecimalTypeDeclarationSQL
     */
    public function testGeneratesDecimalTypeDeclarationSQL(array $column, string $expectedSql): void
    {
        self::assertSame($expectedSql, $this->platform->getDecimalTypeDeclarationSQL($column));
    }

    /** @return mixed[][] */
    public static function getGeneratesDecimalTypeDeclarationSQL(): iterable
    {
        return [
            [[], 'NUMERIC(10, 0)'],
            [['unsigned' => true], 'NUMERIC(10, 0)'],
            [['unsigned' => false], 'NUMERIC(10, 0)'],
            [['precision' => 5], 'NUMERIC(5, 0)'],
            [['scale' => 5], 'NUMERIC(10, 5)'],
            [['precision' => 8, 'scale' => 2], 'NUMERIC(8, 2)'],
        ];
    }

    /**
     * @param mixed[] $column
     *
     * @dataProvider getGeneratesFloatDeclarationSQL
     */
    public function testGeneratesFloatDeclarationSQL(array $column, string $expectedSql): void
    {
        self::assertSame($expectedSql, $this->platform->getFloatDeclarationSQL($column));
    }

    /** @return mixed[][] */
    public static function getGeneratesFloatDeclarationSQL(): iterable
    {
        return [
            [[], 'DOUBLE PRECISION'],
            [['unsigned' => true], 'DOUBLE PRECISION'],
            [['unsigned' => false], 'DOUBLE PRECISION'],
            [['precision' => 5], 'DOUBLE PRECISION'],
            [['scale' => 5], 'DOUBLE PRECISION'],
            [['precision' => 8, 'scale' => 2], 'DOUBLE PRECISION'],
        ];
    }

    public function testItEscapesStringsForLike(): void
    {
        self::assertSame(
            '\_25\% off\_ your next purchase \\\\o/',
            $this->platform->escapeStringForLike('_25% off_ your next purchase \o/', '\\'),
        );
    }

    public function testZeroOffsetWithoutLimitIsIgnored(): void
    {
        $query = 'SELECT * FROM user';

        self::assertSame(
            $query,
            $this->platform->modifyLimitQuery($query, null, 0),
        );
    }

    public function testLimitOffsetCastToInt(): void
    {
        self::assertSame(
            $this->getLimitOffsetCastToIntExpectedQuery(),
            $this->platform->modifyLimitQuery('SELECT * FROM user', '1 BANANA', '2 APPLES'),
        );
    }

    protected function getLimitOffsetCastToIntExpectedQuery(): string
    {
        return 'SELECT * FROM user LIMIT 1 OFFSET 2';
    }

    /**
     * @param array<string, mixed> $column
     *
     * @dataProvider asciiStringSqlDeclarationDataProvider
     */
    public function testAsciiSQLDeclaration(string $expectedSql, array $column): void
    {
        $declarationSql = $this->platform->getAsciiStringTypeDeclarationSQL($column);
        self::assertEquals($expectedSql, $declarationSql);
    }

    /** @return array<int, array{string, array<string, mixed>}> */
    public static function asciiStringSqlDeclarationDataProvider(): array
    {
        return [
            ['VARCHAR(12)', ['length' => 12]],
            ['CHAR(12)', ['length' => 12, 'fixed' => true]],
        ];
    }

    public function testInvalidLockMode(): void
    {
        $this->expectException(InvalidLockMode::class);
        $this->platform->appendLockHint('TABLE', 128);
    }

    public function testItAddsCommentsForOverridingTypes(): void
    {
        $this->backedUpType = Type::getType(Types::STRING);
        self::assertFalse($this->platform->isCommentedDoctrineType($this->backedUpType));
        $type = new class () extends StringType {
            public function getName(): string
            {
                return Types::STRING;
            }

            public function requiresSQLCommentHint(AbstractPlatform $platform): bool
            {
                return true;
            }
        };
        Type::getTypeRegistry()->override(Types::STRING, $type);
        self::assertTrue($this->platform->isCommentedDoctrineType($type));
    }

    public function testEmptyTableDiff(): void
    {
        $diff = new TableDiff('test');

        self::assertTrue($diff->isEmpty());
        self::assertSame([], $this->platform->getAlterTableSQL($diff));
    }

    public function testEmptySchemaDiff(): void
    {
        $diff = new SchemaDiff();

        self::assertTrue($diff->isEmpty());
        self::assertSame([], $this->platform->getAlterSchemaSQL($diff));
    }

    public function tearDown(): void
    {
        if (! isset($this->backedUpType)) {
            return;
        }

        Type::getTypeRegistry()->override(Types::STRING, $this->backedUpType);
        $this->backedUpType = null;
    }
}

interface GetCreateTableSqlDispatchEventListener
{
    public function onSchemaCreateTable(): void;

    public function onSchemaCreateTableColumn(): void;
}

interface GetAlterTableSqlDispatchEventListener
{
    public function onSchemaAlterTable(): void;

    public function onSchemaAlterTableAddColumn(): void;

    public function onSchemaAlterTableRemoveColumn(): void;

    public function onSchemaAlterTableChangeColumn(): void;

    public function onSchemaAlterTableRenameColumn(): void;
}

interface GetDropTableSqlDispatchEventListener
{
    public function onSchemaDropTable(): void;
}
