<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Schema;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

class ComparatorTest extends FunctionalTestCase
{
    private AbstractSchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->schemaManager = $this->connection->createSchemaManager();
    }

    /**
     * @dataProvider defaultValueProvider
     */
    public function testDefaultValueComparison(string $type, mixed $value): void
    {
        $table = new Table('default_value');
        $table->addColumn('test', $type, ['default' => $value]);

        $this->dropAndCreateTable($table);

        $onlineTable = $this->schemaManager->listTableDetails('default_value');

        self::assertNull(
            $this->schemaManager->createComparator()
                ->diffTable($table, $onlineTable)
        );
    }

    /**
     * @return iterable<mixed[]>
     */
    public static function defaultValueProvider(): iterable
    {
        return [
            ['integer', 1],
            ['boolean', false],
        ];
    }
}
