<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

use function array_merge;
use function sprintf;
use function var_export;

class ComparatorTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->schemaManager = $this->connection->getSchemaManager();
    }

    /**
     * @param callable(AbstractSchemaManager):Comparator $comparatorFactory
     * @param mixed                                      $value
     *
     * @dataProvider defaultValueProvider
     */
    public function testDefaultValueComparison(callable $comparatorFactory, string $type, $value): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (
            $type === Types::TEXT && $platform instanceof AbstractMySQLPlatform
            && ! $platform instanceof MariaDBPlatform
        ) {
            // See https://dev.mysql.com/doc/relnotes/mysql/8.0/en/news-8-0-13.html#mysqld-8-0-13-data-types
            self::markTestSkipped('Oracle MySQL does not support default values on TEXT/BLOB columns until 8.0.13.');
        }

        $table = new Table('default_value');
        $table->addColumn('test', $type, ['default' => $value]);

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->introspectTable('default_value');

        self::assertFalse($comparatorFactory($this->schemaManager)->diffTable($table, $onlineTable));
    }

    public function testRenameColumnComparison(): void
    {
        $comparator = new Comparator();

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

        $compareResult = $comparator->compareTables($onlineTable, $table);
        self::assertCount(3, $compareResult->getChangedColumns());
        self::assertCount(2, $compareResult->getRenamedColumns());
        self::assertCount(2, $compareResult->getModifiedColumns());
        self::assertArrayHasKey('test2', $compareResult->getRenamedColumns());

        $renamedOnly        = $compareResult->changedColumns['test2'];
        $renamedAndModified = $compareResult->changedColumns['test'];
        $modifiedOnly       = $compareResult->changedColumns['test3'];

        self::assertEquals('foo', $renamedOnly->getNewColumn()->getName());
        self::assertTrue($renamedOnly->hasNameChanged());
        self::assertCount(0, $renamedOnly->changedProperties);

        self::assertEquals('baz', $renamedAndModified->getNewColumn()->getName());
        self::assertTrue($renamedAndModified->hasNameChanged());
        self::assertTrue($renamedAndModified->hasLengthChanged());
        self::assertTrue($renamedAndModified->hasCommentChanged());
        self::assertFalse($renamedAndModified->hasTypeChanged());
        self::assertCount(2, $renamedAndModified->changedProperties);

        self::assertTrue($modifiedOnly->hasAutoIncrementChanged());
        self::assertTrue($modifiedOnly->hasNotNullChanged());
        self::assertTrue($modifiedOnly->hasTypeChanged());
        self::assertFalse($modifiedOnly->hasLengthChanged());
        self::assertFalse($modifiedOnly->hasCommentChanged());
        self::assertFalse($modifiedOnly->hasNameChanged());
        self::assertCount(3, $modifiedOnly->changedProperties);
    }

    /** @return iterable<mixed[]> */
    public static function defaultValueProvider(): iterable
    {
        foreach (ComparatorTestUtils::comparatorProvider() as $comparatorType => $comparatorArguments) {
            foreach (
                [
                    [Types::INTEGER, 1],
                    [Types::BOOLEAN, false],
                    [Types::TEXT, 'Doctrine'],
                ] as $testArguments
            ) {
                yield sprintf(
                    '%s with default %s value %s',
                    $comparatorType,
                    $testArguments[0],
                    var_export($testArguments[1], true),
                ) => array_merge($comparatorArguments, $testArguments);
            }
        }
    }
}
