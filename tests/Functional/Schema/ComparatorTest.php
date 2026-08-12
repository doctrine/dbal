<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Platform\RenameColumnTest;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

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

        // an actual change of the default value must produce a diff that converges once applied
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
            [Types::FLOAT, 50.0],
            [Types::FLOAT, '50.0'],
            [Types::SMALLFLOAT, 14.75],
            [Types::BOOLEAN, false],
            [Types::TEXT, 'Doctrine'],
        ];
    }
}
