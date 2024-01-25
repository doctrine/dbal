<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
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
