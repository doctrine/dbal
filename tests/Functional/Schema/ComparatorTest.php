<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\Functional\Platform\RenameColumnTest;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\DataProvider;

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

        $table = new Table('default_value', [
            Column::editor()
                ->setUnquotedName('test')
                ->setTypeName($typeName)
                ->setDefaultValue($value)
                ->create(),
        ]);

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable('default_value');

        self::assertTrue(
            $this->schemaManager->createComparator()
                ->compareTables($table, $onlineTable)
                ->isEmpty(),
        );
    }

    public function testRenameColumnComparison(): void
    {
        $platform   = $this->connection->getDatabasePlatform();
        $comparator = new Comparator($platform, new ComparatorConfig());

        $table = new Table('rename_table');
        $table->addColumn('test', Types::STRING, ['default' => 'baz', 'length' => 20]);
        $table->addColumn('test2', Types::STRING, ['default' => 'baz', 'length' => 20]);
        $table->addColumn('test3', Types::STRING, ['default' => 'foo', 'length' => 10]);

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

        self::assertEquals('foo', $renamedOnly->getNewColumn()->getName());
        self::assertTrue($renamedOnly->hasNameChanged());
        self::assertEquals(1, $renamedOnly->countChangedProperties());

        self::assertEquals('baz', $renamedAndModified->getNewColumn()->getName());
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

    /** @return iterable<mixed[]> */
    public static function defaultValueProvider(): iterable
    {
        return [
            [Types::INTEGER, 1],
            [Types::BOOLEAN, false],
            [Types::TEXT, 'Doctrine'],
        ];
    }
}
