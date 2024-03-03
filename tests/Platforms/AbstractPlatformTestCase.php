<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function implode;
use function sprintf;

/** @template T of AbstractPlatform */
abstract class AbstractPlatformTestCase extends TestCase
{
    /** @var T */
    protected AbstractPlatform $platform;

    /** @return T */
    abstract public function createPlatform(): AbstractPlatform;

    protected function setUp(): void
    {
        $this->platform = $this->createPlatform();
    }

    protected function createComparator(): Comparator
    {
        return new Comparator($this->platform);
    }

    public function testQuoteIdentifier(): void
    {
        self::assertEquals('"test"."test"', $this->platform->quoteIdentifier('test.test'));
    }

    #[DataProvider('getReturnsForeignKeyReferentialActionSQL')]
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
            Type::overrideType($type->getName(), $type::class);
        } else {
            Type::addType($type->getName(), $type::class);
        }

        self::assertSame($type->getName(), $this->platform->getDoctrineTypeMapping('TeStTyPe'));
    }

    public function testRegisterUnknownDoctrineMappingType(): void
    {
        $this->expectException(Exception::class);
        $this->platform->registerDoctrineTypeMapping('foo', 'bar');
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
            $indexes[] = $this->platform->getIndexDeclarationSQL($indexDef);
        }

        $uniqueConstraintSQL = $this->platform->getUniqueConstraintDeclarationSQL($uniqueConstraint);
        self::assertStringEndsNotWith($expected, $uniqueConstraintSQL, 'WHERE clause should NOT be present');

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
        $fk = new ForeignKeyConstraint(['fk_name_id'], 'other_table', ['id']);

        $sql = $this->platform->getCreateForeignKeySQL($fk, 'test');
        self::assertEquals($this->getGenerateForeignKeySql(), $sql);
    }

    abstract protected function getGenerateForeignKeySql(): string;

    protected function getBitAndComparisonExpressionSql(string $value1, string $value2): string
    {
        return '(' . $value1 . ' & ' . $value2 . ')';
    }

    public function testGeneratesBitAndComparisonExpressionSql(): void
    {
        $sql = $this->platform->getBitAndComparisonExpression('2', '4');
        self::assertEquals($this->getBitAndComparisonExpressionSql('2', '4'), $sql);
    }

    protected function getBitOrComparisonExpressionSql(string $value1, string $value2): string
    {
        return '(' . $value1 . ' | ' . $value2 . ')';
    }

    public function testGeneratesBitOrComparisonExpressionSql(): void
    {
        $sql = $this->platform->getBitOrComparisonExpression('2', '4');
        self::assertEquals($this->getBitOrComparisonExpressionSql('2', '4'), $sql);
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

        self::assertTrue($keywordList->isKeyword('table'));
    }

    public function testQuotedColumnInPrimaryKeyPropagation(): void
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', Types::STRING, ['length' => 255]);
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
        $table->addColumn('create', Types::STRING, ['length' => 255]);
        $table->addIndex(['create']);

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedColumnInIndexSQL(), $sql);
    }

    public function testQuotedNameInIndexSQL(): void
    {
        $table = new Table('test');
        $table->addColumn('column1', Types::STRING, ['length' => 255]);
        $table->addIndex(['column1'], '`key`');

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedNameInIndexSQL(), $sql);
    }

    public function testQuotedColumnInForeignKeyPropagation(): void
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', Types::STRING, ['length' => 255]);
        $table->addColumn('foo', Types::STRING, ['length' => 255]);
        $table->addColumn('`bar`', Types::STRING, ['length' => 255]);

        // Foreign table with reserved keyword as name (needs quotation).
        $foreignTable = new Table('foreign');

        // Foreign column with reserved keyword as name (needs quotation).
        $foreignTable->addColumn('create', Types::STRING);

        // Foreign column with non-reserved keyword as name (does not need quotation).
        $foreignTable->addColumn('bar', Types::STRING);

        // Foreign table with special character in name (needs quotation on some platforms, e.g. Sqlite).
        $foreignTable->addColumn('`foo-bar`', Types::STRING);

        $table->addForeignKeyConstraint(
            $foreignTable->getQuotedName($this->platform),
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
            $foreignTable->getQuotedName($this->platform),
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
            $foreignTable->getQuotedName($this->platform),
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_INTENDED_QUOTATION',
        );

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedColumnInForeignKeySQL(), $sql);
    }

    public function testQuotesReservedKeywordInUniqueConstraintDeclarationSQL(): void
    {
        $constraint = new UniqueConstraint('select', ['foo'], [], []);

        self::assertSame(
            $this->getQuotesReservedKeywordInUniqueConstraintDeclarationSQL(),
            $this->platform->getUniqueConstraintDeclarationSQL($constraint),
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
            $this->platform->getIndexDeclarationSQL($index),
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

        $tableDiff = new TableDiff($table, [], [
            new ColumnDiff(
                $table->getColumn('select'),
                new Column(
                    'select',
                    Type::getType(Types::STRING),
                    ['length' => 255],
                ),
            ),
        ], [], [], [], [], [], [], [], [], []);

        self::assertStringContainsString(
            $this->platform->quoteIdentifier('select'),
            implode(';', $this->platform->getAlterTableSQL($tableDiff)),
        );
    }

    public function testGetFixedLengthStringTypeDeclarationSQLNoLength(): void
    {
        self::assertSame(
            $this->getExpectedFixedLengthStringTypeDeclarationSQLNoLength(),
            $this->platform->getStringTypeDeclarationSQL(['fixed' => true]),
        );
    }

    protected function getExpectedFixedLengthStringTypeDeclarationSQLNoLength(): string
    {
        return 'CHAR';
    }

    public function testGetFixedLengthStringTypeDeclarationSQLWithLength(): void
    {
        self::assertSame(
            $this->getExpectedFixedLengthStringTypeDeclarationSQLWithLength(),
            $this->platform->getStringTypeDeclarationSQL([
                'fixed' => true,
                'length' => 16,
            ]),
        );
    }

    protected function getExpectedFixedLengthStringTypeDeclarationSQLWithLength(): string
    {
        return 'CHAR(16)';
    }

    public function testGetVariableLengthStringTypeDeclarationSQLNoLength(): void
    {
        self::assertSame(
            $this->getExpectedVariableLengthStringTypeDeclarationSQLNoLength(),
            $this->platform->getStringTypeDeclarationSQL(['name' => 'email']),
        );
    }

    protected function getExpectedVariableLengthStringTypeDeclarationSQLNoLength(): string
    {
        return 'VARCHAR';
    }

    public function testGetVariableLengthStringTypeDeclarationSQLWithLength(): void
    {
        self::assertSame(
            $this->getExpectedVariableLengthStringTypeDeclarationSQLWithLength(),
            $this->platform->getStringTypeDeclarationSQL(['length' => 16]),
        );
    }

    protected function getExpectedVariableLengthStringTypeDeclarationSQLWithLength(): string
    {
        return 'VARCHAR(16)';
    }

    public function testGetFixedLengthBinaryTypeDeclarationSQLNoLength(): void
    {
        self::assertSame(
            $this->getExpectedFixedLengthBinaryTypeDeclarationSQLNoLength(),
            $this->platform->getBinaryTypeDeclarationSQL(['name' => 'checksum', 'fixed' => true]),
        );
    }

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        return 'BINARY';
    }

    public function testGetFixedLengthBinaryTypeDeclarationSQLWithLength(): void
    {
        self::assertSame(
            $this->getExpectedFixedLengthBinaryTypeDeclarationSQLWithLength(),
            $this->platform->getBinaryTypeDeclarationSQL([
                'fixed' => true,
                'length' => 16,
            ]),
        );
    }

    public function getExpectedFixedLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        return 'BINARY(16)';
    }

    public function testGetVariableLengthBinaryTypeDeclarationSQLNoLength(): void
    {
        self::assertSame(
            $this->getExpectedVariableLengthBinaryTypeDeclarationSQLNoLength(),
            $this->platform->getBinaryTypeDeclarationSQL(['name' => 'attachment']),
        );
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLNoLength(): string
    {
        return 'VARBINARY';
    }

    public function testGetVariableLengthBinaryTypeDeclarationSQLWithLength(): void
    {
        self::assertSame(
            $this->getExpectedVariableLengthBinaryTypeDeclarationSQLWithLength(),
            $this->platform->getBinaryTypeDeclarationSQL(['length' => 16]),
        );
    }

    public function getExpectedVariableLengthBinaryTypeDeclarationSQLWithLength(): string
    {
        return 'VARBINARY(16)';
    }

    public function testGetDecimalTypeDeclarationSQLNoPrecision(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);
        $this->platform->getDecimalTypeDeclarationSQL(['name' => 'price', 'scale' => 2]);
    }

    public function testGetDecimalTypeDeclarationSQLNoScale(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);
        $this->platform->getDecimalTypeDeclarationSQL(['name' => 'price', 'precision' => 10]);
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
        $table = new Table('mytable');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $tableDiff = new TableDiff($table, [], [], [], [], [], [], [], [
            'idx_foo' => new Index('idx_bar', ['id']),
        ], [], [], []);

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
        $table = new Table('table');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $tableDiff = new TableDiff($table, [], [], [], [], [], [], [], [
            'create' => new Index('select', ['id']),
            '`foo`' => new Index('`bar`', ['id']),
        ], [], [], []);

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

    public function testAlterTableRenameIndexInSchema(): void
    {
        $table = new Table('myschema.mytable');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $tableDiff = new TableDiff($table, [], [], [], [], [], [], [], [
            'idx_foo' => new Index('idx_bar', ['id']),
        ], [], [], []);

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
        $table = new Table('`schema`.table');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $tableDiff = new TableDiff($table, [], [], [], [], [], [], [], [
            'create' => new Index('select', ['id']),
            '`foo`' => new Index('`bar`', ['id']),
        ], [], [], []);

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
        self::assertEquals(
            $this->getQuotedCommentOnColumnSQLWithQuoteCharacter(),
            $this->platform->getCommentOnColumnSQL('mytable', 'id', "It's a quote !"),
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

    #[DataProvider('getGeneratesInlineColumnCommentSQL')]
    public function testGeneratesInlineColumnCommentSQL(string $comment, string $expectedSql): void
    {
        if (! $this->platform->supportsInlineColumnComments()) {
            self::markTestSkipped(sprintf('%s does not support inline column comments.', $this->platform::class));
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

    public function testThrowsExceptionOnGeneratingInlineColumnCommentSQLIfUnsupported(): void
    {
        if ($this->platform->supportsInlineColumnComments()) {
            self::markTestSkipped(sprintf('%s supports inline column comments.', $this->platform::class));
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Operation "' . AbstractPlatform::class . '::getInlineColumnCommentSQL" is not supported by platform.',
        );
        $this->expectExceptionCode(0);

        $this->platform->getInlineColumnCommentSQL('unsupported');
    }

    public function testQuoteStringLiteral(): void
    {
        self::assertEquals("'No quote'", $this->platform->quoteStringLiteral('No quote'));
        self::assertEquals("'It''s a quote'", $this->platform->quoteStringLiteral("It's a quote"));
        self::assertEquals("''''", $this->platform->quoteStringLiteral("'"));
    }

    public function testReturnsGuidTypeDeclarationSQL(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getGuidTypeDeclarationSQL([]);
    }

    public function testAlterStringToFixedString(): void
    {
        $table = new Table('mytable');
        $table->addColumn('name', Types::STRING, ['length' => 2]);

        $tableDiff = new TableDiff($table, [], [
            new ColumnDiff(
                $table->getColumn('name'),
                new Column(
                    'name',
                    Type::getType(Types::STRING),
                    ['fixed' => true, 'length' => 2],
                ),
            ),
        ], [], [], [], [], [], [], [], [], []);

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
        $primaryTable->addForeignKeyConstraint($foreignTable->getName(), ['foo'], ['id'], [], 'fk_foo');
        $primaryTable->addForeignKeyConstraint($foreignTable->getName(), ['bar'], ['id'], [], 'fk_bar');

        $tableDiff = new TableDiff($primaryTable, [], [], [], [], [], [], [], [
            'idx_foo' => new Index('idx_foo_renamed', ['foo']),
        ], [], [], []);

        self::assertSame(
            $this->getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(),
            $this->platform->getAlterTableSQL($tableDiff),
        );
    }

    /** @return string[] */
    abstract protected function getGeneratesAlterTableRenameIndexUsedByForeignKeySQL(): array;

    /** @param mixed[] $column */
    #[DataProvider('getGeneratesDecimalTypeDeclarationSQL')]
    public function testGeneratesDecimalTypeDeclarationSQL(array $column, string $expectedSql): void
    {
        self::assertSame($expectedSql, $this->platform->getDecimalTypeDeclarationSQL($column));
    }

    /** @return iterable<array{array<string,mixed>,string}> */
    public static function getGeneratesDecimalTypeDeclarationSQL(): iterable
    {
        yield [['precision' => 10, 'scale' => 0], 'NUMERIC(10, 0)'];
        yield [['precision' => 8, 'scale' => 2], 'NUMERIC(8, 2)'];
    }

    /** @param mixed[] $column */
    #[DataProvider('getGeneratesFloatDeclarationSQL')]
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

    /** @param array<string, mixed> $column */
    #[DataProvider('asciiStringSqlDeclarationDataProvider')]
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
