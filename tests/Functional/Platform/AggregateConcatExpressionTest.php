<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Platform;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;

use function explode;
use function sprintf;

class AggregateConcatExpressionTest extends FunctionalTestCase
{
    public function testAggregateConcat(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $this->fillTestTable();

        $sql = sprintf('SELECT %s FROM aggregate_concat_test', $platform->getAggregateConcatExpression('value', $this->connection->quote(',')));

        // The order is not guaranteed, so we can only check if the parts are the same
        $concatenated = $this->connection->query($sql)->fetchOne();
        $parts        = explode(',', $concatenated);

        self::assertEqualsCanonicalizing(['foo', 'bar', 'baz'], $parts);
    }

    public function testAggregateConcatWithOrder(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SqlitePlatform) {
            self::markTestSkipped('Not supported on SQLite');
        }

        $this->fillTestTable();

        $sql = sprintf('SELECT %s FROM aggregate_concat_test', $platform->getAggregateConcatExpression('value', $this->connection->quote(','), 'value DESC'));

        // The order is not guaranteed, so we can only check if the parts are the same
        $concatenated = $this->connection->query($sql)->fetchOne();

        self::assertEquals('foo,baz,bar', $concatenated);
    }

    private function fillTestTable(): void
    {
        $table = new Table('aggregate_concat_test');
        $table->addColumn('value', 'string', ['length' => 10]);
        $this->connection->getSchemaManager()->dropAndCreateTable($table);
        $this->connection->insert('aggregate_concat_test', ['value' => 'foo'], ['value' => ParameterType::STRING]);
        $this->connection->insert('aggregate_concat_test', ['value' => 'bar'], ['value' => ParameterType::STRING]);
        $this->connection->insert('aggregate_concat_test', ['value' => 'baz'], ['value' => ParameterType::STRING]);
    }
}
