<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Platforms;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\InvalidColumnDeclaration;
use Doctrine\DBAL\Exception\InvalidColumnType\ColumnValuesRequired;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\Exception\UnsupportedIndexDefinition;
use Doctrine\DBAL\Platforms\Exception\UnsupportedPrimaryKeyConstraintDefinition;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
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
        return new Comparator($this->platform, new ComparatorConfig());
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
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setColumnNames(UnqualifiedName::unquoted('id'))
                ->create(),
        );

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
        $index = Index::editor()
            ->setName(UnqualifiedName::unquoted('my_idx'))
            ->setColumnNames(
                UnqualifiedName::unquoted('user_name'),
                UnqualifiedName::unquoted('last_login'),
            )
            ->create();

        self::assertEquals(
            $this->getGenerateIndexSql(),
            $this->platform->getCreateIndexSQL($index, 'mytable'),
        );
    }

    abstract public function getGenerateIndexSql(): string;

    public function testGeneratesUniqueIndexCreationSql(): void
    {
        $index = Index::editor()
            ->setUnquotedName('index_name')
            ->setType(IndexType::UNIQUE)
            ->setUnquotedColumnNames('test', 'test2')
            ->create();

        $sql = $this->platform->getCreateIndexSQL($index, 'test');
        self::assertEquals($this->getGenerateUniqueIndexSql(), $sql);
    }

    abstract public function getGenerateUniqueIndexSql(): string;

    public function testGeneratesForeignKeyCreationSql(): void
    {
        $fk = ForeignKeyConstraint::editor()
            ->setUnquotedReferencingColumnNames('fk_name_id')
            ->setUnquotedReferencedTableName('other_table')
            ->setUnquotedReferencedColumnNames('id')
            ->create();

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

    public function testQuotedColumnInPrimaryKeyPropagation(): void
    {
        $table = new Table('`quoted`');
        $table->addColumn('create', Types::STRING, ['length' => 255]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setColumnNames(UnqualifiedName::unquoted('create'))
                ->create(),
        );

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
            $foreignTable->getObjectName()->toSQL($this->platform),
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_RESERVED_KEYWORD',
        );

        // Foreign table with non-reserved keyword as name
        $foreignTable = new Table('foo');

        // Foreign column with reserved keyword as name
        $foreignTable->addColumn('create', Types::STRING);

        // Foreign column with non-reserved keyword as name
        $foreignTable->addColumn('bar', Types::STRING);

        // Foreign table with special character in name
        $foreignTable->addColumn('`foo-bar`', Types::STRING);

        $table->addForeignKeyConstraint(
            $foreignTable->getObjectName()->toSQL($this->platform),
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
            $foreignTable->getObjectName()->toSQL($this->platform),
            ['create', 'foo', '`bar`'],
            ['create', 'bar', '`foo-bar`'],
            [],
            'FK_WITH_INTENDED_QUOTATION',
        );

        $sql = $this->platform->getCreateTableSQL($table);
        self::assertEquals($this->getQuotedColumnInForeignKeySQL(), $sql);
    }

    public function testQuotesReservedKeywordInTruncateTableSQL(): void
    {
        self::assertSame(
            $this->getQuotesReservedKeywordInTruncateTableSQL(),
            $this->platform->getTruncateTableSQL('select'),
        );
    }

    abstract protected function getQuotesReservedKeywordInTruncateTableSQL(): string;

    abstract protected function getQuotesReservedKeywordInIndexDeclarationSQL(): string;

    public function testGetCreateSchemaSQL(): void
    {
        $this->expectException(Exception::class);

        $this->platform->getCreateSchemaSQL('schema');
    }

    public function testAlterTableChangeQuotedColumn(): void
    {
        $table = new Table('mytable');
        $table->addColumn('select', Types::INTEGER);

        $tableDiff = new TableDiff($table, changedColumns: [
            'select' => new ColumnDiff(
                $table->getColumn('select'),
                new Column(
                    'select',
                    Type::getType(Types::STRING),
                    ['length' => 255],
                ),
            ),
        ]);

        self::assertStringContainsString(
            $this->platform->quoteSingleIdentifier(
                $this->platform->getUnquotedIdentifierFolding()
                    ->foldUnquotedIdentifier('select'),
            ),
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
            $this->platform->getStringTypeDeclarationSQL([
                'name' => UnqualifiedName::unquoted('email'),
            ]),
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
            $this->platform->getBinaryTypeDeclarationSQL([
                'name' => UnqualifiedName::unquoted('checksum'),
                'fixed' => true,
            ]),
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
            $this->platform->getBinaryTypeDeclarationSQL([
                'name' => UnqualifiedName::unquoted('attachment'),
            ]),
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
        $this->platform->getDecimalTypeDeclarationSQL([
            'name' => UnqualifiedName::unquoted('price'),
            'scale' => 2,
        ]);
    }

    public function testGetDecimalTypeDeclarationSQLNoScale(): void
    {
        $this->expectException(InvalidColumnDeclaration::class);
        $this->platform->getDecimalTypeDeclarationSQL([
            'name' => UnqualifiedName::unquoted('price'),
            'precision' => 10,
        ]);
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
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setColumnNames(UnqualifiedName::unquoted('id'))
                ->create(),
        );

        $tableDiff = new TableDiff($table, renamedIndexes: [
            'idx_foo' => Index::editor()
                ->setName(UnqualifiedName::unquoted('idx_bar'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('id'),
                )
                ->create(),
        ]);

        self::assertSame(
            $this->getAlterTableRenameIndexSQL(),
            $this->platform->getAlterTableSQL($tableDiff),
        );
    }

    /** @return string[] */
    protected function getAlterTableRenameIndexSQL(): array
    {
        return [
            'DROP INDEX `idx_foo`',
            'CREATE INDEX `idx_bar` ON mytable (`id`)',
        ];
    }

    public function testQuotesAlterTableRenameIndex(): void
    {
        $table = new Table('table');
        $table->addColumn('id', Types::INTEGER);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setColumnNames(UnqualifiedName::unquoted('id'))
                ->create(),
        );

        $tableDiff = new TableDiff($table, renamedIndexes: [
            'create' => Index::editor()
                ->setName(UnqualifiedName::unquoted('select'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('id'),
                )
                ->create(),
            'foo' => Index::editor()
                ->setName(UnqualifiedName::unquoted('bar'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('id'),
                )
                ->create(),
        ]);

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
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setColumnNames(UnqualifiedName::unquoted('id'))
                ->create(),
        );

        $tableDiff = new TableDiff($table, renamedIndexes: [
            'idx_foo' => Index::editor()
                ->setName(UnqualifiedName::unquoted('idx_bar'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('id'),
                )
                ->create(),
        ]);

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
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setColumnNames(UnqualifiedName::unquoted('id'))
                ->create(),
        );

        $tableDiff = new TableDiff($table, renamedIndexes: [
            'create' => Index::editor()
                ->setName(UnqualifiedName::unquoted('select'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('id'),
                )
                ->create(),
            'foo' => Index::editor()
                ->setName(UnqualifiedName::unquoted('bar'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('id'),
                )
                ->create(),
        ]);

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
        return "COMMENT ON COLUMN \"MYTABLE\".\"ID\" IS 'This is a comment'";
    }

    protected function getQuotedCommentOnColumnSQLWithQuoteCharacter(): string
    {
        return "COMMENT ON COLUMN \"MYTABLE\".\"ID\" IS 'It''s a quote !'";
    }

    /**
     * @see testGetCommentOnColumnSQL
     *
     * @return string[]
     */
    abstract protected function getCommentOnColumnSQL(): array;

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

        $tableDiff = new TableDiff($table, changedColumns: [
            'name' => new ColumnDiff(
                $table->getColumn('name'),
                new Column(
                    'name',
                    Type::getType(Types::STRING),
                    ['fixed' => true, 'length' => 2],
                ),
            ),
        ]);

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
        $foreignTable->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setColumnNames(UnqualifiedName::unquoted('id'))
                ->create(),
        );

        $primaryTable = new Table('mytable');
        $primaryTable->addColumn('foo', Types::INTEGER);
        $primaryTable->addColumn('bar', Types::INTEGER);
        $primaryTable->addColumn('baz', Types::INTEGER);
        $primaryTable->addIndex(['foo'], 'idx_foo');
        $primaryTable->addIndex(['bar'], 'idx_bar');
        $primaryTable->addForeignKeyConstraint($foreignTable->getName(), ['foo'], ['id'], [], 'fk_foo');
        $primaryTable->addForeignKeyConstraint($foreignTable->getName(), ['bar'], ['id'], [], 'fk_bar');

        $tableDiff = new TableDiff($primaryTable, renamedIndexes: [
            'idx_foo' => Index::editor()
                ->setName(UnqualifiedName::unquoted('idx_foo_renamed'))
                ->setColumnNames(
                    UnqualifiedName::unquoted('foo'),
                )
                ->create(),
        ]);

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

    /** @param mixed[] $column */
    #[DataProvider('getGeneratesSmallFloatDeclarationSQL')]
    public function testGeneratesSmallFloatDeclarationSQL(array $column, string $expectedSql): void
    {
        self::assertSame($expectedSql, $this->platform->getSmallFloatDeclarationSQL($column));
    }

    /** @return mixed[][] */
    public static function getGeneratesSmallFloatDeclarationSQL(): iterable
    {
        return [
            [[], 'REAL'],
            [['unsigned' => true], 'REAL'],
            [['unsigned' => false], 'REAL'],
            [['precision' => 5], 'REAL'],
            [['scale' => 5], 'REAL'],
            [['precision' => 4, 'scale' => 2], 'REAL'],
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

    /** @param array<string> $values */
    #[DataProvider('getEnumDeclarationSQLProvider')]
    public function testGetEnumDeclarationSQL(array $values, string $expectedSQL): void
    {
        self::assertSame($expectedSQL, $this->platform->getEnumDeclarationSQL(['values' => $values]));
    }

    /** @return array<string, array{array<string>, string}> */
    public static function getEnumDeclarationSQLProvider(): array
    {
        return [
            'single value' => [['foo'], 'VARCHAR(3)'],
            'multiple values' => [['foo', 'bar1'], 'VARCHAR(4)'],
        ];
    }

    /** @param array<mixed> $column */
    #[DataProvider('getEnumDeclarationSQLWithInvalidValuesProvider')]
    public function testGetEnumDeclarationSQLWithInvalidValues(array $column): void
    {
        self::expectException(ColumnValuesRequired::class);
        $this->platform->getEnumDeclarationSQL($column);
    }

    /** @return array<string, array{array<mixed>}> */
    public static function getEnumDeclarationSQLWithInvalidValuesProvider(): array
    {
        return [
            "field 'values' does not exist" => [[]],
            "field 'values' is not an array" => [['values' => 'foo']],
            "field 'values' is an empty array" => [['values' => []]],
        ];
    }

    /**
     * The list of tested platforms should be kept in-sync with
     * {@see PrimaryKeyConstraintTest::testNameIntrospection()}.
     */
    public function testNamedPrimaryKeyConstraintIsReportedAsUnsupported(): void
    {
        if (! $this->platform instanceof MySqlPlatform && ! $this->platform instanceof SQLitePlatform) {
            self::markTestSkipped('This current database platform supports named primary key constraints.');
        }

        $table = new Table('users');
        $table->addColumn('id', Types::INTEGER);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setName(
                    UnqualifiedName::unquoted('users_pk'),
                )
                ->setColumnNames(UnqualifiedName::unquoted('id'))
                ->create(),
        );

        $this->expectException(UnsupportedPrimaryKeyConstraintDefinition::class);
        $this->platform->getCreateTableSQL($table);
    }

    /**
     * The list of tested platforms should be kept in-sync with
     * {@see PrimaryKeyConstraintTest::testIsClusteredIntrospection()}.
     */
    public function testNonClusteredPrimaryKeyConstraintIsReportedAsUnsupported(): void
    {
        if ($this->platform instanceof SQLServerPlatform) {
            self::markTestSkipped('This current database platform supports non-clustered primary key constraints.');
        }

        $table = new Table('users');
        $table->addColumn('id', Types::INTEGER);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()
                ->setIsClustered(false)
                ->setColumnNames(UnqualifiedName::unquoted('id'))
                ->create(),
        );

        $this->expectException(UnsupportedPrimaryKeyConstraintDefinition::class);
        $this->platform->getCreateTableSQL($table);
    }

    public function testIndexWithColumnLengthsIsReportedAsUnsupported(): void
    {
        if ($this->platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('This current database platform supports indexes with column lengths.');
        }

        $index = Index::editor()
            ->setName(UnqualifiedName::unquoted('idx_username'))
            ->setColumns(
                new IndexedColumn(UnqualifiedName::unquoted('username'), 32),
            )
            ->create();

        $this->expectException(UnsupportedIndexDefinition::class);
        $this->platform->getCreateIndexSQL($index, 'table');
    }

    #[TestWith([IndexType::FULLTEXT])]
    #[TestWith([IndexType::SPATIAL])]
    public function testIndexTypeIsReportedAsUnsupported(IndexType $type): void
    {
        if ($this->platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped(
                sprintf('This current database platform supports indexes with type %s.', $type->name),
            );
        }

        $index = Index::editor()
            ->setName(UnqualifiedName::unquoted('idx_test'))
            ->setType($type)
            ->setColumnNames(
                UnqualifiedName::unquoted('test'),
            )
            ->create();

        $this->expectException(UnsupportedIndexDefinition::class);
        $this->platform->getCreateIndexSQL($index, 'table');
    }

    public function testClusteredIndexIsReportedAsUnsupported(): void
    {
        if ($this->platform instanceof SQLServerPlatform) {
            self::markTestSkipped('This current database platform supports clustered indexes.');
        }

        $index = Index::editor()
            ->setName(UnqualifiedName::unquoted('idx_sku'))
            ->setColumnNames(
                UnqualifiedName::unquoted('sku'),
            )
            ->setIsClustered(true)
            ->create();

        $this->expectException(UnsupportedIndexDefinition::class);
        $this->platform->getCreateIndexSQL($index, 'table');
    }

    public function testPartialIndexIsReportedAsUnsupported(): void
    {
        if ($this->platform instanceof PostgreSQLPlatform) {
            self::markTestSkipped('This current database platform supports partial indexes.');
        }

        $index = Index::editor()
            ->setName(UnqualifiedName::unquoted('idx_username'))
            ->setColumnNames(
                UnqualifiedName::unquoted('username'),
            )
            ->setPredicate('is_active = 1')
            ->create();

        $this->expectException(UnsupportedIndexDefinition::class);
        $this->platform->getCreateIndexSQL($index, 'table');
    }
}
