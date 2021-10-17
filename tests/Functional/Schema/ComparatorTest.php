<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function array_merge;

class ComparatorTest extends FunctionalTestCase
{
    /** @var AbstractSchemaManager */
    private $schemaManager;

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
        $table = new Table('default_value');
        $table->addColumn('test', $type, ['default' => $value]);

        $this->schemaManager->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->listTableDetails('default_value');

        self::assertFalse($comparatorFactory($this->schemaManager)->diffTable($table, $onlineTable));
    }

    /**
     * @return iterable<mixed[]>
     */
    public static function defaultValueProvider(): iterable
    {
        foreach (ComparatorTestUtils::comparatorProvider() as $comparatorArguments) {
            foreach (
                [
                    ['integer', 1],
                    ['boolean', false],
                ] as $testArguments
            ) {
                yield array_merge($comparatorArguments, $testArguments);
            }
        }
    }
}
