<?php

declare(strict_types=1);

namespace Doctrine\Tests\DBAL\Functional\Platform;

use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Tests\DbalFunctionalTestCase;

class TextTypeTest extends DbalFunctionalTestCase
{
    public function testAddColumnWithDefault(): void
    {
        $schemaManager = $this->connection->getSchemaManager();

        $table = new Table('text_type_test');

        $table->addColumn('data', Types::TEXT);
        $schemaManager->dropAndCreateTable($table);

        $this->connection->executeStatement('INSERT INTO text_type_test (data) VALUES (?)', ['Zażółć']);

        $query  = 'SELECT data FROM text_type_test';
        $result = $this->connection->executeQuery($query)->fetch(FetchMode::NUMERIC);
        self::assertSame(['Zażółć'], $result);
    }
}
