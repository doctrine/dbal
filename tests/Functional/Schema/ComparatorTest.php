<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\DefaultExpression;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Platform\RenameColumnTest;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

use function sprintf;
use function version_compare;

class ComparatorTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->schemaManager = $this->connection->createSchemaManager();
    }

    #[DataProvider('defaultValueProvider')]
    public function testDefaultValueComparison(string $typeName, mixed $value): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (
            $typeName === Types::TEXT && $platform instanceof AbstractMySQLPlatform
            && ! $platform instanceof MariaDBPlatform
        ) {
            // See https://dev.mysql.com/doc/relnotes/mysql/8.0/en/news-8-0-13.html#mysqld-8-0-13-data-types
            self::markTestSkipped('Oracle MySQL does not support default values on TEXT/BLOB columns until 8.0.13.');
        }

        $table = Table::editor()
            ->setUnquotedName('default_value')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('test')
                    ->setTypeName($typeName)
                    ->setDefaultValue($value)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('default_value');

        self::assertTrue(
            $this->schemaManager->createComparator()
                ->compareTables($onlineTable, $table)
                ->isEmpty(),
        );
    }

    /** @param class-string<AbstractPlatform> $platformClass */
    #[DataProvider('functionDefaultProvider')]
    public function testFunctionDefaultComparison(
        string $platformClass,
        string $minServerVersion,
        string $typeName,
        string $expression,
    ): void {
        if (! $this->connection->getDatabasePlatform() instanceof $platformClass) {
            self::markTestSkipped(sprintf('Only relevant for %s', $platformClass));
        }

        if (version_compare($this->connection->getServerVersion(), $minServerVersion, '<')) {
            self::markTestSkipped(sprintf('Requires server version %s or newer', $minServerVersion));
        }

        $table = Table::editor()
            ->setUnquotedName('function_default')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('test')
                    ->setTypeName($typeName)
                    ->setLength(32)
                    ->setDefaultValue($this->createDefaultExpression($expression))
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $this->assertNoDiffInEitherDirection($table);
    }

    /**
     * Each expression is specified in the form the database normalizes it to.
     *
     * @return iterable<string, array{class-string<AbstractPlatform>, string, string, string}>
     */
    public static function functionDefaultProvider(): iterable
    {
        yield 'PostgreSQL gen_random_uuid()' => [PostgreSQLPlatform::class, '13', Types::GUID, 'gen_random_uuid()'];
        yield 'PostgreSQL now()' => [PostgreSQLPlatform::class, '0', Types::DATETIME_MUTABLE, 'now()'];
        yield 'PostgreSQL random()' => [PostgreSQLPlatform::class, '0', Types::FLOAT, 'random()'];
        yield 'PostgreSQL upper()' => [PostgreSQLPlatform::class, '0', Types::STRING, "upper('abc'::text)"];
        yield 'PostgreSQL concatenation' => [
            PostgreSQLPlatform::class,
            '0',
            Types::STRING,
            "('a'::text || 'b'::text)",
        ];

        yield 'MySQL uuid()' => [MySQLPlatform::class, '8.0.13', Types::GUID, '(uuid())'];
        yield 'MySQL rand()' => [MySQLPlatform::class, '8.0.13', Types::FLOAT, '(rand())'];
        yield 'MySQL curdate()' => [MySQLPlatform::class, '8.0.13', Types::DATE_MUTABLE, '(curdate())'];

        yield 'MariaDB uuid()' => [MariaDBPlatform::class, '0', Types::GUID, 'uuid()'];
        yield 'MariaDB rand()' => [MariaDBPlatform::class, '0', Types::FLOAT, 'rand()'];
        yield 'MariaDB hex(rand())' => [MariaDBPlatform::class, '0', Types::STRING, 'hex(rand())'];
        yield 'MariaDB ucase()' => [MariaDBPlatform::class, '0', Types::STRING, "ucase('abc')"];
        yield 'MariaDB concat()' => [MariaDBPlatform::class, '0', Types::STRING, "concat('a','b')"];

        yield 'SQL Server newid()' => [SQLServerPlatform::class, '0', Types::GUID, 'newid()'];
        yield 'SQL Server sysdatetime()' => [SQLServerPlatform::class, '0', Types::DATETIME_MUTABLE, 'sysdatetime()'];
        yield 'SQL Server rand()' => [SQLServerPlatform::class, '0', Types::FLOAT, 'rand()'];
        yield 'SQL Server upper()' => [SQLServerPlatform::class, '0', Types::STRING, "upper('abc')"];
        yield 'SQL Server concatenation' => [SQLServerPlatform::class, '0', Types::STRING, "'a'+'b'"];
        yield 'SQL Server arithmetic' => [SQLServerPlatform::class, '0', Types::INTEGER, '(1)+(2)'];

        yield 'Oracle SYS_GUID()' => [OraclePlatform::class, '0', Types::GUID, 'SYS_GUID()'];
        yield 'Oracle SYSDATE' => [OraclePlatform::class, '0', Types::DATE_MUTABLE, 'SYSDATE'];
        yield 'Oracle SYSTIMESTAMP' => [OraclePlatform::class, '0', Types::DATETIME_MUTABLE, 'SYSTIMESTAMP'];
        yield 'Oracle UPPER()' => [OraclePlatform::class, '0', Types::STRING, "UPPER('abc')"];
        yield 'Oracle concatenation' => [OraclePlatform::class, '0', Types::STRING, "'a' || 'b'"];

        yield 'SQLite randomblob()' => [SQLitePlatform::class, '0', Types::GUID, '(lower(hex(randomblob(16))))'];
        yield 'SQLite random()' => [SQLitePlatform::class, '0', Types::INTEGER, '(random())'];
        yield 'SQLite datetime()' => [SQLitePlatform::class, '0', Types::DATETIME_MUTABLE, "(datetime('now'))"];
        yield 'SQLite upper()' => [SQLitePlatform::class, '0', Types::STRING, "(upper('abc'))"];
        yield 'SQLite concatenation' => [SQLitePlatform::class, '0', Types::STRING, "('a' || 'b')"];
    }

    #[DataProvider('functionLikeStringDefaultProvider')]
    public function testFunctionLikeStringDefaultComparison(string $value): void
    {
        $table = Table::editor()
            ->setUnquotedName('function_like_default')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('test')
                    ->setTypeName(Types::STRING)
                    ->setLength(32)
                    ->setDefaultValue($value)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTableByUnquotedName('function_like_default');

        self::assertSame($value, $onlineTable->getColumn('test')->getDefault());

        $this->assertNoDiffInEitherDirection($table);
    }

    /** @return iterable<string, array{string}> */
    public static function functionLikeStringDefaultProvider(): iterable
    {
        yield 'uuid()' => ['uuid()'];
        yield 'gen_random_uuid()' => ['gen_random_uuid()'];
        yield 'newid()' => ['newid()'];
        yield 'SYS_GUID()' => ['SYS_GUID()'];
        yield 'now()' => ['now()'];
        yield 'SYSDATE' => ['SYSDATE'];
        yield 'quote' => ["it's"];
        yield 'concatenation' => ["a' || 'b"];
        yield 'parenthesized' => ['(1 + 2)'];
        yield 'numeric' => ['1.5'];
    }

    private function assertNoDiffInEitherDirection(Table $table): void
    {
        $comparator = $this->schemaManager->createComparator();

        self::assertTrue(
            ComparatorTestUtils::diffFromActualToDesiredTable($this->schemaManager, $comparator, $table)
                ->isEmpty(),
        );

        self::assertTrue(
            ComparatorTestUtils::diffFromDesiredToActualTable($this->schemaManager, $comparator, $table)
                ->isEmpty(),
        );
    }

    private function createDefaultExpression(string $sql): DefaultExpression
    {
        return new class ($sql) implements DefaultExpression {
            public function __construct(private readonly string $sql)
            {
            }

            public function toSQL(AbstractPlatform $platform): string
            {
                return $this->sql;
            }
        };
    }

    #[TestWith([Types::FLOAT])]
    #[TestWith([Types::SMALLFLOAT])]
    public function testFloatDefaultValueComparisonConverges(string $typeName): void
    {
        $table = Table::editor()
            ->setUnquotedName('float_default_value')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('score')
                    ->setTypeName($typeName)
                    ->setDefaultValue(14.75)
                    ->create(),
            )
            ->create();

        $this->dropAndCreateTable($table);

        $comparator = $this->schemaManager->createComparator();

        self::assertTrue(ComparatorTestUtils::diffFromActualToDesiredTable(
            $this->schemaManager,
            $comparator,
            $table,
        )->isEmpty());

        self::assertTrue(ComparatorTestUtils::diffFromDesiredToActualTable(
            $this->schemaManager,
            $comparator,
            $table,
        )->isEmpty());

        $desiredTable = $table->edit()
            ->modifyColumnByUnquotedName('score', static function (ColumnEditor $editor): void {
                $editor->setDefaultValue(50.0);
            })
            ->create();

        ComparatorTestUtils::assertDiffNotEmpty($this->connection, $comparator, $desiredTable);
    }

    public function testRenameColumnComparison(): void
    {
        $platform   = $this->connection->getDatabasePlatform();
        $comparator = new Comparator($platform, new ComparatorConfig());

        $table = Table::editor()
            ->setUnquotedName('rename_table')
            ->setColumns(
                Column::editor()
                    ->setUnquotedName('test')
                    ->setTypeName(Types::STRING)
                    ->setLength(20)
                    ->setDefaultValue('baz')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test2')
                    ->setTypeName(Types::STRING)
                    ->setLength(20)
                    ->setDefaultValue('baz')
                    ->create(),
                Column::editor()
                    ->setUnquotedName('test3')
                    ->setTypeName(Types::STRING)
                    ->setLength(10)
                    ->setDefaultValue('foo')
                    ->create(),
            )
            ->create();

        $onlineTable = clone $table;
        $table->renameColumn('test', 'baz')
            ->setLength(40)
            ->setComment('Comment');

        $table->renameColumn('test2', 'foo');

        $table->getColumn('test3')
            ->setAutoincrement(true)
            ->setNotnull(false)
            ->setType(Type::getType(Types::BIGINT));

        $compareResult  = $comparator->compareTables($onlineTable, $table);
        $renamedColumns = RenameColumnTest::getRenamedColumns($compareResult);
        self::assertSame($renamedColumns, $compareResult->getRenamedColumns());
        self::assertCount(3, $compareResult->getChangedColumns());
        self::assertCount(2, $compareResult->getModifiedColumns());
        self::assertCount(2, $renamedColumns);
        self::assertArrayHasKey('test2', $renamedColumns);

        $renamedOnly        = $compareResult->getChangedColumns()['test2'];
        $renamedAndModified = $compareResult->getChangedColumns()['test'];
        $modifiedOnly       = $compareResult->getChangedColumns()['test3'];

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('test2'),
            $renamedOnly->getNewColumn()->getObjectName(),
        );

        self::assertTrue($renamedOnly->hasNameChanged());
        self::assertEquals(1, $renamedOnly->countChangedProperties());

        $this->assertUnqualifiedNameEquals(
            UnqualifiedName::unquoted('test'),
            $renamedAndModified->getNewColumn()->getObjectName(),
        );

        self::assertTrue($renamedAndModified->hasNameChanged());
        self::assertTrue($renamedAndModified->hasLengthChanged());
        self::assertTrue($renamedAndModified->hasCommentChanged());
        self::assertFalse($renamedAndModified->hasTypeChanged());
        self::assertEquals(3, $renamedAndModified->countChangedProperties());

        self::assertTrue($modifiedOnly->hasAutoIncrementChanged());
        self::assertTrue($modifiedOnly->hasNotNullChanged());
        self::assertTrue($modifiedOnly->hasTypeChanged());
        self::assertFalse($modifiedOnly->hasLengthChanged());
        self::assertFalse($modifiedOnly->hasCommentChanged());
        self::assertFalse($modifiedOnly->hasNameChanged());
        self::assertEquals(3, $modifiedOnly->countChangedProperties());
    }

    /** @return list<array{string, mixed}> */
    public static function defaultValueProvider(): iterable
    {
        return [
            [Types::INTEGER, 1],
            [Types::FLOAT, 14.75],
            [Types::FLOAT, '50.0'],
            [Types::SMALLFLOAT, 14.75],
            [Types::BOOLEAN, false],
            [Types::TEXT, 'Doctrine'],
        ];
    }
}
